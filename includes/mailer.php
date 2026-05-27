<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

$LAST_EMAIL_ERROR = '';

function set_email_error($message)
{
    global $LAST_EMAIL_ERROR;
    $LAST_EMAIL_ERROR = $message;
}

function last_email_error()
{
    global $LAST_EMAIL_ERROR;
    return $LAST_EMAIL_ERROR;
}

function email_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function email_header_text($value)
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B');
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function email_linkify($escapedText)
{
    return preg_replace(
        '~(https?://[^\s<]+)~',
        '<a href="$1" style="color:#087f73;font-weight:700">$1</a>',
        $escapedText
    );
}

function normalize_email_text($message)
{
    return trim(str_replace(array("\r\n", "\r"), "\n", (string)$message));
}

function html_email_body($subject, $message)
{
    $message = normalize_email_text($message);
    $blocks = preg_split("/\n{2,}/", $message);
    $htmlBlocks = array();

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $lines = explode("\n", $block);
        if (count($lines) > 1) {
            $items = array();
            foreach ($lines as $line) {
                $items[] = '<li>' . email_linkify(email_escape(trim($line))) . '</li>';
            }
            $htmlBlocks[] = '<ul>' . implode('', $items) . '</ul>';
        } else {
            $htmlBlocks[] = '<p>' . nl2br(email_linkify(email_escape($block))) . '</p>';
        }
    }

    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
body{margin:0;background:#f7f8f4;color:#17211f;font-family:Arial,Helvetica,sans-serif;line-height:1.55}
.wrap{max-width:640px;margin:0 auto;padding:28px 18px}.card{background:#fff;border:1px solid #dde5dd;border-radius:16px;box-shadow:0 12px 30px rgba(16,32,29,.08);overflow:hidden}
.head{background:#07534d;color:#fff;padding:22px 24px}.head h1{font-size:22px;line-height:1.2;margin:0}.body{padding:24px}
p{margin:0 0 14px}ul{margin:0 0 16px;padding:0;list-style:none}li{border-bottom:1px solid #edf2ed;padding:8px 0}li:last-child{border-bottom:0}
.footer{color:#64716d;font-size:12px;padding:0 24px 22px}</style></head><body><div class="wrap"><div class="card"><div class="head"><h1>' . email_escape($subject) . '</h1></div><div class="body">' . implode('', $htmlBlocks) . '</div><div class="footer">Mensagem enviada automaticamente pelo i-Reserva.</div></div></div></body></html>';
}

function smtp_safe_data($html)
{
    $html = str_replace(array("\r\n", "\r"), "\n", $html);
    $html = str_replace("\n.", "\n..", $html);
    return str_replace("\n", "\r\n", $html);
}

function smtp_read($socket)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_command($socket, $command, $expected)
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, (array)$expected, true)) {
        set_email_error('Resposta SMTP inesperada: ' . trim($response));
        return false;
    }
    return true;
}

function smtp_mail($settings, $to, $subject, $message)
{
    if (empty($settings['smtp_enabled']) || empty($settings['smtp_host']) || empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        set_email_error('SMTP não está habilitado ou está incompleto no cadastro do restaurante.');
        return false;
    }

    $host = $settings['smtp_host'];
    $port = !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : 587;
    $encryption = !empty($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls';
    $target = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 20);
    if (!$socket) {
        set_email_error('Não foi possível conectar ao SMTP: ' . $errstr);
        return false;
    }

    stream_set_timeout($socket, 20);
    $greeting = smtp_read($socket);
    if ((int)substr($greeting, 0, 3) !== 220) {
        set_email_error('Servidor SMTP recusou conexão: ' . trim($greeting));
        fclose($socket);
        return false;
    }

    $serverName = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    if (!smtp_command($socket, 'EHLO ' . $serverName, 250)) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtp_command($socket, 'STARTTLS', 220) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            set_email_error('Falha ao iniciar conexão TLS com o SMTP.');
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, 'EHLO ' . $serverName, 250)) {
            fclose($socket);
            return false;
        }
    }

    if (!smtp_command($socket, 'AUTH LOGIN', 334)
        || !smtp_command($socket, base64_encode($settings['smtp_username']), 334)
        || !smtp_command($socket, base64_encode($settings['smtp_password']), 235)) {
        if (!last_email_error()) {
            set_email_error('Usuário ou senha SMTP inválidos.');
        }
        fclose($socket);
        return false;
    }

    $fromEmail = !empty($settings['smtp_from_email']) ? $settings['smtp_from_email'] : $settings['smtp_username'];
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : 'i-Reserva';
    $html = html_email_body($subject, $message);
    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . email_header_text($fromName) . ' <' . $fromEmail . '>',
        'To: ' . $to,
        'Subject: ' . email_header_text($subject)
    );
    $data = implode("\r\n", $headers) . "\r\n\r\n" . smtp_safe_data($html);

    $ok = smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', 250)
        && smtp_command($socket, 'RCPT TO:<' . $to . '>', array(250, 251))
        && smtp_command($socket, 'DATA', 354)
        && smtp_command($socket, $data . "\r\n.", 250);
    smtp_command($socket, 'QUIT', 221);
    fclose($socket);
    if (!$ok && !last_email_error()) {
        set_email_error('Falha ao entregar a mensagem pelo SMTP.');
    }
    return $ok;
}

