<?php
session_start();
require_once '../db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT id, name, email, status 
    FROM users 
    WHERE id != ?
    ORDER BY status DESC, name ASC
");

$stmt->bind_param("i", $current_user_id);
$stmt->execute();

$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'users' => $users]);
?>