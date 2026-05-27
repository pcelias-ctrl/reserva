<?php
$title = 'Painel';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

$viewOptions = array('week' => 7, 'fortnight' => 14, 'month' => 0);
$viewLabels = array('week' => 'Semanal', 'fortnight' => 'Quinzenal', 'month' => 'Mensal');
$statusLabels = array(
    'all' => 'Todas',
    'to_confirm' => 'Aguardando aprovação',
    'confirmed' => 'Confirmadas',
    'cancelled' => 'Canceladas',
    'completed' => 'Concluídas',
    'no_show' => 'Não compareceu'
);
$statusGroups = array(
    'confirmed' => array('approved', 'confirmed'),
    'to_confirm' => array('pending'),
    'cancelled' => array('cancelled'),
    'completed' => array('completed'),
    'no_show' => array('no_show')
);

$view = isset($_GET['view']) && isset($viewOptions[$_GET['view']]) ? $_GET['view'] : 'week';
$statusFilter = isset($_GET['status_group']) && isset($statusLabels[$_GET['status_group']]) ? $_GET['status_group'] : 'all';
$dateParam = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$dateTimestamp = strtotime($dateParam);
if ($dateTimestamp === false) {
    $dateTimestamp = time();
}

if ($view === 'month') {
    $rangeStart = date('Y-m-01', $dateTimestamp);
    $rangeEnd = date('Y-m-t', $dateTimestamp);
    $previousDate = date('Y-m-d', strtotime($rangeStart . ' -1 month'));
    $nextDate = date('Y-m-d', strtotime($rangeStart . ' +1 month'));
} else {
    $rangeStart = date('Y-m-d', strtotime('monday this week', $dateTimestamp));
    $rangeEnd = date('Y-m-d', strtotime($rangeStart . ' +' . ($viewOptions[$view] - 1) . ' days'));
    $previousDate = date('Y-m-d', strtotime($rangeStart . ' -' . $viewOptions[$view] . ' days'));
    $nextDate = date('Y-m-d', strtotime($rangeStart . ' +' . $viewOptions[$view] . ' days'));
}
$today = date('Y-m-d');

function panel_url($params)
{
    $base = array(
        'view' => isset($_GET['view']) ? $_GET['view'] : 'week',
        'status_group' => isset($_GET['status_group']) ? $_GET['status_group'] : 'all',
        'date' => isset($_GET['date']) ? $_GET['date'] : date('Y-m-d')
    );
    return 'index.php?' . http_build_query(array_merge($base, $params));
}

