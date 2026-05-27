<?php
$title = 'Minhas reservas';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_customer();
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->prepare("SELECT r.*, rest.name restaurant_name, COALESCE(o.name, 'Nenhuma') occasion_name FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id LEFT JOIN occasions o ON o.id = r.occasion_id WHERE r.customer_id = ? ORDER BY r.reservation_date DESC, r.reservation_time DESC");
$stmt->execute(array($_SESSION['customer']['id']));
$reservations = $stmt->fetchAll();
?>
<section class="panel">
    <h1>Minhas reservas</h1>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Restaurante</th><th>Data</th><th>Hora</th><th>Pessoas</th><th>Ocasião</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
                <tr>
                    <td><?php echo e($reservation['restaurant_name']); ?></td>
                    <td><?php echo e(date('d/m/Y', strtotime($reservation['reservation_date']))); ?></td>
                    <td><?php echo e(substr($reservation['reservation_time'], 0, 5)); ?></td>
                    <td><?php echo (int)$reservation['party_size']; ?></td>
                    <td><?php echo e($reservation['occasion_name']); ?></td>
                    <td><span class="badge"><?php echo e(reservation_status_label($reservation['status'])); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
