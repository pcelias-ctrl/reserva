<?php
$title = 'Entrar';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE email = ?');
    $stmt->execute(array(trim($_POST['email'])));
    $customer = $stmt->fetch();

    if ($customer && $customer['password_hash'] && password_verify($_POST['password'], $customer['password_hash'])) {
        $_SESSION['customer'] = array(
            'id' => (int)$customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone']
        );
        redirect_to('minhas-reservas.php');
    }
    flash('error', 'E-mail ou senha inválidos.');
    redirect_to('login.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel auth-card">
    <h1>Entrar</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Senha <input type="password" name="password" required></label>
        <button class="button primary" type="submit">Acessar</button>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
