<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_url($path = '')
{
    global $APP_URL;
    if ($APP_URL) {
        return $APP_URL . '/' . ltrim($path, '/');
    }
    return $path;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!$posted || !hash_equals(csrf_token(), $posted)) {
            http_response_code(419);
            die('Sessão expirada. Recarregue a página e tente novamente.');
        }
    }
}

function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $value;
    }

    return null;
}

function current_customer()
{
    return isset($_SESSION['customer']) ? $_SESSION['customer'] : null;
}

function redirect_to($path)
{
    header('Location: ' . $path);
    exit;
}

function only_digits($value)
{
    return preg_replace('/\D+/', '', (string)$value);
}

function build_whatsapp_url($phone, $message)
{
    $digits = only_digits($phone);
    if ($digits === '') {
        return '';
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function restaurant_logo_src($restaurant)
{
    $hasLogo = !empty($restaurant['has_logo']) || (!array_key_exists('has_logo', $restaurant) && !empty($restaurant['logo_mime']));
    if ($hasLogo) {
        $version = !empty($restaurant['logo_version']) ? '&v=' . rawurlencode($restaurant['logo_version']) : '';
        return '/logo.php?id=' . (int)$restaurant['id'] . $version;
    }
    if (!empty($restaurant['logo_url'])) {
        return $restaurant['logo_url'];
    }
    return '';
}

function reservation_whatsapp_message($reservation)
{
    $lines = array(
        'Nova reserva pelo i-Reserva',
        'Restaurante: ' . $reservation['restaurant_name'],
        'Cliente: ' . $reservation['customer_name'],
        'Telefone: ' . $reservation['customer_phone'],
        'E-mail: ' . $reservation['customer_email'],
        'Data: ' . date('d/m/Y', strtotime($reservation['reservation_date'])),
        'Horário: ' . substr($reservation['reservation_time'], 0, 5),
        'Pessoas: ' . $reservation['party_size'],
        'Ocasião: ' . $reservation['occasion_name']
    );

    if (!empty($reservation['dietary_restrictions'])) {
        $lines[] = 'Restrições: ' . $reservation['dietary_restrictions'];
    }
    if (!empty($reservation['notes'])) {
        $lines[] = 'Observações: ' . $reservation['notes'];
    }

    return implode("\n", $lines);
}
