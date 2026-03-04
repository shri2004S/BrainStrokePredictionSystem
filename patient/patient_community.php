<?php
// patient_community.php
include 'db_conn.php';
session_start();

// 🧑‍⚕️ For testing: replace with session data later
$patient_id = 1;
$patient_name = "Patient 1";

// ✅ Handle new group message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== '') {
        $stmt = $conn->prepare("INSERT INTO group_chat (patient_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $patient_id, $msg);
        $stmt->execute();
    }
    exit;
}

// ✅ Fetch all messages for display (latest first)
if (isset($_GET['load'])) {
    $res = mysqli_query($conn, "
        SELECT g.*, p.name AS sender_name 
        FROM group_chat g 
        JOIN patients p ON g.patient_id = p.id 
        ORDER BY g.created_at ASC
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        $isMe = ($row['patient_id'] == $patient_id);
        echo '<div class="msg '.($isMe ? 'me' : 'other').'">
                <div class="sender">'.htmlspecialchars($row['sender_name']).'</div>
                <div class="bubble">'.htmlspecialchars($row['message']).'</div>
                <small class="time">'.htmlspecialchars($row['created_at']).'</small>
              </div>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Community Group Chat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: #ece5dd;
  font-family: 'Segoe UI', sans-serif;
}
.chat-container {
  max-width: 600px;
  margin: 30px auto;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}
.chat-header {
  background: #25D366;
  color: white;
  padding: 15px;
  font-weight: 600;
}
.chat-box {
  height: 65vh;
  overflow-y: auto;
  padding: 15px;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
}
.msg {
  margin-bottom: 10px;
  display: flex;
  flex-direction: column;
  max-width: 80%;
}
.msg.me {
  align-self: flex-end;
}
.msg.other {
  align-self: flex-start;
}
.sender {
  font-size: 12px;
  color: #555;
  margin-bottom: 2px;
}
.bubble {
  padding: 10px 14px;
  border-radius: 15px;
  background: #dcf8c6;
  display: inline-block;
}
.msg.other .bubble {
  background: #fff;
  border: 1px solid #ddd;
}
.time {
  font-size: 10px;
  color: #999;
  margin-top: 2px;
}
.chat-input {
  display: flex;
  border-top: 1px solid #ddd;
  background: #fff;
}
.chat-input input {
  flex: 1;
  border: none;
  padding: 12px;
  outline: none;
}
.chat-input button {
  background: #25D366;
  border: none;
  color: white;
  padding: 0 20px;
  font-weight: 600;
}
</style>
</head>
<body>

<div class="chat-container">
  <div class="chat-header">💬 Patient Community Group</div>
  <div class="chat-box" id="chatBox"></div>

  <form class="chat-input" id="chatForm">
    <input type="text" id="message" placeholder="Type a message..." autocomplete="off">
    <button type="submit">Send</button>
  </form>
</div>

<script>
const chatBox = document.getElementById('chatBox');
const chatForm = document.getElementById('chatForm');
const messageInput = document.getElementById('message');

// 🕒 Load chat every 2 seconds
function loadChat() {
  fetch('patient_community.php?load=1')
    .then(res => res.text())
    .then(html => {
      chatBox.innerHTML = html;
      chatBox.scrollTop = chatBox.scrollHeight;
    });
}
setInterval(loadChat, 2000);
loadChat();

// 📩 Send new message
chatForm.addEventListener('submit', e => {
  e.preventDefault();
  const msg = messageInput.value.trim();
  if (!msg) return;
  const formData = new FormData();
  formData.append('message', msg);
  fetch('patient_community.php', { method: 'POST', body: formData })
    .then(() => {
      messageInput.value = '';
      loadChat();
    });
});
</script>
</body>
</html>
