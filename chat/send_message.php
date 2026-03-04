<?php
session_start();
require_once '../db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$receiver_id   = intval($data['receiver_id'] ?? 0);
$receiver_role = $data['receiver_role'] ?? '';
$message       = trim($data['message'] ?? '');

if ($receiver_id <= 0 || empty($receiver_role) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO chat_messages 
    (sender_id, sender_role, receiver_id, receiver_role, message)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isiss",
    $_SESSION['user_id'],
    $_SESSION['role'],
    $receiver_id,
    $receiver_role,
    $message
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Message not sent']);
}
?>