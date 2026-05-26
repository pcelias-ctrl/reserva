<?php

require_once __DIR__ . '/../config/db.php';

$email = getenv('ADMIN_EMAIL') ?: 'admin@reserva.local';
$password = getenv('ADMIN_PASSWORD') ?: 'admin123';

$stmt = $pdo->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
$stmt->execute(array($email));
$admin = $stmt->fetch();

if (!$admin) {
    echo "admin_missing\n";
    exit(1);
}

echo password_verify($password, $admin['password_hash']) ? "admin_login_valid\n" : "admin_login_invalid\n";
