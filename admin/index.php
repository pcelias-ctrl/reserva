<?php
$title = 'Painel';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/header.php';

$cards = array(
    'pending' => 'Pendentes',
    'approved' => 'Aprovadas',
    'confirmed' => 'Confirmadas',
    'seated' => 'Na casa'
);
$counts = array();
foreach ($cards as $status => $label) {
    $stmt = $pdo->prepare('SELECT COUNT(*) total FROM reservations WHERE status = ?');
    $stmt->execute(array($status));
    $counts[$status] = (int)$stmt->fetch()['total'];
}

$weekParam = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');
$weekTimestamp = strtotime($weekParam);
if ($weekTimestamp === false) {
    $weekTimestamp = time();
}
$weekStart = date('Y-m-d', strtotime('monday this week', $weekTimestamp));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$previousWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' +7 days'));
$today = date('Y-m-d');

$dayLabels = array('Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo');
$weekdays = array();
for ($day = 0; $day < 7; $day++) {
    $date = date('Y-m-d', strtotime($weekStart . ' +' . $day . ' days'));
    $weekdays[$date] = array(
        'label' => $dayLabels[$day],
        'reservations' => array()
    );
}

$stmt = $pdo->prepare(
    "SELECT r.*, rest.name restaurant_name, COALESCE(o.name, 'Nenhuma') occasion_name, COALESCE(e.name, 'Sem preferência') environment_name
     FROM reservations r
     INNER JOIN restaurants rest ON rest.id = r.restaurant_id
     LEFT JOIN occasions o ON o.id = r.occasion_id
     LEFT JOIN environments e ON e.id = r.environment_id
     WHERE r.reservation_date BETWEEN ? AND ?
     ORDER BY r.reservation_date, r.reservation_time"
);
$stmt->execute(array($weekStart, $weekEnd));
foreach ($stmt->fetchAll() as $reservation) {
    if (isset($weekdays[$reservation['reservation_date']])) {
        $weekdays[$reservation['reservation_date']]['reservations'][] = $reservation;
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
    <div class="section-title">
        <div>
            <p class="eyebrow">Agenda semanal</p>
            <h2><?php echo e(date('d/m', strtotime($weekStart)) . ' a ' . date('d/m/Y', strtotime($weekEnd))); ?></h2>
        </div>
        <div class="filters">
            <a href="index.php?week=<?php echo e($previousWeek); ?>">Semana anterior</a>
            <a href="index.php?week=<?php echo e($today); ?>">Hoje</a>
            <a href="index.php?week=<?php echo e($nextWeek); ?>">Próxima semana</a>
        </div>
    </div>
    <div class="week-agenda">
        <?php foreach ($weekdays as $date => $day): ?>
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
                        <a class="agenda-reservation status-<?php echo e($reservation['status']); ?>" href="reservas.php?id=<?php echo (int)$reservation['id']; ?>">
                            <span class="agenda-time"><?php echo e(substr($reservation['reservation_time'], 0, 5)); ?></span>
                            <strong><?php echo e($reservation['customer_name']); ?></strong>
                            <em><?php echo (int)$reservation['party_size']; ?> lugares · <?php echo e($reservation['restaurant_name']); ?></em>
                            <small><?php echo e(reservation_status_label($reservation['status'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
