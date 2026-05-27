<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

verify_csrf();

$customer = current_customer();
$customerId = $customer ? (int)$customer['id'] : null;
$email = trim($_POST['customer_email']);
$name = trim($_POST['customer_name']);
$phone = trim($_POST['customer_phone']);

if (!$customerId && !empty($_POST['customer_password'])) {
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
    $stmt->execute(array($email));
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare('INSERT INTO customers (name, email, phone, password_hash, lgpd_marketing_consent) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(array($name, $email, $phone, password_hash($_POST['customer_password'], PASSWORD_DEFAULT), isset($_POST['lgpd_share_consent']) ? 1 : 0));
        $customerId = (int)$pdo->lastInsertId();
        $_SESSION['customer'] = array('id' => $customerId, 'name' => $name, 'email' => $email, 'phone' => $phone);
    }
}

$occasionId = !empty($_POST['occasion_id']) ? (int)$_POST['occasion_id'] : null;
$restaurantId = !empty($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
$environmentId = !empty($_POST['environment_id']) ? (int)$_POST['environment_id'] : null;

$stmt = $pdo->prepare("SELECT id FROM restaurants WHERE id = ? AND status = 'active'");
$stmt->execute(array($restaurantId));
if (!$stmt->fetch()) {
    flash('error', 'Selecione um restaurante válido.');
    redirect_to('index.php');
}

$weekday = (int)date('w', strtotime($_POST['reservation_date']));
$stmt = $pdo->prepare(
    "SELECT COUNT(*) total
     FROM restaurant_hours
     WHERE restaurant_id = ?
       AND weekday = ?
       AND is_closed = 0
       AND opens_at IS NOT NULL
       AND closes_at IS NOT NULL
       AND ? BETWEEN TIME_FORMAT(opens_at, '%H:%i') AND TIME_FORMAT(closes_at, '%H:%i')"
);
$stmt->execute(array($restaurantId, $weekday, $_POST['reservation_time']));
$schedule = $stmt->fetch();
if ((int)$schedule['total'] === 0) {
    flash('error', 'Horário indisponível para o restaurante e data selecionados.');
    redirect_to('index.php');
}

$stmt = $pdo->prepare(
    'INSERT INTO reservations
    (restaurant_id, customer_id, occasion_id, environment_id, customer_name, customer_email, customer_phone, reservation_date, reservation_time, party_size, birthday_day, birthday_month, dietary_restrictions, notes, lgpd_terms_consent, lgpd_share_consent)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute(array(
    $restaurantId,
    $customerId,
    $occasionId,
    $environmentId,
    $name,
    $email,
    $phone,
    $_POST['reservation_date'],
    $_POST['reservation_time'],
    (int)$_POST['party_size'],
    !empty($_POST['birthday_day']) ? (int)$_POST['birthday_day'] : null,
    !empty($_POST['birthday_month']) ? (int)$_POST['birthday_month'] : null,
    trim($_POST['dietary_restrictions']),
    trim($_POST['notes']),
    isset($_POST['lgpd_terms_consent']) ? 1 : 0,
    isset($_POST['lgpd_share_consent']) ? 1 : 0
));

$reservationId = (int)$pdo->lastInsertId();
$answerLabels = array();
if (!empty($_POST['answers']) && is_array($_POST['answers'])) {
    $questionStmt = $pdo->prepare('SELECT label FROM questionnaire_questions WHERE id = ?');
    $answerStmt = $pdo->prepare('INSERT INTO reservation_answers (reservation_id, question_id, answer) VALUES (?, ?, ?)');
    foreach ($_POST['answers'] as $questionId => $answer) {
        $answerText = is_array($answer) ? implode(', ', $answer) : trim($answer);
        if ($answerText === '') {
            continue;
        }
        $answerStmt->execute(array($reservationId, (int)$questionId, $answerText));
        $questionStmt->execute(array((int)$questionId));
        $question = $questionStmt->fetch();
        if ($question) {
            $answerLabels[$question['label']] = $answerText;
        }
    }
}

$stmt = $pdo->prepare("SELECT r.*, COALESCE(o.name, 'Nenhuma') AS occasion_name, rest.name restaurant_name, rest.whatsapp restaurant_whatsapp, rest.reservation_message, rest.smtp_enabled, rest.smtp_host, rest.smtp_port, rest.smtp_username, rest.smtp_password, rest.smtp_encryption, rest.smtp_from_email, rest.smtp_from_name, rest.smtp_admin_email FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id LEFT JOIN occasions o ON o.id = r.occasion_id WHERE r.id = ?");
$stmt->execute(array($reservationId));
$reservation = $stmt->fetch();
notify_reservation_created($reservation, $answerLabels);

$_SESSION['last_whatsapp_url'] = build_whatsapp_url($reservation['restaurant_whatsapp'], reservation_whatsapp_message($reservation));
flash('success', 'Reserva enviada. O restaurante vai analisar e confirmar por e-mail.');
redirect_to('obrigado.php?id=' . $reservationId);
