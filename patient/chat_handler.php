<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

// --- Auth ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';
if (isset($_SESSION['doctor_id'])) {
    $user_role = 'doctor';
}

$action = $_POST['action'] ?? '';

// ═══════════════════════════════════════════
//  SEND MESSAGE
// ═══════════════════════════════════════════
if ($action === 'send_message') {

    $receiver_id   = intval($_POST['receiver_id'] ?? 0);
    $message_type  = $_POST['message_type'] ?? 'text';
    $receiver_role = ($user_role === 'doctor') ? 'patient' : 'doctor';
    $message       = '';
    $file_path     = null;

    if ($receiver_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
        exit();
    }

    // ── IMAGE ──
    if ($message_type === 'image') {

        // Ensure 'image' is in the enum (safe to run repeatedly)
        $conn->query("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','audio','image') DEFAULT 'text'");

        $uploadDir = __DIR__ . '/uploads/chat_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $saved = false;

        // Option A: base64 string sent as 'image_base64'
        if (!empty($_POST['image_base64']) && strpos($_POST['image_base64'], 'data:image') === 0) {
            $data  = $_POST['image_base64'];
            $parts = explode(',', $data, 2);
            $ext   = 'jpg';
            if (preg_match('/data:image\/(\w+);/', $data, $m)) {
                $ext = ($m[1] === 'jpeg') ? 'jpg' : $m[1];
            }
            $filename = uniqid('img_', true) . '.' . $ext;
            file_put_contents($uploadDir . $filename, base64_decode($parts[1]));
            $file_path = 'uploads/chat_images/' . $filename;
            $message   = '[Image]';
            $saved     = true;
        }

        // Option B: multipart file upload
        if (!$saved && !empty($_FILES['image_file']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type']);
                exit();
            }
            $filename = uniqid('img_', true) . '.' . $ext;
            move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename);
            $file_path = 'uploads/chat_images/' . $filename;
            $message   = '[Image]';
            $saved     = true;
        }

        if (!$saved) {
            echo json_encode(['success' => false, 'message' => 'No image data received']);
            exit();
        }

    // ── AUDIO ──
    } elseif ($message_type === 'audio') {

        if (empty($_FILES['audio_file']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'No audio file received']);
            exit();
        }
        $uploadDir = __DIR__ . '/uploads/chat_audio/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $filename  = uniqid('audio_', true) . '.webm';
        move_uploaded_file($_FILES['audio_file']['tmp_name'], $uploadDir . $filename);
        $file_path = 'uploads/chat_audio/' . $filename;
        $message   = '[Audio]';

    // ── TEXT ──
    } else {
        $message_type = 'text';
        $message      = trim($_POST['message'] ?? '');
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Empty message']);
            exit();
        }
    }

    // INSERT
    // bind_param type string: i=sender_id, s=sender_role, i=receiver_id, s=receiver_role,
    //                         s=message, s=message_type, s=file_path  → "isissss" (7 chars)
    $ins = $conn->prepare(
        "INSERT INTO chat_messages
            (sender_id, sender_role, receiver_id, receiver_role, message, message_type, file_path)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    $ins->bind_param("isissss",
        $user_id, $user_role,
        $receiver_id, $receiver_role,
        $message, $message_type, $file_path
    );

    if ($ins->execute()) {
        echo json_encode(['success' => true, 'new_message_id' => $ins->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $ins->error]);
    }
    $ins->close();
    exit();
}

// ═══════════════════════════════════════════
//  FETCH MESSAGES (polling)
// ═══════════════════════════════════════════
if ($action === 'fetch_messages') {

    $receiver_id     = intval($_POST['receiver_id'] ?? 0);
    $last_message_id = intval($_POST['last_message_id'] ?? 0);
    $receiver_role   = ($user_role === 'doctor') ? 'patient' : 'doctor';

    // bind_param type string: "isisisisi" = 9 params
    $stmt = $conn->prepare(
        "SELECT * FROM chat_messages
         WHERE (
             (sender_id=? AND sender_role=? AND receiver_id=? AND receiver_role=?)
             OR
             (sender_id=? AND sender_role=? AND receiver_id=? AND receiver_role=?)
         )
         AND id > ?
         ORDER BY created_at ASC"
    );

    $stmt->bind_param("isisisisi",
        $user_id,     $user_role,     $receiver_id,  $receiver_role,
        $receiver_id, $receiver_role, $user_id,      $user_role,
        $last_message_id
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $row['formatted_time'] = date('h:i A', strtotime($row['created_at']));
        $row['message_type']   = $row['message_type'] ?? 'text';
        $row['file_path']      = $row['file_path'] ?? null;
        $messages[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
?>