<?php

require_once __DIR__ . '/../config/db.php';

$sqlFile = __DIR__ . '/../sql/database.sql';
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "Arquivo SQL nao encontrado: {$sqlFile}\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if ($statement === '' || stripos($statement, 'CREATE DATABASE') === 0 || stripos($statement, 'USE ') === 0) {
        continue;
    }
    $pdo->exec($statement);
}

echo "Banco inicializado com sucesso.\n";
