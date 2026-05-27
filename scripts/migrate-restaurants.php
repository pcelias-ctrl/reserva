<?php

require_once __DIR__ . '/../config/db.php';

function column_exists($pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(array($table, $column));
    return (int)$stmt->fetch()['total'] > 0;
}

function table_exists($pdo, $table)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(array($table));
    return (int)$stmt->fetch()['total'] > 0;
}

if (!table_exists($pdo, 'restaurants')) {
    $pdo->exec(
        "CREATE TABLE restaurants (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(180) NOT NULL,
          legal_name VARCHAR(180) NULL,
          document_number VARCHAR(40) NULL,
          email VARCHAR(160) NULL,
          phone VARCHAR(40) NULL,
          whatsapp VARCHAR(40) NOT NULL,
          logo_url VARCHAR(500) NULL,
          logo_mime VARCHAR(80) NULL,
          logo_data MEDIUMBLOB NULL,
          address TEXT NULL,
          reservation_message TEXT NULL,
          status ENUM('active','inactive') NOT NULL DEFAULT 'active',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

if (!column_exists($pdo, 'restaurants', 'logo_mime')) {
    $pdo->exec('ALTER TABLE restaurants ADD logo_mime VARCHAR(80) NULL AFTER logo_url');
}

if (!column_exists($pdo, 'restaurants', 'logo_data')) {
    $pdo->exec('ALTER TABLE restaurants ADD logo_data MEDIUMBLOB NULL AFTER logo_mime');
}

$stmt = $pdo->query('SELECT COUNT(*) total FROM restaurants');
if ((int)$stmt->fetch()['total'] === 0) {
    $stmt = $pdo->prepare(
        'INSERT INTO restaurants (name, legal_name, email, phone, whatsapp, logo_url, address, reservation_message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute(array(
        'Restaurante Demo',
        'Restaurante Demo Ltda',
        'reservas@restaurantedemo.com',
        '(11) 99999-9999',
        '5511999999999',
        '',
        'Rua das Reservas, 100',
        'Nova reserva recebida pelo Reserva On-line.'
    ));
}

$restaurantId = (int)$pdo->query('SELECT id FROM restaurants ORDER BY id LIMIT 1')->fetch()['id'];

if (!column_exists($pdo, 'environments', 'restaurant_id')) {
    $pdo->exec('ALTER TABLE environments ADD restaurant_id INT NULL AFTER id');
    $stmt = $pdo->prepare('UPDATE environments SET restaurant_id = ? WHERE restaurant_id IS NULL');
    $stmt->execute(array($restaurantId));
    $pdo->exec('ALTER TABLE environments MODIFY restaurant_id INT NOT NULL');
    $pdo->exec('ALTER TABLE environments ADD CONSTRAINT fk_environment_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE');
}

if (!column_exists($pdo, 'reservations', 'restaurant_id')) {
    $pdo->exec('ALTER TABLE reservations ADD restaurant_id INT NULL AFTER id');
    $stmt = $pdo->prepare('UPDATE reservations SET restaurant_id = ? WHERE restaurant_id IS NULL');
    $stmt->execute(array($restaurantId));
    $pdo->exec('ALTER TABLE reservations MODIFY restaurant_id INT NOT NULL');
    $pdo->exec('ALTER TABLE reservations ADD CONSTRAINT fk_reservation_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE');
}

echo "Migracao multi-restaurante concluida.\n";
