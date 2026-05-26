<?php
$title = 'Admin';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute(array(trim($_POST['email'])));
    $admin = $stmt->fetch();
    $password = trim($_POST['password']);
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin'] = array('id' => (int)$admin['id'], 'name' => $admin['name'], 'email' => $admin['email']);
        redirect_to('index.php');
    }
    flash('error', 'Email ou senha invalidos.');
    redirect_to('login.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel auth-card">
    <h1>Painel administrativo</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>Email <input type="email" name="email" required></label>
        <label>Senha <input type="password" name="password" required></label>
        <button class="button primary" type="submit">Entrar</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
