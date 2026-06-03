<?php

require_once __DIR__ . '/../config/db.php';

function special_days_table_exists($pdo, $table)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(array($table));
    return (int)$stmt->fetch()['total'] > 0;
}

if (!special_days_table_exists($pdo, 'restaurant_special_days')) {
    $pdo->exec(
        "CREATE TABLE restaurant_special_days (
          id INT AUTO_INCREMENT PRIMARY KEY,
          restaurant_id INT NOT NULL,
          special_date DATE NOT NULL,
          name VARCHAR(160) NOT NULL,
          status ENUM('active','inactive') NOT NULL DEFAULT 'active',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_special_day (restaurant_id, special_date),
          CONSTRAINT fk_special_day_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

if (!special_days_table_exists($pdo, 'restaurant_special_times')) {
    $pdo->exec(
        "CREATE TABLE restaurant_special_times (
          id INT AUTO_INCREMENT PRIMARY KEY,
          special_day_id INT NOT NULL,
          period ENUM('lunch','dinner') NOT NULL DEFAULT 'dinner',
          reservation_time TIME NOT NULL,
          status ENUM('active','inactive') NOT NULL DEFAULT 'active',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_special_time (special_day_id, reservation_time),
          CONSTRAINT fk_special_time_day FOREIGN KEY (special_day_id) REFERENCES restaurant_special_days(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

echo "Migração de dias especiais concluída.\n";
