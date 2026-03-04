<?php
/**
 * DOCTOR CHAT API HANDLER
 * Handles AJAX requests for doctor chat functionality
 * - Sending messages from doctor to patient
 * - Fetching new messages from patient
 */

session_start();
require_once 'db_conn.php';

// Set JSON response header
header('Content-Type: application/json');

// =====================================================
// AUTHENTICATION CHECK
// =====================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please login as doctor.'
    ]);
    exit();
}

$doctor_id = $_SESSION['user_id'];

// =====================================================
// HANDLE FETCH REQUEST (GET)
// =====================================================
if (isset($_GET['fetch']) && $_GET['fetch'] === 'true') {
    try {
        $other_id = intval($_GET['other_id'] ?? 0);
        $last_id = intval($_GET['last_id'] ?? 0);
        
        if ($other_id <= 0) {
            throw new Exception('Invalid patient ID');
        }
        
        // Fetch new messages from patient to doctor
        $sql = "SELECT 
                    id,
                    sender_id,
                    sender_role,
                    receiver_id,
                    receiver_role,
                    message,
                    is_read,
                    created_at,
                    DATE_FORMAT(created_at, '%h:%i %p') as formatted_time
                FROM chat_messages
                WHERE id > ?
                  AND (
                    (sender_id = ? AND sender_role = 'doctor' AND receiver_id = ? AND receiver_role = 'patient')
                    OR
                    (sender_id = ? AND sender_role = 'patient' AND receiver_id = ? AND receiver_role = 'doctor')
                  )
                ORDER BY created_at ASC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iiiii", $last_id, $doctor_id, $other_id, $other_id, $doctor_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to fetch messages: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $messages = [];
        
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => (int)$row['id'],
                'sender_id' => (int)$row['sender_id'],
                'sender_role' => $row['sender_role'],
                'receiver_id' => (int)$row['receiver_id'],
                'receiver_role' => $row['receiver_role'],
                'message' => htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'),
                'created_at' => $row['created_at'],
                'formatted_time' => $row['formatted_time'],
                'is_read' => (bool)$row['is_read']
            ];
        }
        
        $stmt->close();
        
        // Mark received messages as read (from patient to doctor)
        if (!empty($messages)) {
            $update_sql = "UPDATE chat_messages 
                          SET is_read = 1 
                          WHERE sender_role = 'patient' 
                            AND sender_id = ? 
                            AND receiver_role = 'doctor'
                            AND receiver_id = ? 
                            AND is_read = 0";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $other_id, $doctor_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        echo json_encode([
            'status' => 'success',
            'messages' => $messages,
            'count' => count($messages)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    } finally {
        if (isset($conn) && $conn->ping()) {
            $conn->close();
        }
    }
    exit();
}

// =====================================================
// HANDLE SEND REQUEST (POST)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        // Validation
        if ($receiver_id <= 0) {
            throw new Exception('Invalid patient ID');
        }
        
        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }
        
        if (strlen($message) > 5000) {
            throw new Exception('Message too long (max 5000 characters)');
        }
        
        // Verify patient exists
        $stmt = $conn->prepare("SELECT id FROM patient_signup WHERE id = ?");
        $stmt->bind_param("i", $receiver_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('Patient not found');
        }
        $stmt->close();
        
        // Insert message into chat_messages table
        $sql = "INSERT INTO chat_messages 
                (sender_id, sender_role, receiver_id, receiver_role, message, created_at) 
                VALUES (?, 'doctor', ?, 'patient', ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iis", $doctor_id, $receiver_id, $message);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to send message: ' . $stmt->error);
        }
        
        $new_message_id = $stmt->insert_id;
        $stmt->close();
        
        // Return success
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'new_message_id' => $new_message_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    } finally {
        if (isset($conn) && $conn->ping()) {
            $conn->close();
        }
    }
    exit();
}

// =====================================================
// INVALID REQUEST
// =====================================================
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid request method'
]);
?>