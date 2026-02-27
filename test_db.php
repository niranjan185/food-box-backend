<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
// If we reached here, DB connection succeeded
$host = $conn->host_info ?? 'connected';
echo json_encode(['ok' => true, 'host' => $host]);
