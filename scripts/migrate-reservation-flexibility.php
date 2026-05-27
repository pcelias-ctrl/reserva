<?php

require_once __DIR__ . '/../config/db.php';

function reservation_flex_column_exists($pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(array($table, $column));
    return (int)$stmt->fetch()['total'] > 0;
}

function reservation_flex_table_exists($pdo, $table)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(array($table));
    return (int)$stmt->fetch()['total'] > 0;
}

function reservation_flex_index_exists($pdo, $table, $index)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute(array($table, $index));
    return (int)$stmt->fetch()['total'] > 0;
}

function reservation_flex_column_nullable($pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        'SELECT IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(array($table, $column));
    $row = $stmt->fetch();
    return $row && $row['IS_NULLABLE'] === 'YES';
}

$pdo->exec('ALTER TABLE reservations MODIFY customer_email VARCHAR(160) NULL');

if (!reservation_flex_table_exists($pdo, 'occupancy_extra_tables')) {
    $pdo->exec(
        "CREATE TABLE occupancy_extra_tables (
          id INT AUTO_INCREMENT PRIMARY KEY,
          layout_date DATE NOT NULL,
          environment_id INT NOT NULL,
          label VARCHAR(40) NOT NULL,
          shape ENUM('square','round') NOT NULL DEFAULT 'square',
          seats INT NOT NULL DEFAULT 2,
          position_x INT NOT NULL DEFAULT 60,
          position_y INT NOT NULL DEFAULT 60,
          source_table_id INT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          CONSTRAINT fk_extra_table_environment FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE,
          CONSTRAINT fk_extra_table_source FOREIGN KEY (source_table_id) REFERENCES tables_map(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

if (!reservation_flex_column_nullable($pdo, 'occupancy_assignments', 'table_id')) {
    try {
        $pdo->exec('ALTER TABLE occupancy_assignments DROP FOREIGN KEY fk_occupancy_assignment_table');
    } catch (Exception $e) {
    }
    $pdo->exec('ALTER TABLE occupancy_assignments MODIFY table_id INT NULL');
    try {
        $pdo->exec('ALTER TABLE occupancy_assignments ADD CONSTRAINT fk_occupancy_assignment_table FOREIGN KEY (table_id) REFERENCES tables_map(id) ON DELETE CASCADE');
    } catch (Exception $e) {
    }
}

if (!reservation_flex_column_exists($pdo, 'occupancy_assignments', 'extra_table_id')) {
    $pdo->exec('ALTER TABLE occupancy_assignments ADD extra_table_id INT NULL AFTER table_id');
    $pdo->exec('ALTER TABLE occupancy_assignments ADD KEY idx_occupancy_extra_table (extra_table_id)');
    $pdo->exec('ALTER TABLE occupancy_assignments ADD CONSTRAINT fk_occupancy_assignment_extra_table FOREIGN KEY (extra_table_id) REFERENCES occupancy_extra_tables(id) ON DELETE CASCADE');
}

if (!reservation_flex_index_exists($pdo, 'occupancy_assignments', 'uniq_occupancy_extra_assignment')) {
    $pdo->exec('ALTER TABLE occupancy_assignments ADD UNIQUE KEY uniq_occupancy_extra_assignment (layout_date, extra_table_id)');
}

echo "Migração de reservas flexíveis concluída.\n";
