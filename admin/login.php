<?php
$title = 'Admin';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute(array($email));
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin'] = array('id' => (int)$admin['id'], 'name' => $admin['name'], 'email' => $admin['email']);
        redirect_to('index.php');
    }

    $envAdminEmail = getenv('ADMIN_EMAIL') ?: 'admin@admin.com';
    $envAdminPassword = getenv('ADMIN_PASSWORD') ?: 'admin123';
    if (!$admin && strcasecmp($email, $envAdminEmail) === 0 && hash_equals($envAdminPassword, $password)) {
        $_SESSION['admin'] = array('id' => 0, 'name' => 'Administrador', 'email' => $envAdminEmail);
        redirect_to('index.php');
    }
    flash('error', 'E-mail ou senha inválidos.');
    redirect_to('login.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel auth-card">
    <h1>Painel administrativo</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Senha <input type="password" name="password" required></label>
        <button class="button primary" type="submit">Entrar</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
