<?php
$title = 'Minhas reservas';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_feedback') {
        $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        $rating = isset($_POST['feedback_rating']) && $_POST['feedback_rating'] !== '' ? (int)$_POST['feedback_rating'] : null;
        $comment = trim(isset($_POST['feedback_comment']) ? $_POST['feedback_comment'] : '');

        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            flash('error', 'Escolha uma nota entre 1 e 5.');
            redirect_to('minhas-reservas.php');
        }

        if ($comment === '') {
            flash('error', 'Conte um pouco sobre a sua experiência antes de enviar.');
            redirect_to('minhas-reservas.php');
        }

        $stmt = $pdo->prepare('SELECT id, status FROM reservations WHERE id = ? AND customer_id = ?');
        $stmt->execute(array($reservationId, $_SESSION['customer']['id']));
        $reservation = $stmt->fetch();

        if (!$reservation || !in_array($reservation['status'], array('completed', 'no_show'), true)) {
            flash('error', 'Não foi possível registrar feedback para esta reserva.');
            redirect_to('minhas-reservas.php');
        }

        $stmt = $pdo->prepare('UPDATE reservations SET feedback_rating = ?, feedback_comment = ?, feedback_submitted_at = NOW() WHERE id = ? AND customer_id = ?');
        $stmt->execute(array($rating, $comment, $reservationId, $_SESSION['customer']['id']));
        flash('success', 'Obrigado. Seu feedback foi enviado para o restaurante.');
    }

    redirect_to('minhas-reservas.php');
}

require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->prepare("SELECT r.*, rest.name restaurant_name, COALESCE(o.name, 'Nenhuma') occasion_name FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id LEFT JOIN occasions o ON o.id = r.occasion_id WHERE r.customer_id = ? ORDER BY r.reservation_date DESC, r.reservation_time DESC");
$stmt->execute(array($_SESSION['customer']['id']));
$reservations = $stmt->fetchAll();
?>
<section class="panel">
    <div class="section-title">
        <div>
            <p class="eyebrow">Área do cliente</p>
            <h1>Minhas reservas</h1>
        </div>
    </div>

    <div class="reservation-list customer-reservations">
        <?php if (!$reservations): ?>
            <div class="empty-state">
                <strong>Nenhuma reserva encontrada.</strong>
                <p>Quando você fizer uma reserva, ela aparecerá aqui com o histórico e as próximas ações.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($reservations as $reservation): ?>
            <?php
            $canFeedback = in_array($reservation['status'], array('completed', 'no_show'), true);
            $isNoShow = $reservation['status'] === 'no_show';
            ?>
            <article class="reservation-card customer-reservation-card">
                <div class="reservation-card-main">
                    <div class="reservation-card-title">
                        <div>
                            <h2><?php echo e($reservation['restaurant_name']); ?></h2>
                            <p><?php echo e(date('d/m/Y', strtotime($reservation['reservation_date']))); ?> às <?php echo e(substr($reservation['reservation_time'], 0, 5)); ?> · <?php echo (int)$reservation['party_size']; ?> pessoas</p>
                        </div>
                        <span class="badge"><?php echo e(reservation_status_label($reservation['status'])); ?></span>
                    </div>
                    <p>Ocasião: <?php echo e($reservation['occasion_name']); ?></p>
                    <?php if ($reservation['notes']): ?><p>Observações: <?php echo e($reservation['notes']); ?></p><?php endif; ?>
                </div>

                <?php if ($canFeedback): ?>
                    <form method="post" class="feedback-box">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="save_feedback">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">

                        <div>
                            <h3><?php echo $isNoShow ? 'Podemos entender o que aconteceu?' : 'Como foi sua experiência?'; ?></h3>
                            <p>
                                <?php if ($isNoShow): ?>
                                    Notamos que você não veio na data escolhida. Quer agendar uma nova data? Conte o que aconteceu para o restaurante cuidar melhor do próximo contato.
                                <?php else: ?>
                                    Deu tudo certo? Conte para o restaurante como foi a visita e o que pode melhorar.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="grid two">
                            <label>Nota
                                <select name="feedback_rating">
                                    <option value="">Sem nota</option>
                                    <?php for ($score = 5; $score >= 1; $score--): ?>
                                        <option value="<?php echo $score; ?>" <?php echo (int)$reservation['feedback_rating'] === $score ? 'selected' : ''; ?>><?php echo $score; ?> de 5</option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label>Como podemos melhorar?
                                <textarea name="feedback_comment" rows="4" required><?php echo e($reservation['feedback_comment']); ?></textarea>
                            </label>
                        </div>

                        <?php if (!empty($reservation['feedback_submitted_at'])): ?>
                            <p class="feedback-sent">Feedback enviado em <?php echo e(date('d/m/Y H:i', strtotime($reservation['feedback_submitted_at']))); ?>. Você pode atualizar a mensagem se desejar.</p>
                        <?php endif; ?>

                        <button class="button primary" type="submit">Enviar feedback</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
