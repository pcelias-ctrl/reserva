<?php

require_once __DIR__ . '/../config/db.php';

function occupancy_table_exists($pdo, $table)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(array($table));
    return (int)$stmt->fetch()['total'] > 0;
}

if (!occupancy_table_exists($pdo, 'occupancy_layouts')) {
    $pdo->exec(
        "CREATE TABLE occupancy_layouts (
          id INT AUTO_INCREMENT PRIMARY KEY,
          layout_date DATE NOT NULL,
          environment_id INT NOT NULL,
          table_id INT NOT NULL,
          position_x INT NOT NULL DEFAULT 40,
          position_y INT NOT NULL DEFAULT 40,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_occupancy_layout (layout_date, environment_id, table_id),
          CONSTRAINT fk_occupancy_layout_environment FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE,
          CONSTRAINT fk_occupancy_layout_table FOREIGN KEY (table_id) REFERENCES tables_map(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

if (!occupancy_table_exists($pdo, 'occupancy_assignments')) {
    $pdo->exec(
        "CREATE TABLE occupancy_assignments (
          id INT AUTO_INCREMENT PRIMARY KEY,
          layout_date DATE NOT NULL,
          environment_id INT NOT NULL,
          reservation_id INT NOT NULL,
          table_id INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_occupancy_assignment (layout_date, table_id),
          KEY idx_occupancy_reservation (reservation_id, layout_date),
          CONSTRAINT fk_occupancy_assignment_environment FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE,
          CONSTRAINT fk_occupancy_assignment_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
          CONSTRAINT fk_occupancy_assignment_table FOREIGN KEY (table_id) REFERENCES tables_map(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

echo "Migração de ocupação concluída.\n";
