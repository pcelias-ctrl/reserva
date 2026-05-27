<?php
require_once __DIR__ . '/functions.php';
$title = isset($title) ? $title : 'i_Reserva';
$isAdmin = isset($isAdmin) ? $isAdmin : false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?></title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="<?php echo $isAdmin ? 'admin-shell' : 'public-shell'; ?>">
<header class="topbar">
    <a class="brand" href="<?php echo $isAdmin ? 'index.php' : 'index.php'; ?>">i_Reserva</a>
    <nav>
        <?php if ($isAdmin): ?>
            <a href="index.php">Cockpit</a>
            <a href="reservas.php">Reservas</a>
            <a href="restaurantes.php">Restaurantes</a>
            <a href="configuracoes.php">Configuracoes</a>
            <a href="logout.php">Sair</a>
        <?php else: ?>
            <a href="index.php">Reservar</a>
            <?php if (current_customer()): ?>
                <a href="minhas-reservas.php">Minhas reservas</a>
                <a href="logout.php">Sair</a>
            <?php else: ?>
                <a href="login.php">Entrar</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
</header>
<main class="page">
<?php if ($msg = flash('success')): ?>
    <div class="alert success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert error"><?php echo e($msg); ?></div>
<?php endif; ?>
