<?php
session_start();
require_once '../db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$stmt = $conn->prepare("UPDATE users SET status = 'online' WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);

echo json_encode(['success' => $stmt->execute()]);
?>