function send_reservation_email($to, $subject, $message, $settings = null)
{
    global $MAIL_FROM, $MAIL_FROM_NAME;

    if (!$to) {
        set_email_error('Destinatário de e-mail não informado.');
        return false;
    }

    if ($settings && smtp_mail($settings, $to, $subject, $message)) {
        return true;
    }

    if ($settings) {
        return false;
    }

    $sendmailPath = trim((string)ini_get('sendmail_path'));
    $sendmailBinary = $sendmailPath ? strtok($sendmailPath, ' ') : '';
    if ($sendmailBinary && !is_executable($sendmailBinary)) {
        set_email_error('Servidor sem sendmail disponível. Configure o SMTP do restaurante.');
        return false;
    }

    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . email_header_text($MAIL_FROM_NAME) . ' <' . $MAIL_FROM . '>'
    );

    $sent = @mail($to, email_header_text($subject), html_email_body($subject, $message), implode("\r\n", $headers));
    if (!$sent) {
        set_email_error('Falha no mail() do PHP. Configure o SMTP do restaurante.');
    }
    return $sent;
}

function notify_reservation_created($reservation, $answers)
{
    global $MAIL_ADMIN_TO;

    $restaurantName = !empty($reservation['restaurant_name']) ? $reservation['restaurant_name'] : 'restaurante';
    $date = date('d/m/Y', strtotime($reservation['reservation_date']));
    $time = substr($reservation['reservation_time'], 0, 5);

    $clientLines = array(
        'Olá, ' . $reservation['customer_name'] . '!',
        '',
        'Recebemos sua solicitação de reserva no ' . $restaurantName . '.',
        'A equipe do restaurante vai analisar a disponibilidade e retornar com a confirmação.',
        '',
        'Resumo da reserva',
        'Restaurante: ' . $restaurantName,
        'Data: ' . $date,
        'Horário: ' . $time,
        'Pessoas: ' . $reservation['party_size'],
        'Ocasião: ' . $reservation['occasion_name'],
        '',
        'Obrigado por escolher o i-Reserva.'
    );
    if (!empty($reservation['customer_email'])) {
        send_reservation_email($reservation['customer_email'], 'Recebemos sua reserva', implode("\n", $clientLines), $reservation);
    }

    $adminLines = array(
        'Nova reserva recebida pelo i-Reserva',
        '',
        'Restaurante: ' . $restaurantName,
        'Cliente: ' . $reservation['customer_name'],
        'E-mail: ' . $reservation['customer_email'],
        'Telefone: ' . $reservation['customer_phone'],
        'Data: ' . $date,
        'Horário: ' . $time,
        'Pessoas: ' . $reservation['party_size'],
        'Ocasião: ' . $reservation['occasion_name'],
        'Restrições alimentares: ' . ($reservation['dietary_restrictions'] ?: 'Não informado'),
        'Observações: ' . ($reservation['notes'] ?: 'Não informado')
    );

    if ($answers) {
        $adminLines[] = '';
        $adminLines[] = 'Respostas do questionário';
        foreach ($answers as $label => $answer) {
            $adminLines[] = $label . ': ' . $answer;
        }
    }

    $adminTo = !empty($reservation['smtp_admin_email']) ? $reservation['smtp_admin_email'] : $MAIL_ADMIN_TO;
    if ($adminTo) {
        send_reservation_email($adminTo, 'Nova reserva recebida', implode("\n", $adminLines), $reservation);
    }
}

function reservation_status_email_message($reservation, $status)
{
    $restaurantName = !empty($reservation['restaurant_name']) ? $reservation['restaurant_name'] : 'restaurante';
    $date = date('d/m/Y', strtotime($reservation['reservation_date']));
    $time = substr($reservation['reservation_time'], 0, 5);

    return implode("\n", array(
        'Olá, ' . $reservation['customer_name'] . '!',
        '',
        'Sua reserva no ' . $restaurantName . ' foi atualizada.',
        '',
        'Status atual: ' . reservation_status_label($status),
        'Data: ' . $date,
        'Horário: ' . $time,
        'Pessoas: ' . $reservation['party_size'],
        '',
        'Em caso de dúvida, responda este e-mail ou fale diretamente com o restaurante.'
    ));
}