function feedback_message($reservation)
{
    global $APP_URL;

    $restaurant = !empty($reservation['restaurant_name']) ? $reservation['restaurant_name'] : 'nosso restaurante';
    $dateLine = 'Reserva: ' . date('d/m/Y', strtotime($reservation['reservation_date'])) . ' às ' . substr($reservation['reservation_time'], 0, 5);
    $baseUrl = !empty($APP_URL) ? $APP_URL : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
    $customerAreaUrl = rtrim($baseUrl, '/') . '/minhas-reservas.php';
    $hasCustomerPassword = !empty($reservation['customer_has_password']);

    if ($reservation['status'] === 'no_show') {
        $lines = array(
            'Olá, ' . $reservation['customer_name'] . '.',
            '',
            'Poxa, notamos que você não veio na data escolhida para sua reserva no ' . $restaurant . '.',
            '',
            'Quer agendar uma nova data? Conte o que aconteceu para que a equipe possa cuidar melhor do próximo contato.',
            '',
            $hasCustomerPassword
                ? 'Se preferir, acesse sua área no i-Reserva para deixar seu feedback: ' . $customerAreaUrl
                : 'Se preferir, responda este e-mail contando o que aconteceu. A equipe vai receber sua mensagem com atenção.',
            '',
            $dateLine,
            '',
            'Estamos à disposição para receber você em outro momento.'
        );
        return implode("\n", $lines);
    }

    $lines = array(
        'Olá, ' . $reservation['customer_name'] . '!',
        '',
        'Obrigado por escolher o ' . $restaurant . '. Deu tudo certo na sua experiência?',
        '',
        'Sua opinião ajuda a equipe a cuidar melhor de cada detalhe: atendimento, ambiente, tempo de espera, pratos e tudo o que fez diferença na sua visita.',
        '',
        'Pode responder este e-mail com poucas palavras mesmo: o que foi ótimo, o que poderia melhorar e se você voltaria a reservar conosco.',
        '',
        $hasCustomerPassword
            ? 'Se quiser registrar pelo sistema, acesse sua área no i-Reserva: ' . $customerAreaUrl
            : 'Se você ainda não criou senha no i-Reserva, sem problema: basta responder este e-mail e seu feedback chegará ao restaurante.',
        '',
        $dateLine,
        '',
        'Obrigado por dividir sua experiência com a gente. Até a próxima!'
    );
    return implode("\n", $lines);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

    if ($action === 'send_feedback_email' && $reservationId) {
        $stmt = $pdo->prepare(
            "SELECT r.*, rest.name restaurant_name, rest.smtp_enabled, rest.smtp_host, rest.smtp_port, rest.smtp_username, rest.smtp_password, rest.smtp_encryption, rest.smtp_from_email, rest.smtp_from_name,
                    (c.password_hash IS NOT NULL AND c.password_hash <> '') AS customer_has_password
             FROM reservations r
             INNER JOIN restaurants rest ON rest.id = r.restaurant_id
             LEFT JOIN customers c ON c.id = r.customer_id OR LOWER(c.email) = LOWER(r.customer_email)
             WHERE r.id = ?"
        );
        $stmt->execute(array($reservationId));
        $reservation = $stmt->fetch();
        if ($reservation && send_reservation_email($reservation['customer_email'], 'Como foi sua experiência?', feedback_message($reservation), $reservation)) {
            flash('success', 'E-mail de agradecimento enviado para ' . $reservation['customer_email'] . '.');
        } else {
            flash('error', 'Não foi possível enviar o e-mail: ' . last_email_error());
        }
    }

    redirect_to(panel_url(array()));
}

require_once __DIR__ . '/../includes/header.php';

$cards = array(
    'pending' => 'Aguardando aprovação',
    'confirmed' => 'Confirmadas',
    'cancelled' => 'Canceladas',
    'completed' => 'Concluídas',
    'no_show' => 'Não compareceu'
);
$counts = array();
foreach ($cards as $status => $label) {
    $stmt = $pdo->prepare('SELECT COUNT(*) total FROM reservations WHERE status = ?');
    $stmt->execute(array($status));
    $counts[$status] = (int)$stmt->fetch()['total'];
}

$dayLabels = array('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado');
$days = array();
$totalDays = (int)((strtotime($rangeEnd) - strtotime($rangeStart)) / 86400) + 1;
for ($day = 0; $day < $totalDays; $day++) {
    $date = date('Y-m-d', strtotime($rangeStart . ' +' . $day . ' days'));
    $days[$date] = array(
        'label' => $dayLabels[(int)date('w', strtotime($date))],
        'reservations' => array()
    );
}

$where = 'WHERE r.reservation_date BETWEEN ? AND ?';
$params = array($rangeStart, $rangeEnd);
if ($statusFilter !== 'all') {
    $placeholders = implode(',', array_fill(0, count($statusGroups[$statusFilter]), '?'));
    $where .= ' AND r.status IN (' . $placeholders . ')';
    foreach ($statusGroups[$statusFilter] as $status) {
        $params[] = $status;
    }
}

