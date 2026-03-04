<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in as PATIENT
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized. Please login.']);
    exit;
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['status' => 'error', 'msg' => 'Database connection failed']);
    exit;
}

$patient_id = intval($_SESSION['user_id']);

// ==================== SEND MESSAGE (PATIENT TO DOCTOR) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
    
    // Validation
    if (empty($message)) {
        echo json_encode(['status' => 'error', 'msg' => 'Message is empty']);
        exit;
    }
    
    if ($doctor_id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid doctor ID']);
        exit;
    }
    
    // Verify doctor exists in doctors table
    $check_sql = "SELECT id FROM doctors WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $doctor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Doctor not found']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Sanitize message
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // Insert message
    $sql = "INSERT INTO chat_messages (sender_role, sender_id, receiver_id, message, created_at) 
            VALUES ('patient', ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'msg' => 'SQL error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("iis", $patient_id, $doctor_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'msg' => 'Message sent to doctor',
            'message_id' => $stmt->insert_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Failed to send message']);
    }
    
    $stmt->close();
    exit;
}

// ==================== FETCH NEW MESSAGES (PATIENT <-> DOCTOR) ====================
if (isset($_GET['fetch']) && $_GET['fetch'] == 'true') {
    
    $doctor_id = isset($_GET['other_id']) ? intval($_GET['other_id']) : 0;
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    if ($doctor_id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid doctor ID']);
        exit;
    }
    
    // Fetch NEW messages between patient and doctor
    $sql = "SELECT id, sender_id, receiver_id, sender_role, message, created_at 
            FROM chat_messages 
            WHERE ((sender_id = ? AND receiver_id = ? AND sender_role = 'patient') 
               OR (sender_id = ? AND receiver_id = ? AND sender_role = 'doctor'))
            AND id > ?
            ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'msg' => 'SQL error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("iiiii", $patient_id, $doctor_id, $doctor_id, $patient_id, $last_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'msg' => 'Query failed']);
        $stmt->close();
        exit;
    }
    
    $result = $stmt->get_result();
    $messages = array();
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = array(
            'id' => intval($row['id']),
            'sender_id' => intval($row['sender_id']),
            'receiver_id' => intval($row['receiver_id']),
            'sender_role' => $row['sender_role'],
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'time_formatted' => date('h:i A', strtotime($row['created_at'])),
            'is_from_me' => ($row['sender_id'] == $patient_id && $row['sender_role'] == 'patient')
        );
    }
    
    echo json_encode([
        'status' => 'success',
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
    $stmt->close();
    exit;
}

// ==================== GET DOCTOR LIST ====================
if (isset($_GET['get_doctors']) && $_GET['get_doctors'] == 'true') {
    
    $sql = "SELECT id, full_name, email, specialization, profile_image_path 
            FROM doctors 
            ORDER BY full_name";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $doctors = array();
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'doctors' => $doctors
        ]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Could not fetch doctors']);
    }
    
    exit;
}

// Invalid request
echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
exit;
?>