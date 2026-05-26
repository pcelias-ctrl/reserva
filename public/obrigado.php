<?php
$title = 'Reserva enviada';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$whatsappUrl = isset($_SESSION['last_whatsapp_url']) ? $_SESSION['last_whatsapp_url'] : '';
if (!$whatsappUrl && !empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT r.*, COALESCE(o.name, 'Nenhuma') AS occasion_name, rest.name restaurant_name, rest.whatsapp restaurant_whatsapp FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id LEFT JOIN occasions o ON o.id = r.occasion_id WHERE r.id = ?");
    $stmt->execute(array((int)$_GET['id']));
    $reservation = $stmt->fetch();
    if ($reservation) {
        $whatsappUrl = build_whatsapp_url($reservation['restaurant_whatsapp'], reservation_whatsapp_message($reservation));
    }
}
?>
<section class="panel centered">
    <p class="eyebrow">Tudo certo</p>
    <h1>Recebemos sua reserva.</h1>
    <p>Ela entrou como pendente. Envie tambem os dados pelo WhatsApp do restaurante para agilizar o atendimento.</p>
    <?php if ($whatsappUrl): ?>
        <a class="button whatsapp" href="<?php echo e($whatsappUrl); ?>" target="_blank" rel="noopener">Enviar dados no WhatsApp</a>
    <?php endif; ?>
    <a class="button primary" href="index.php">Fazer outra reserva</a>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
