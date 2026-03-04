<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
    
    if (empty($message)) {
        echo json_encode(['status' => 'error', 'msg' => 'Message is empty']);
        exit;
    }
    
    if ($doctor_id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid doctor']);
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Please login first']);
        exit;
    }
    
    $sender_id = intval($_SESSION['user_id']);
    $sender_role = 'patient';
    
    $sql = "INSERT INTO chat_messages (sender_role, sender_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'msg' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("siis", $sender_role, $sender_id, $doctor_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'msg' => 'Message sent']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Failed: ' . $stmt->error]);
    }
    
    $stmt->close();
}
?>