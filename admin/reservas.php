<?php
$title = 'Reservas';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    $allowed = array('pending','approved','confirmed','seated','completed','cancelled','no_show');
    if (in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?');
        $stmt->execute(array($status, $id));
        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
        $stmt->execute(array($id));
        $reservation = $stmt->fetch();
        if ($reservation) {
            send_reservation_email($reservation['customer_email'], 'Atualizacao da sua reserva', 'Sua reserva agora esta com status: ' . $status);
        }
        flash('success', 'Reserva atualizada.');
    }
    redirect_to('reservas.php');
}

require_once __DIR__ . '/../includes/header.php';

$filter = isset($_GET['status']) ? $_GET['status'] : '';
$sql = "SELECT r.*, rest.name restaurant_name, rest.whatsapp restaurant_whatsapp, COALESCE(o.name, 'Nenhuma') occasion_name, COALESCE(e.name, 'Sem preferencia') environment_name, COALESCE(t.label, '-') table_label
        FROM reservations r
        INNER JOIN restaurants rest ON rest.id = r.restaurant_id
        LEFT JOIN occasions o ON o.id = r.occasion_id
        LEFT JOIN environments e ON e.id = r.environment_id
        LEFT JOIN tables_map t ON t.id = r.table_id";
$params = array();
if ($filter) {
    $sql .= ' WHERE r.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY r.reservation_date DESC, r.reservation_time DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();
?>
<section class="panel">
    <div class="section-title">
        <h1>Reservas</h1>
        <div class="filters">
            <a href="reservas.php">Todas</a>
            <a href="reservas.php?status=pending">Pendentes</a>
            <a href="reservas.php?status=confirmed">Confirmadas</a>
        </div>
    </div>
    <div class="reservation-list">
        <?php foreach ($reservations as $reservation): ?>
            <article class="reservation-card">
                <div>
                    <h2><?php echo e($reservation['customer_name']); ?></h2>
                    <p><?php echo e(date('d/m/Y', strtotime($reservation['reservation_date']))); ?> as <?php echo e(substr($reservation['reservation_time'], 0, 5)); ?> · <?php echo (int)$reservation['party_size']; ?> pessoas</p>
                    <p><?php echo e($reservation['customer_email']); ?> · <?php echo e($reservation['customer_phone']); ?></p>
                    <p><?php echo e($reservation['restaurant_name']); ?> · <?php echo e($reservation['environment_name']); ?> · mesa <?php echo e($reservation['table_label']); ?> · <?php echo e($reservation['occasion_name']); ?></p>
                    <?php if ($reservation['dietary_restrictions']): ?><p><strong>Restricoes:</strong> <?php echo e($reservation['dietary_restrictions']); ?></p><?php endif; ?>
                    <?php if ($reservation['notes']): ?><p><strong>Observacoes:</strong> <?php echo e($reservation['notes']); ?></p><?php endif; ?>
                </div>
                <form method="post" class="status-form">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$reservation['id']; ?>">
                    <select name="status">
                        <?php foreach (array('pending','approved','confirmed','seated','completed','cancelled','no_show') as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $reservation['status'] === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button primary" type="submit">Salvar</button>
                    <a class="button whatsapp" target="_blank" rel="noopener" href="<?php echo e(build_whatsapp_url($reservation['restaurant_whatsapp'], reservation_whatsapp_message($reservation))); ?>">WhatsApp</a>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
