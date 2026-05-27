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
    $allowed = array('pending','confirmed','cancelled','completed','no_show');
    if (in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?');
        $stmt->execute(array($status, $id));
        $stmt = $pdo->prepare('SELECT r.*, rest.name restaurant_name, rest.smtp_enabled, rest.smtp_host, rest.smtp_port, rest.smtp_username, rest.smtp_password, rest.smtp_encryption, rest.smtp_from_email, rest.smtp_from_name FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id WHERE r.id = ?');
        $stmt->execute(array($id));
        $reservation = $stmt->fetch();
        if ($reservation) {
            send_reservation_email($reservation['customer_email'], 'Atualização da sua reserva', reservation_status_email_message($reservation, $status), $reservation);
        }
        flash('success', 'Reserva atualizada.');
    }
    redirect_to('reservas.php');
}

require_once __DIR__ . '/../includes/header.php';

$filter = isset($_GET['status']) ? $_GET['status'] : '';
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT r.*, rest.name restaurant_name, rest.whatsapp restaurant_whatsapp, COALESCE(o.name, 'Nenhuma') occasion_name, COALESCE(e.name, 'Sem preferência') environment_name, COALESCE(t.label, '-') table_label
        FROM reservations r
        INNER JOIN restaurants rest ON rest.id = r.restaurant_id
        LEFT JOIN occasions o ON o.id = r.occasion_id
        LEFT JOIN environments e ON e.id = r.environment_id
        LEFT JOIN tables_map t ON t.id = r.table_id";
$params = array();
if ($reservationId) {
    $sql .= ' WHERE r.id = ?';
    $params[] = $reservationId;
} elseif ($filter) {
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
            <a href="reservas.php?status=pending">Aguardando aprovação</a>
            <a href="reservas.php?status=confirmed">Confirmadas</a>
            <a href="reservas.php?status=cancelled">Canceladas</a>
            <a href="reservas.php?status=completed">Concluídas</a>
            <a href="reservas.php?status=no_show">Não compareceu</a>
            <?php if ($reservationId): ?><a href="index.php">Voltar ao painel</a><?php endif; ?>
        </div>
    </div>
    <div class="reservation-list">
        <?php foreach ($reservations as $reservation): ?>
            <article class="reservation-card">
                <div>
                    <h2><?php echo e($reservation['customer_name']); ?></h2>
                    <p><?php echo e(date('d/m/Y', strtotime($reservation['reservation_date']))); ?> às <?php echo e(substr($reservation['reservation_time'], 0, 5)); ?> · <?php echo (int)$reservation['party_size']; ?> pessoas</p>
                    <p><?php echo e($reservation['customer_email']); ?> · <?php echo e($reservation['customer_phone']); ?></p>
                    <p><?php echo e($reservation['restaurant_name']); ?> · <?php echo e($reservation['environment_name']); ?> · mesa <?php echo e($reservation['table_label']); ?> · <?php echo e($reservation['occasion_name']); ?></p>
                    <?php if ($reservation['dietary_restrictions']): ?><p><strong>Restrições:</strong> <?php echo e($reservation['dietary_restrictions']); ?></p><?php endif; ?>
                    <?php if ($reservation['notes']): ?><p><strong>Observações:</strong> <?php echo e($reservation['notes']); ?></p><?php endif; ?>
                    <?php if (!empty($reservation['feedback_comment']) || !empty($reservation['feedback_rating'])): ?>
                        <div class="feedback-summary">
                            <strong>Feedback do cliente</strong>
                            <?php if (!empty($reservation['feedback_rating'])): ?><span>Nota: <?php echo (int)$reservation['feedback_rating']; ?>/5</span><?php endif; ?>
                            <?php if (!empty($reservation['feedback_comment'])): ?><p><?php echo nl2br(e($reservation['feedback_comment'])); ?></p><?php endif; ?>
                            <?php if (!empty($reservation['feedback_submitted_at'])): ?><small>Enviado em <?php echo e(date('d/m/Y H:i', strtotime($reservation['feedback_submitted_at']))); ?></small><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <form method="post" class="status-form">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$reservation['id']; ?>">
                    <select name="status">
                        <?php foreach (array('pending','confirmed','cancelled','completed','no_show') as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $reservation['status'] === $status ? 'selected' : ''; ?>><?php echo e(reservation_status_label($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button primary" type="submit">Salvar ação</button>
                    <a class="button whatsapp" target="_blank" rel="noopener" href="<?php echo e(build_whatsapp_url($reservation['restaurant_whatsapp'], reservation_whatsapp_message($reservation))); ?>">WhatsApp</a>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
