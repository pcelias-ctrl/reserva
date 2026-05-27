<?php
$title = 'Cockpit';
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

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT r.*, rest.name restaurant_name, COALESCE(o.name, 'Nenhuma') occasion_name, COALESCE(e.name, 'Sem preferência') environment_name FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id LEFT JOIN occasions o ON o.id = r.occasion_id LEFT JOIN environments e ON e.id = r.environment_id WHERE r.reservation_date >= ? ORDER BY r.reservation_date, r.reservation_time LIMIT 12");
$stmt->execute(array($today));
$agenda = $stmt->fetchAll();
?>
<section class="dashboard-hero">
    <div>
        <p class="eyebrow">Cockpit</p>
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

<section class="panel">
    <h2>Próximas reservas</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Quando</th><th>Restaurante</th><th>Cliente</th><th>Pessoas</th><th>Ambiente</th><th>Ocasião</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($agenda as $reservation): ?>
                <tr>
                    <td><?php echo e(date('d/m/Y', strtotime($reservation['reservation_date'])) . ' ' . substr($reservation['reservation_time'], 0, 5)); ?></td>
                    <td><?php echo e($reservation['restaurant_name']); ?></td>
                    <td><?php echo e($reservation['customer_name']); ?><small><?php echo e($reservation['customer_phone']); ?></small></td>
                    <td><?php echo (int)$reservation['party_size']; ?></td>
                    <td><?php echo e($reservation['environment_name']); ?></td>
                    <td><?php echo e($reservation['occasion_name']); ?></td>
                    <td><span class="badge"><?php echo e(reservation_status_label($reservation['status'])); ?></span></td>
                    <td><a class="button ghost" href="reservas.php?id=<?php echo (int)$reservation['id']; ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
