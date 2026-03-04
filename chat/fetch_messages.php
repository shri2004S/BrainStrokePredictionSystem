<?php
session_start();
require_once '../db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$current_user_id   = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

$receiver_id = intval($_GET['receiver_id'] ?? 0);
$receiver_role = $_GET['receiver_role'] ?? '';

if ($receiver_id <= 0 || empty($receiver_role)) {
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit();
}

$stmt = $conn->prepare("
    SELECT *
    FROM chat_messages
    WHERE 
        (sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?)
        OR
        (sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?)
    ORDER BY created_at ASC
");

$stmt->bind_param(
    "isisisis",
    $current_user_id, $current_user_role, $receiver_id, $receiver_role,
    $receiver_id, $receiver_role, $current_user_id, $current_user_role
);

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $row['is_sender'] = ($row['sender_id'] == $current_user_id);
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => $messages]);
?>