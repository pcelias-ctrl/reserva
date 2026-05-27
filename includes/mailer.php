<?php

require_once __DIR__ . '/../config/app.php';

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
    return in_array((int)substr($response, 0, 3), (array)$expected, true);
}

function smtp_mail($settings, $to, $subject, $message)
{
    if (empty($settings['smtp_enabled']) || empty($settings['smtp_host']) || empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        return false;
    }

    $host = $settings['smtp_host'];
    $port = !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : 587;
    $encryption = !empty($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls';
    $target = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 20);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 20);
    if ((int)substr(smtp_read($socket), 0, 3) !== 220) {
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
    return $ok;
}

function send_reservation_email($to, $subject, $message, $settings = null)
{
    global $MAIL_FROM, $MAIL_FROM_NAME;

    if (!$to) {
        return false;
    }

    if ($settings && smtp_mail($settings, $to, $subject, $message)) {
        return true;
    }

    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: ' . $MAIL_FROM_NAME . ' <' . $MAIL_FROM . '>'
    );

    return @mail($to, $subject, $message, implode("\r\n", $headers));
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
