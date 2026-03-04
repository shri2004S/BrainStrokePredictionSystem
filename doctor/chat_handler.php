<?php
// doctor/chat_handler.php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

// ── AUTHORISATION: must be a logged-in doctor ──────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$doctor_id  = (int) $_SESSION['user_id']; // always the DOCTOR (sender or receiver)
$action     = $_POST['action']      ?? '';
$receiver_id = (int) ($_POST['receiver_id'] ?? 0); // always the PATIENT on the other end

// ═══════════════════════════════════════════════════════════
//  ACTION: send_message
// ═══════════════════════════════════════════════════════════
if ($action === 'send_message') {

    $message_type = $_POST['message_type'] ?? 'text';
    $message      = '';
    $file_path    = null;

    // ── TEXT ──────────────────────────────────────────────
    if ($message_type === 'text') {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            echo json_encode(['success' => false, 'error' => 'Empty message']);
            exit;
        }
    }

    // ── IMAGE (file upload) ───────────────────────────────
    elseif ($message_type === 'image') {
        $dir = 'uploads/chat_images/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (!empty($_FILES['image_file']['tmp_name'])) {
            $ext      = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $ext      = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? $ext : 'jpg';
            $filename = 'img_' . uniqid('', true) . '.' . $ext;
            move_uploaded_file($_FILES['image_file']['tmp_name'], $dir . $filename);
            $file_path = $dir . $filename;
            $message   = '[Image]';

        } elseif (!empty($_POST['image_base64'])) {
            // base64 from patient-side chat (fallback)
            $parts    = explode(',', $_POST['image_base64'], 2);
            $imgData  = base64_decode(count($parts) === 2 ? $parts[1] : $parts[0]);
            $filename = 'img_' . uniqid('', true) . '.jpg';
            file_put_contents($dir . $filename, $imgData);
            $file_path = $dir . $filename;
            $message   = '[Image]';
        } else {
            echo json_encode(['success' => false, 'error' => 'No image data']);
            exit;
        }
    }

    // ── AUDIO ─────────────────────────────────────────────
    elseif ($message_type === 'audio') {
        if (empty($_FILES['audio_file']['tmp_name'])) {
            echo json_encode(['success' => false, 'error' => 'No audio data']);
            exit;
        }
        $dir = 'uploads/chat_audio/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'audio_' . uniqid('', true) . '.webm';
        move_uploaded_file($_FILES['audio_file']['tmp_name'], $dir . $filename);
        $file_path = $dir . $filename;
        $message   = '[Audio]';
    }

    // ── INSERT ────────────────────────────────────────────
    // sender = doctor ($doctor_id), receiver = patient ($receiver_id)
    $stmt = $conn->prepare(
        "INSERT INTO chat_messages
            (sender_id, sender_role, receiver_id, receiver_role, message, message_type, file_path)
         VALUES (?, 'doctor', ?, 'patient', ?, ?, ?)"
    );
    $stmt->bind_param("iisss", $doctor_id, $receiver_id, $message, $message_type, $file_path);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'new_message_id' => $new_id]);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  ACTION: fetch_messages  (poll for new PATIENT → DOCTOR msgs)
// ═══════════════════════════════════════════════════════════
if ($action === 'fetch_messages') {

    $last_id = (int) ($_POST['last_message_id'] ?? 0);

    // Only return messages where:
    //   sender = this specific PATIENT ($receiver_id)  →  receiver = this specific DOCTOR ($doctor_id)
    // This prevents mixing messages from different patients or different doctors.
    $stmt = $conn->prepare(
        "SELECT *,
                DATE_FORMAT(created_at, '%h:%i %p') AS formatted_time
         FROM   chat_messages
         WHERE  sender_role  = 'patient'
           AND  sender_id    = ?
           AND  receiver_role = 'doctor'
           AND  receiver_id  = ?
           AND  id           > ?
         ORDER  BY created_at ASC"
    );
    $stmt->bind_param("iii", $receiver_id, $doctor_id, $last_id);
    $stmt->execute();
    $result   = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    // Mark fetched messages as read
    if (!empty($messages)) {
        $ustmt = $conn->prepare(
            "UPDATE chat_messages
             SET    is_read = 1
             WHERE  sender_role  = 'patient'
               AND  sender_id    = ?
               AND  receiver_role = 'doctor'
               AND  receiver_id  = ?"
        );
        $ustmt->bind_param("ii", $receiver_id, $doctor_id);
        $ustmt->execute();
        $ustmt->close();
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

// ── Unknown action ─────────────────────────────────────────
echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>