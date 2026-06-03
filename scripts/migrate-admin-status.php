<?php

require_once __DIR__ . '/../config/db.php';

function admin_status_column_exists($pdo)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(array('admins', 'status'));
    return (int)$stmt->fetch()['total'] > 0;
}

if (!admin_status_column_exists($pdo)) {
    $pdo->exec("ALTER TABLE admins ADD status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER password_hash");
}

echo "Migração de status dos administradores concluída.\n";
