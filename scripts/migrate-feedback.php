<?php

require_once __DIR__ . '/../config/db.php';

function reservation_column_exists($pdo, $column)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(array('reservations', $column));
    return (int)$stmt->fetch()['total'] > 0;
}

if (!reservation_column_exists($pdo, 'feedback_rating')) {
    $pdo->exec('ALTER TABLE reservations ADD feedback_rating TINYINT NULL AFTER lgpd_share_consent');
}

if (!reservation_column_exists($pdo, 'feedback_comment')) {
    $pdo->exec('ALTER TABLE reservations ADD feedback_comment TEXT NULL AFTER feedback_rating');
}

if (!reservation_column_exists($pdo, 'feedback_submitted_at')) {
    $pdo->exec('ALTER TABLE reservations ADD feedback_submitted_at TIMESTAMP NULL DEFAULT NULL AFTER feedback_comment');
}

echo "Migração de feedback concluída.\n";
