<?php
$title = 'Manutenção';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$admin = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'delete_reservations') {
        $ids = isset($_POST['reservation_ids']) && is_array($_POST['reservation_ids']) ? array_map('intval', $_POST['reservation_ids']) : array();
        $ids = array_values(array_filter($ids));
        if (!$ids) {
            flash('error', 'Selecione pelo menos uma reserva para excluir.');
            redirect_to('manutencao.php');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('DELETE FROM reservations WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        flash('success', count($ids) . ' reserva(s) excluída(s).');
        redirect_to('manutencao.php');
    }

    if ($action === 'change_password') {
        $email = trim($_POST['email']);
        $currentPassword = trim($_POST['current_password']);
        $newPassword = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);

        if ($newPassword === '' || strlen($newPassword) < 6) {
            flash('error', 'A nova senha precisa ter pelo menos 6 caracteres.');
            redirect_to('manutencao.php');
        }
        if ($newPassword !== $confirmPassword) {
            flash('error', 'A confirmação da senha não confere.');
            redirect_to('manutencao.php');
        }

        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
        $stmt->execute(array($email));
        $existing = $stmt->fetch();
        $envEmail = getenv('ADMIN_EMAIL') ?: 'admin@admin.com';
        $envPassword = getenv('ADMIN_PASSWORD') ?: 'admin123';
        $currentOk = $existing
            ? password_verify($currentPassword, $existing['password_hash'])
            : (strcasecmp($email, $envEmail) === 0 && hash_equals($envPassword, $currentPassword));

        if (!$currentOk) {
            flash('error', 'Senha atual inválida.');
            redirect_to('manutencao.php');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $stmt->execute(array($hash, (int)$existing['id']));
            $adminId = (int)$existing['id'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute(array('Administrador', $email, $hash));
            $adminId = (int)$pdo->lastInsertId();
        }

        $_SESSION['admin'] = array('id' => $adminId, 'name' => 'Administrador', 'email' => $email);
        flash('success', 'Senha administrativa alterada.');
        redirect_to('manutencao.php');
    }
}

require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT r.*, rest.name restaurant_name FROM reservations r INNER JOIN restaurants rest ON rest.id = r.restaurant_id ORDER BY r.created_at DESC, r.id DESC LIMIT 80");
$reservations = $stmt->fetchAll();
?>
<section class="dashboard-hero compact-hero">
    <div>
        <p class="eyebrow">Manutenção</p>
        <h1>Limpeza de testes e segurança administrativa.</h1>
    </div>
</section>

<section class="settings-grid">
    <div class="panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Reservas de teste</p>
                <h2>Excluir reservas selecionadas</h2>
            </div>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="delete_reservations">
            <div class="maintenance-list">
                <?php foreach ($reservations as $reservation): ?>
                    <label class="maintenance-row">
                        <input type="checkbox" name="reservation_ids[]" value="<?php echo (int)$reservation['id']; ?>">
                        <span>
                            <strong>#<?php echo (int)$reservation['id']; ?> · <?php echo e($reservation['customer_name']); ?></strong>
                            <em><?php echo e($reservation['restaurant_name']); ?> · <?php echo e(date('d/m/Y', strtotime($reservation['reservation_date']))); ?> às <?php echo e(substr($reservation['reservation_time'], 0, 5)); ?> · <?php echo e(reservation_status_label($reservation['status'])); ?></em>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="button danger" type="submit" onclick="return confirm('Excluir as reservas selecionadas? Esta ação não pode ser desfeita.');">Excluir selecionadas</button>
        </form>
    </div>

    <div class="panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Acesso</p>
                <h2>Alterar senha administrativa</h2>
            </div>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="change_password">
            <label>E-mail administrativo
                <input type="email" name="email" required value="<?php echo e($admin ? $admin['email'] : 'admin@admin.com'); ?>">
            </label>
            <label>Senha atual
                <input type="password" name="current_password" required>
            </label>
            <label>Nova senha
                <input type="password" name="new_password" required minlength="6">
            </label>
            <label>Confirmar nova senha
                <input type="password" name="confirm_password" required minlength="6">
            </label>
            <button class="button primary" type="submit">Alterar senha</button>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
