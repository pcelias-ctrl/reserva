<?php

require_once __DIR__ . '/../config/app.php';

function send_reservation_email($to, $subject, $message)
{
    global $MAIL_FROM, $MAIL_FROM_NAME;

    if (!$to) {
        return false;
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
    send_reservation_email($reservation['customer_email'], 'Recebemos sua reserva', "Obrigado. Sua reserva está em análise.\n\n" . $body);

    if ($MAIL_ADMIN_TO) {
        send_reservation_email($MAIL_ADMIN_TO, 'Nova reserva recebida', $body);
    }
}
