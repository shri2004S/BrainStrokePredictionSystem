<?php
require 'db_conn.php';

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("INSERT INTO chat_messages (sender_role, message) VALUES (?, ?)");
$stmt->bind_param("ss", $data['sender'], $data['message']);
$stmt->execute();
?>