$stmt = $pdo->prepare(
    "SELECT r.*, rest.name restaurant_name, rest.whatsapp restaurant_whatsapp, COALESCE(o.name, 'Nenhuma') occasion_name, COALESCE(e.name, 'Sem preferência') environment_name
     FROM reservations r
     INNER JOIN restaurants rest ON rest.id = r.restaurant_id
     LEFT JOIN occasions o ON o.id = r.occasion_id
     LEFT JOIN environments e ON e.id = r.environment_id
     $where
     ORDER BY r.reservation_date, r.reservation_time"
);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $reservation) {
    if (isset($days[$reservation['reservation_date']])) {
        $days[$reservation['reservation_date']]['reservations'][] = $reservation;
    }
}
?>
<section class="dashboard-hero">
    <div>
        <p class="eyebrow">Painel</p>
        <h1>Agenda, aprovações e operação de reservas.</h1>
    </div>
    <a class="button primary" href="reservas.php">Ver reservas</a>
</section>

<section class="metric-grid">
    <?php foreach ($cards as $status => $label): ?>
        <div class="metric">
            <span><?php echo e($label); ?></span>
            <strong><?php echo (int)$counts[$status]; ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<section class="panel agenda-panel">
    <div class="section-title agenda-title">
        <div>
            <p class="eyebrow">Agenda <?php echo e(strtolower($viewLabels[$view])); ?></p>
            <h2><?php echo e(date('d/m', strtotime($rangeStart)) . ' a ' . date('d/m/Y', strtotime($rangeEnd))); ?></h2>
        </div>
        <div class="filters">
            <a href="<?php echo e(panel_url(array('date' => $previousDate))); ?>">Anterior</a>
            <a href="<?php echo e(panel_url(array('date' => $today))); ?>">Hoje</a>
            <a href="<?php echo e(panel_url(array('date' => $nextDate))); ?>">Próximo</a>
        </div>
    </div>

    <div class="agenda-toolbar">
        <div class="segmented-control">
            <?php foreach ($viewLabels as $option => $label): ?>
                <a class="<?php echo $view === $option ? 'active' : ''; ?>" href="<?php echo e(panel_url(array('view' => $option, 'date' => $today))); ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="segmented-control status-control">
            <?php foreach ($statusLabels as $option => $label): ?>
                <a class="<?php echo $statusFilter === $option ? 'active' : ''; ?>" href="<?php echo e(panel_url(array('status_group' => $option))); ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="week-agenda agenda-range-<?php echo e($view); ?>">
        <?php foreach ($days as $date => $day): ?>
            <div class="agenda-day <?php echo $date === $today ? 'today' : ''; ?>">
                <div class="agenda-day-header">
                    <strong><?php echo e($day['label']); ?></strong>
                    <span><?php echo e(date('d/m', strtotime($date))); ?></span>
                </div>
                <div class="agenda-items">
                    <?php if (!$day['reservations']): ?>
                        <span class="empty-day">Sem reservas</span>
                    <?php endif; ?>
                    <?php foreach ($day['reservations'] as $reservation): ?>
                        <article class="agenda-reservation status-<?php echo e($reservation['status']); ?>">
                            <a class="agenda-reservation-main" href="reservas.php?id=<?php echo (int)$reservation['id']; ?>">
                                <span class="agenda-time"><?php echo e(substr($reservation['reservation_time'], 0, 5)); ?></span>
                                <strong><?php echo e($reservation['customer_name']); ?></strong>
                                <em><?php echo (int)$reservation['party_size']; ?> lugares · <?php echo e($reservation['restaurant_name']); ?></em>
                                <small><?php echo e(reservation_status_label($reservation['status'])); ?></small>
                            </a>
                            <?php if (in_array($reservation['status'], array('completed', 'no_show'), true)): ?>
                                <div class="agenda-actions">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="send_feedback_email">
                                        <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">
                                        <button type="submit">Feedback por e-mail</button>
                                    </form>
                                    <a target="_blank" rel="noopener" href="<?php echo e(build_whatsapp_url($reservation['customer_phone'], feedback_message($reservation))); ?>">Feedback WhatsApp</a>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
