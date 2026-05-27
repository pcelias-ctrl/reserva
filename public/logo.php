<?php

require_once __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT logo_mime, logo_data FROM restaurants WHERE id = ? AND logo_data IS NOT NULL');
$stmt->execute(array($id));
$logo = $stmt->fetch();

if (!$logo || empty($logo['logo_data'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $logo['logo_mime']);
header('Cache-Control: public, max-age=3600');
echo $logo['logo_data'];
