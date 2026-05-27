<?php

require_once __DIR__ . '/../config/app.php';

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
    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: ' . $to,
        'Subject: ' . $subject
    );
    $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $message);

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
        'Content-type: text/plain; charset=UTF-8',
        'From: ' . $MAIL_FROM_NAME . ' <' . $MAIL_FROM . '>'
    );

    $sent = @mail($to, $subject, $message, implode("\r\n", $headers));
    if (!$sent) {
        set_email_error('Falha no mail() do PHP. Configure o SMTP do restaurante.');
    }
    return $sent;
}

function notify_reservation_created($reservation, $answers)
{
    global $MAIL_ADMIN_TO;

    $lines = array();
    $lines[] = 'Nova reserva recebida pelo i-Reserva';
    if (!empty($reservation['restaurant_name'])) {
        $lines[] = 'Restaurante: ' . $reservation['restaurant_name'];
    }
    $lines[] = 'Cliente: ' . $reservation['customer_name'];
    $lines[] = 'E-mail: ' . $reservation['customer_email'];
    $lines[] = 'Telefone: ' . $reservation['customer_phone'];
    $lines[] = 'Data/Hora: ' . $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
    $lines[] = 'Pessoas: ' . $reservation['party_size'];
    $lines[] = 'Ocasião: ' . $reservation['occasion_name'];
    $lines[] = 'Restrições: ' . $reservation['dietary_restrictions'];
    $lines[] = 'Observações: ' . $reservation['notes'];

    foreach ($answers as $label => $answer) {
        $lines[] = $label . ': ' . $answer;
    }

    $body = implode("\n", $lines);
    send_reservation_email($reservation['customer_email'], 'Recebemos sua reserva', "Obrigado. Sua reserva está em análise.\n\n" . $body, $reservation);

    $adminTo = !empty($reservation['smtp_admin_email']) ? $reservation['smtp_admin_email'] : $MAIL_ADMIN_TO;
    if ($adminTo) {
        send_reservation_email($adminTo, 'Nova reserva recebida', $body, $reservation);
    }
}
