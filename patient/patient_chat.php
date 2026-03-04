<?php
session_start();
require_once __DIR__ . '/../db_conn.php';

// ✅ Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ✅ Validate patient session
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

// ✅ Validate doctor_id parameter
if (!isset($_GET['doctor_id']) || !is_numeric($_GET['doctor_id'])) {
    header("Location: ../chat.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];
$doctor_id = (int)$_GET['doctor_id'];

// ✅ Get doctor details
$stmt = $conn->prepare("SELECT id, name, email, experience, rating, role FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doctor) {
    die("Doctor not found.");
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Dr. <?= htmlspecialchars($doctor['name']) ?> - NeuroNest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        .chat-container { display: flex; flex-direction: column; height: 100vh; max-width: 1200px; margin: 0 auto; background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        
        /* Header */
        .chat-header { background: linear-gradient(135deg, #48bb78, #38a169); color: #fff; padding: 1rem 1.5rem; display: flex; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .back-btn { background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; margin-right: 1rem; text-decoration: none; display: inline-flex; align-items: center; font-size: 14px; transition: background 0.3s; }
        .back-btn:hover { background: rgba(255,255,255,0.3); }
        .doctor-avatar { width: 45px; height: 45px; border-radius: 50%; background: #fff; color: #48bb78; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.3rem; margin-right: 1rem; }
        .doctor-info h2 { font-size: 1.2rem; margin-bottom: 0.25rem; }
        .doctor-info p { font-size: 0.9rem; opacity: 0.9; }
        
        /* Messages Area */
        .messages-container { flex: 1; overflow-y: auto; padding: 1.5rem; background: linear-gradient(to bottom, #e5ddd5 0%, #f0f2f5 100%); }
        .date-divider { text-align: center; margin: 1.5rem 0; }
        .date-divider span { background: rgba(255,255,255,0.9); padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; color: #667781; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        .message { display: flex; margin-bottom: 1rem; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        
        .message-bubble { max-width: 65%; padding: 0.75rem 1rem; border-radius: 12px; position: relative; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .message.sent .message-bubble { background: #dcf8c6; border-bottom-right-radius: 4px; }
        .message.received .message-bubble { background: #fff; border-bottom-left-radius: 4px; }
        
        .message-text { margin-bottom: 0.25rem; color: #111; line-height: 1.4; font-size: 0.95rem; }
        .message-time { font-size: 0.75rem; color: #667781; text-align: right; display: flex; align-items: center; justify-content: flex-end; gap: 0.25rem; }
        .message-status { font-size: 0.75rem; color: #4fc3f7; }
        
        /* Input Area */
        .chat-input-container { background: #f0f2f5; padding: 1rem 1.5rem; display: flex; gap: 0.75rem; border-top: 1px solid #e0e0e0; }
        .chat-input { flex: 1; padding: 0.85rem 1.25rem; border: 2px solid #e0e0e0; border-radius: 25px; font-size: 1rem; outline: none; font-family: inherit; transition: border-color 0.3s; }
        .chat-input:focus { border-color: #48bb78; }
        .send-btn { background: #48bb78; color: #fff; border: none; padding: 0.85rem 2rem; border-radius: 25px; cursor: pointer; font-weight: bold; transition: all 0.3s; font-size: 1rem; }
        .send-btn:hover { background: #38a169; transform: scale(1.05); }
        .send-btn:disabled { background: #ccc; cursor: not-allowed; transform: scale(1); }
        
        .no-messages { text-align: center; color: #667781; padding: 3rem; }
        #loadingIndicator { text-align: center; color: #667781; padding: 2rem; }
        
        /* Scrollbar */
        .messages-container::-webkit-scrollbar { width: 8px; }
        .messages-container::-webkit-scrollbar-track { background: transparent; }
        .messages-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
        .messages-container::-webkit-scrollbar-thumb:hover { background: #999; }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .chat-container { max-width: 100%; }
            .message-bubble { max-width: 80%; }
            .doctor-info h2 { font-size: 1rem; }
            .doctor-info p { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="chat-container">
    <div class="chat-header">
        <a href="../chat.php" class="back-btn">← Back</a>
        <div class="doctor-avatar"><?= strtoupper(substr($doctor['name'], 0, 1)) ?></div>
        <div class="doctor-info">
            <h2>Dr. <?= htmlspecialchars($doctor['name']) ?></h2>
            <p><?= htmlspecialchars($doctor['role']) ?> • <?= htmlspecialchars($doctor['experience']) ?> years experience • ⭐ <?= htmlspecialchars($doctor['rating']) ?></p>
        </div>
    </div>

    <div class="messages-container" id="messagesContainer">
        <div id="loadingIndicator">Loading messages...</div>
    </div>

    <div class="chat-input-container">
        <input type="text" id="messageInput" class="chat-input" placeholder="Type a message..." autocomplete="off">
        <button onclick="sendMessage()" id="sendBtn" class="send-btn">Send</button>
    </div>
</div>

<script>
(function() {
    "use strict";
    const config = {
        patientId: <?= json_encode($patient_id) ?>,
        doctorId: <?= json_encode($doctor_id) ?>,
        csrfToken: <?= json_encode($csrf_token) ?>,
        lastMessageId: 0,
        isLoading: false,
        pollInterval: null
    };

    const dom = {
        messagesContainer: document.getElementById("messagesContainer"),
        messageInput: document.getElementById("messageInput"),
        sendBtn: document.getElementById("sendBtn"),
        loadingIndicator: document.getElementById("loadingIndicator")
    };

    function formatTime(ts) {
        const date = new Date(ts);
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function formatDateDivider(dateStr) {
        const msgDate = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        msgDate.setHours(0,0,0,0);
        today.setHours(0,0,0,0);
        yesterday.setHours(0,0,0,0);
        
        if (msgDate.getTime() === today.getTime()) return 'Today';
        if (msgDate.getTime() === yesterday.getTime()) return 'Yesterday';
        return msgDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    let currentDate = '';
    
    function addMessage(msg, isInitialLoad = false) {
        if (dom.loadingIndicator) {
            dom.loadingIndicator.style.display = 'none';
        }

        // Add date divider if date changed
        const msgDate = msg.created_at.split(' ')[0];
        if (msgDate !== currentDate) {
            currentDate = msgDate;
            const divider = document.createElement('div');
            divider.className = 'date-divider';
            divider.innerHTML = `<span>${formatDateDivider(msg.created_at)}</span>`;
            dom.messagesContainer.appendChild(divider);
        }

        const isSent = msg.is_mine || msg.sender_type === 'patient';
        const messageDiv = document.createElement("div");
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
        
        const statusIcon = msg.is_read ? '✓✓' : (msg.is_delivered ? '✓✓' : '✓');
        const statusHtml = isSent ? `<span class="message-status">${statusIcon}</span>` : '';
        
        messageDiv.innerHTML = `
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                <div class="message-time">${formatTime(msg.created_at)} ${statusHtml}</div>
            </div>
        `;
        
        dom.messagesContainer.appendChild(messageDiv);

        if (!isInitialLoad) {
            scrollToBottom();
        }
    }

    function scrollToBottom() {
        dom.messagesContainer.scrollTop = dom.messagesContainer.scrollHeight;
    }

    async function fetchMessages(isPolling = false) {
        let endpoint = `get_messages.php?other_user_id=${config.doctorId}&other_user_type=doctor`;
        if (isPolling) {
            endpoint += `&last_message_id=${config.lastMessageId}`;
        }

        try {
            const response = await fetch(endpoint);
            const data = await response.json();

            if (data.success && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    addMessage(msg, !isPolling);
                    config.lastMessageId = Math.max(config.lastMessageId, msg.id);
                });
            }
            return data;
        } catch (error) {
            console.error("Fetch error:", error);
            return { success: false, messages: [] };
        }
    }

    async function loadInitialMessages() {
        currentDate = ''; // Reset date tracker
        const data = await fetchMessages(false);
        if (!data.success || data.messages.length === 0) {
            dom.loadingIndicator.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
        }
        scrollToBottom();
    }

    window.sendMessage = async function() {
        const text = dom.messageInput.value.trim();
        if (!text || config.isLoading) return;

        config.isLoading = true;
        dom.sendBtn.disabled = true;
        dom.sendBtn.textContent = 'Sending...';

        try {
            const response = await fetch("send_message.php", {
                method: "POST",
                headers: { 
                    "Content-Type": "application/json",
                    "X-CSRF-Token": config.csrfToken 
                },
                body: JSON.stringify({ 
                    receiver_id: config.doctorId,
                    receiver_type: 'doctor',
                    message: text 
                })
            });

            const data = await response.json();
            if (data.success) {
                addMessage({
                    id: data.message_id,
                    message: text,
                    sender_type: 'patient',
                    is_mine: true,
                    created_at: data.timestamp,
                    is_delivered: true,
                    is_read: false
                });
                dom.messageInput.value = "";
                config.lastMessageId = Math.max(config.lastMessageId, data.message_id);
            } else {
                alert(data.error || "Failed to send message.");
            }
        } catch (error) {
            console.error("Send message error:", error);
            alert("An error occurred while sending the message.");
        } finally {
            config.isLoading = false;
            dom.sendBtn.disabled = false;
            dom.sendBtn.textContent = 'Send';
            dom.messageInput.focus();
        }
    }

    // Send on Enter key
    dom.messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Initialize
    document.addEventListener("DOMContentLoaded", () => {
        loadInitialMessages();
        config.pollInterval = setInterval(() => fetchMessages(true), 3000);
        dom.messageInput.focus();
    });

    window.addEventListener("beforeunload", () => {
        if (config.pollInterval) clearInterval(config.pollInterval);
    });

})();
</script>
</body>
</html>