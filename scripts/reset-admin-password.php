<?php

require_once __DIR__ . '/../config/db.php';

$email = getenv('ADMIN_EMAIL') ?: 'admin@reserva.local';
$password = getenv('ADMIN_PASSWORD') ?: 'admin123';
$name = getenv('ADMIN_NAME') ?: 'Administrador';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$stmt->execute(array($email));
$admin = $stmt->fetch();

if ($admin) {
    $stmt = $pdo->prepare('UPDATE admins SET name = ?, password_hash = ? WHERE email = ?');
    $stmt->execute(array($name, $hash, $email));
} else {
    $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute(array($name, $email, $hash));
}

echo "Administrador atualizado: {$email}\n";

if ($email !== 'admin@admin.com') {
    $alternateHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
    $stmt->execute(array('admin@admin.com'));
    $alternate = $stmt->fetch();

    if ($alternate) {
        $stmt = $pdo->prepare('UPDATE admins SET name = ?, password_hash = ? WHERE email = ?');
        $stmt->execute(array('Administrador', $alternateHash, 'admin@admin.com'));
    } else {
        $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute(array('Administrador', 'admin@admin.com', $alternateHash));
    }

    echo "Administrador alternativo atualizado: admin@admin.com\n";
}
