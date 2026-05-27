<?php

require_once __DIR__ . '/../config/db.php';

function hours_table_exists($pdo)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(array('restaurant_hours'));
    return (int)$stmt->fetch()['total'] > 0;
}

if (!hours_table_exists($pdo)) {
    $pdo->exec(
        "CREATE TABLE restaurant_hours (
          id INT AUTO_INCREMENT PRIMARY KEY,
          restaurant_id INT NOT NULL,
          weekday TINYINT NOT NULL,
          period ENUM('lunch','dinner') NOT NULL,
          opens_at TIME NULL,
          closes_at TIME NULL,
          is_closed TINYINT(1) NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_restaurant_hours (restaurant_id, weekday, period),
          CONSTRAINT fk_hours_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

$countHours = $pdo->query('SELECT COUNT(*) total FROM restaurant_hours')->fetch();
if ((int)$countHours['total'] === 0) {
    $restaurants = $pdo->query('SELECT id FROM restaurants')->fetchAll();
    $stmt = $pdo->prepare('INSERT INTO restaurant_hours (restaurant_id, weekday, period, opens_at, closes_at, is_closed) VALUES (?, ?, ?, ?, ?, 0)');
    foreach ($restaurants as $restaurant) {
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $stmt->execute(array((int)$restaurant['id'], $weekday, 'lunch', '12:00:00', '15:00:00'));
            $stmt->execute(array((int)$restaurant['id'], $weekday, 'dinner', '19:00:00', '23:00:00'));
        }
    }
}

echo "Migração de horários concluída.\n";
