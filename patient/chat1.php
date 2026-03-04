<?php
session_start();
require_once 'db_conn.php';

$errors = [];
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];
$username   = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Patient';

$current_doctor_id = null;
if (isset($_GET['id']))        $current_doctor_id = intval($_GET['id']);
elseif (isset($_GET['doctor_id'])) $current_doctor_id = intval($_GET['doctor_id']);

$doctors = [];
$current_doctor = null;

try {
    if (isset($conn)) {
        $sql_list = "SELECT DISTINCT d.* FROM doctors d
                     INNER JOIN appointments a ON d.id = a.doctor_id
                     WHERE a.patient_id = ?
                     ORDER BY d.full_name";
        $stmt = $conn->prepare($sql_list);
        if ($stmt) {
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $doctors[] = $row;
            $stmt->close();
        }

        if ($current_doctor_id) {
            $sql_s = "SELECT * FROM doctors WHERE id = ?";
            $st2   = $conn->prepare($sql_s);
            if ($st2) {
                $st2->bind_param("i", $current_doctor_id);
                $st2->execute();
                $res2 = $st2->get_result();
                if ($doc_data = $res2->fetch_assoc()) {
                    $current_doctor = $doc_data;
                    $in_list = false;
                    foreach ($doctors as $d) {
                        if ($d['id'] == $current_doctor['id']) { $in_list = true; break; }
                    }
                    if (!$in_list) array_unshift($doctors, $current_doctor);
                }
                $st2->close();
            }
        } elseif (!empty($doctors)) {
            $current_doctor    = $doctors[0];
            $current_doctor_id = $doctors[0]['id'];
        }
    }
} catch (Exception $e) { $errors[] = $e->getMessage(); }

$chat_history    = [];
$last_message_id = 0;

if ($current_doctor && isset($conn)) {
    try {
        $sql_chat = "SELECT * FROM chat_messages
                WHERE (
                    (sender_id=? AND sender_role='patient' AND receiver_id=? AND receiver_role='doctor')
                    OR
                    (sender_id=? AND sender_role='doctor'  AND receiver_id=? AND receiver_role='patient')
                )
                ORDER BY created_at ASC";
        $stmt = $conn->prepare($sql_chat);
        if ($stmt) {
            $stmt->bind_param("iiii", $patient_id, $current_doctor['id'], $current_doctor['id'], $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $chat_history[] = $row;
                if ($row['id'] > $last_message_id) $last_message_id = $row['id'];
            }
            $stmt->close();
        }
    } catch (Exception $e) { $errors[] = $e->getMessage(); }
}

// Helper: decide how to render a message row
function getMessageType($row) {
    $type = $row['message_type'] ?? 'text';
    $fp   = $row['file_path']    ?? '';
    if ($fp) {
        if (strpos($fp, 'uploads/chat_images/') !== false) return 'image';
        if (strpos($fp, 'uploads/chat_audio/')  !== false) return 'audio';
    }
    return $type;
}

/**
 * FIX: Resolve the correct public path for a file.
 * - Doctor files live in  ../doctor/uploads/...
 * - Patient files live in       uploads/...  (same folder as this script)
 */
function resolveFilePath($file_path, $sender_role) {
    if (!$file_path) return '';
    // Already has a directory prefix (e.g. already prefixed or absolute URL) — leave it alone
    if (strpos($file_path, '../') === 0 || strpos($file_path, 'http') === 0) {
        return $file_path;
    }
    if ($sender_role === 'doctor') {
        return '../doctor/' . $file_path;
    }
    return $file_path; // patient files are relative to this script's location
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Chat | NeuroNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <style type="text/tailwindcss">
        :root {
            --primary: #2D9F75;
            --primary-light: #E6F4EE;
            --bg-light: #F8FBF9;
            --wa-bg: #efeae2;
            --wa-bubble-out: #d9fdd3;
            --wa-bubble-in: #ffffff;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .sidebar-active { @apply bg-[var(--primary-light)] text-[var(--primary)] border-l-4 border-[var(--primary)]; }
        .chat-bg-pattern {
            background-color: var(--wa-bg);
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%239CA3AF' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 10px; }
        .message-bubble { position:relative; max-width:65%; padding:8px 12px; border-radius:8px; font-size:14.2px; line-height:19px; box-shadow:0 1px 0.5px rgba(0,0,0,0.13); }
        .bubble-in  { background-color: var(--wa-bubble-in);  border-top-left-radius:0; }
        .bubble-out { background-color: var(--wa-bubble-out); border-top-right-radius:0; }
        .active-contact { background-color: #f0f2f5; }
        .msg-image { max-width:220px; max-height:200px; border-radius:6px; margin-bottom:4px; cursor:zoom-in; display:block; }
        .msg-audio { width:220px; height:36px; border-radius:20px; outline:none; display:block; margin-bottom:4px; }
        #imagePreviewBar { display:none; align-items:center; gap:8px; padding:8px 12px; background:#f0f2f5; border-top:1px solid #ddd; }
        #imagePreviewBar.visible { display:flex; }
        #previewImg { width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #ddd; }
        #audioRecordingBar { display:none; align-items:center; gap:10px; padding:0 12px; background:#f0f2f5; min-height:64px; border-top:1px solid #e5e5e5; }
        #audioRecordingBar.visible { display:flex; }
        .rec-dot { width:10px; height:10px; background:#e53935; border-radius:50%; animation:blink 1s infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }
        #recTimer { font-size:14px; font-weight:600; color:#333; min-width:44px; }
        #lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; cursor:zoom-out; }
        #lightbox.open { display:flex; }
        #lightbox img { max-width:90vw; max-height:90vh; border-radius:8px; }
    </style>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: { extend: { colors: { primary:"#2D9F75","background-light":"#F1F8E9","surface-dark":"#1E1E1E" } } }
        };
    </script>
</head>
<body class="bg-background-light min-h-screen overflow-hidden">

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()">
    <img id="lightboxImg" src="" alt="Full size">
</div>

<div class="flex h-screen w-full">

    <!-- Left nav -->
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col hidden lg:flex z-20">
        <div class="p-6">
            <div class="flex flex-col items-center gap-3">
                <div class="p-4 bg-[var(--bg-light)] rounded-2xl w-full flex justify-center">
                    <img alt="NeuroNest Logo" class="w-16 h-16 rounded-full mb-2"
                         src="https://lh3.googleusercontent.com/aida-public/AB6AXuD08vK3RuRVPr4VLfS9NA66YU-jHnsXxx_GDi0hRAy5gUTFQzGJbGCX_v00FtADpWg3pPq24mphnahaaPqQb3100id1cTD6-_phwOZlS0KGUe-mYGEL6pPKAxtHy8QIVC5ShomIy0C8lPaegvgmEzP7G1R9u-QEfVVfDtV2fCFwyIny6XZJuOtkSLrjyLtsHOwSDKU8SZA_d6UIHCznruKu3WXwj2Zm9GcnH5q6GWmmJWmLzG9ZGovjV7EHnxZsWOjXCUjvtohPA7g"/>
                </div>
                <h1 class="text-2xl font-bold text-[var(--primary)] tracking-tight">NeuroNest</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 space-y-2 mt-4">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl" href="dashboard.php"><span class="material-icons-outlined">home</span><span class="font-medium">Home</span></a>
            <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl" href="chat.php"><span class="material-icons-outlined">chat</span><span class="font-medium">Chat</span></a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl" href="notes.php"><span class="material-icons-outlined">description</span><span class="font-medium">Notes</span></a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl" href="Recommendation.php"><span class="material-icons-outlined">auto_awesome</span><span class="font-medium">Recommendations</span></a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl" href="prediction.php"><span class="material-icons-outlined">psychology</span><span class="font-medium">Prediction</span></a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl" href="community.php"><span class="material-icons-outlined">group</span><span class="font-medium">Community</span></a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl" href="Emergency.php"><span class="material-icons-outlined">report_problem</span><span class="font-medium text-red-500">Emergency</span></a>
        </nav>
        <div class="p-4 border-t border-slate-200">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden">
                    <img alt="User Profile" src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-slate-500">Patient Account</p>
                </div>
                <span class="material-icons-outlined text-slate-400">settings</span>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col relative h-full bg-slate-100 overflow-hidden">
        <div class="absolute top-0 w-full h-32 bg-[#00a884] z-0 hidden lg:block"></div>
        <div class="relative z-10 w-full h-full lg:h-[95vh] lg:w-[98%] lg:mx-auto lg:my-auto bg-white lg:rounded-xl shadow-2xl flex overflow-hidden">

            <!-- Contact sidebar -->
            <aside class="w-full lg:w-[35%] border-r border-gray-200 bg-white flex flex-col <?php echo $current_doctor ? 'hidden lg:flex' : 'flex'; ?>" id="contactList">
                <div class="bg-[#f0f2f5] p-3 border-b border-gray-100">
                    <div class="flex justify-between items-center mb-3 px-1">
                        <h2 class="font-bold text-xl text-gray-700">Chats</h2>
                        <div class="flex gap-2 text-gray-500">
                            <button class="p-1 hover:bg-gray-200 rounded-full"><span class="material-icons-outlined">edit_square</span></button>
                            <button class="p-1 hover:bg-gray-200 rounded-full"><span class="material-icons-outlined">more_vert</span></button>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg flex items-center px-3 py-1.5 border border-gray-100">
                        <span class="material-icons-outlined text-gray-400 text-sm">search</span>
                        <input type="text" id="searchInput" placeholder="Search doctors"
                               class="bg-transparent border-none focus:ring-0 text-sm w-full text-gray-700 placeholder-gray-400">
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar bg-white">
                    <?php if (empty($doctors)): ?>
                        <div class="p-8 text-center text-gray-400 text-sm">
                            <p>No conversations yet.</p>
                            <a href="chat.php" class="text-[var(--primary)] font-medium hover:underline mt-2 block">Find a specialist</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($doctors as $doc):
                            $isActive = ($current_doctor && $current_doctor['id'] == $doc['id']);
                            $initial  = strtoupper(substr($doc['full_name'], 0, 1));
                        ?>
                        <a href="?id=<?php echo $doc['id']; ?>"
                           class="flex items-center gap-3 p-3 cursor-pointer hover:bg-[#f5f6f6] border-b border-gray-50 transition-colors <?php echo $isActive ? 'active-contact' : ''; ?> doctor-item">
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-lg shrink-0">
                                <?php echo $initial; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline">
                                    <h4 class="text-gray-900 font-medium truncate">Dr. <?php echo htmlspecialchars($doc['full_name']); ?></h4>
                                    <span class="text-xs text-gray-400">Today</span>
                                </div>
                                <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($doc['specialization'] ?? 'Specialist'); ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Chat area -->
            <?php if ($current_doctor): ?>
            <div class="w-full lg:w-[65%] flex flex-col h-full bg-[#efeae2] relative" id="chatArea">

                <!-- Header -->
                <header class="bg-[#f0f2f5] px-4 py-3 flex items-center justify-between border-b border-gray-300 h-16 shrink-0 z-20">
                    <div class="flex items-center gap-3">
                        <a href="chat1.php" class="lg:hidden text-gray-500 mr-1"><span class="material-icons-outlined">arrow_back</span></a>
                        <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                            <?php echo strtoupper(substr($current_doctor['full_name'], 0, 1)); ?>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-semibold text-gray-800 text-md">Dr. <?php echo htmlspecialchars($current_doctor['full_name']); ?></span>
                            <span class="text-xs text-gray-500">Online</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-gray-500">
                        <button class="p-2 hover:bg-gray-200 rounded-full"><span class="material-icons-outlined">search</span></button>
                        <button class="p-2 hover:bg-gray-200 rounded-full"><span class="material-icons-outlined">more_vert</span></button>
                    </div>
                </header>

                <!-- Messages -->
                <div id="messagesContainer" class="flex-1 overflow-y-auto custom-scrollbar p-4 lg:p-8 space-y-2 chat-bg-pattern">
                    <div class="flex justify-center mb-6">
                        <div class="bg-[#ffeba6] text-[#554a28] text-xs px-3 py-1.5 rounded-lg shadow-sm text-center max-w-md">
                            <span class="material-icons-outlined text-[12px] align-middle mr-1">lock</span>
                            Messages are end-to-end encrypted.
                        </div>
                    </div>

                    <?php if (empty($chat_history)): ?>
                        <div class="flex flex-col items-center justify-center h-full text-center opacity-60">
                            <span class="material-symbols-outlined text-4xl mb-2">waving_hand</span>
                            <p class="text-sm bg-white/50 px-3 py-1 rounded-full">Say hello to Dr. <?php echo htmlspecialchars($current_doctor['full_name']); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chat_history as $msg):
                            $is_me       = ($msg['sender_role'] === 'patient');
                            $time        = date('h:i A', strtotime($msg['created_at']));
                            $mtype       = getMessageType($msg);
                            $fp          = $msg['file_path'] ?? '';
                            // ── FIX: route doctor files to the doctor folder ──
                            $resolved_fp = resolveFilePath($fp, $msg['sender_role']);
                        ?>
                        <div class="flex w-full <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                            <div class="message-bubble <?php echo $is_me ? 'bubble-out' : 'bubble-in'; ?>"
                                 <?php echo ($mtype !== 'text') ? 'style="padding:6px 8px;"' : ''; ?>>

                                <?php if ($mtype === 'image' && $resolved_fp): ?>
                                    <img src="<?php echo htmlspecialchars($resolved_fp); ?>"
                                         class="msg-image"
                                         onclick="openLightbox(this.src)"
                                         alt="Image"
                                         onerror="this.style.display='none';this.nextSibling.style.display='inline'">
                                    <span style="display:none;font-size:12px;color:#999;">Image unavailable</span>

                                <?php elseif ($mtype === 'audio' && $resolved_fp): ?>
                                    <audio controls class="msg-audio">
                                        <source src="<?php echo htmlspecialchars($resolved_fp); ?>">
                                        Your browser does not support audio.
                                    </audio>

                                <?php else: ?>
                                    <div class="text-gray-900 break-words pr-2">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="flex justify-end items-center gap-1 mt-1 select-none">
                                    <span class="text-[11px] text-gray-500 min-w-[45px] text-right"><?php echo $time; ?></span>
                                    <?php if ($is_me): ?>
                                        <span class="material-icons-outlined text-[#53bdeb] text-[15px]">done_all</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Image Preview Bar -->
                <div id="imagePreviewBar">
                    <img id="previewImg" src="" alt="Preview">
                    <span id="previewCaption" class="text-xs text-gray-500 flex-1 truncate">Image ready to send</span>
                    <button onclick="clearImageSelection()" class="text-gray-400 hover:text-red-500 p-1">
                        <span class="material-icons-outlined" style="font-size:18px;">close</span>
                    </button>
                </div>

                <!-- Audio Recording Bar -->
                <div id="audioRecordingBar">
                    <button onclick="cancelRecording()" class="text-gray-400 hover:text-red-500 p-2">
                        <span class="material-icons-outlined">delete</span>
                    </button>
                    <div class="rec-dot"></div>
                    <span id="recTimer">0:00</span>
                    <canvas id="recWave" width="160" height="32" style="flex:1;"></canvas>
                    <button onclick="stopAndSendAudio()" class="bg-[#00a884] text-white rounded-full p-2 hover:bg-[#009b79]">
                        <span class="material-icons-outlined">send</span>
                    </button>
                </div>

                <!-- Emoji Picker -->
                <div id="emojiPicker" style="position:absolute;bottom:70px;left:4px;width:300px;background:#fff;
                     border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.18);z-index:100;display:none;flex-direction:column;overflow:hidden;">
                    <input type="text" id="emojiSearch" placeholder="🔍 Search emoji..."
                           style="margin:8px;padding:6px 10px;border-radius:8px;border:1px solid #e5e5e5;font-size:13px;width:calc(100% - 16px);outline:none;"
                           oninput="filterEmojis(this.value)">
                    <div id="emojiTabs" style="display:flex;border-bottom:1px solid #f0f0f0;background:#f9f9f9;"></div>
                    <div id="emojiGrid" style="display:grid;grid-template-columns:repeat(8,1fr);gap:2px;padding:6px 8px;max-height:220px;overflow-y:auto;"></div>
                </div>

                <!-- Hidden file input -->
                <input type="file" id="imageFileInput" accept="image/*" style="display:none;" onchange="handleImageSelected(this)">

                <!-- Footer -->
                <footer id="mainFooter" class="bg-[#f0f2f5] px-4 py-2 min-h-[64px] flex items-center gap-2 z-20 shrink-0">
                    <button id="emojiToggleBtn" onclick="toggleEmojiPicker()"
                            class="text-gray-500 p-2 hover:bg-gray-200 rounded-full">
                        <span class="material-icons-outlined text-2xl">sentiment_satisfied_alt</span>
                    </button>
                    <button onclick="document.getElementById('imageFileInput').click()"
                            class="text-gray-500 p-2 hover:bg-gray-200 rounded-full" title="Attach image">
                        <span class="material-icons-outlined text-2xl">attach_file</span>
                    </button>
                    <div class="flex-1 bg-white rounded-lg flex items-center px-4 py-2">
                        <input type="text" id="messageInput"
                               class="w-full border-none focus:ring-0 text-sm bg-transparent placeholder-gray-500"
                               placeholder="Type a message" autocomplete="off"
                               oninput="onInputChange()" onkeydown="handleEnter(event)">
                    </div>
                    <button onclick="handleActionBtn()" id="actionBtn"
                            class="text-gray-500 p-2 hover:bg-gray-200 rounded-full transition-colors">
                        <span class="material-icons-outlined text-2xl" id="actionBtnIcon">mic</span>
                    </button>
                </footer>

            </div>
            <?php else: ?>
            <div class="hidden lg:flex flex-col items-center justify-center w-[65%] bg-[#f0f2f5] border-b-[6px] border-[#25d366]">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/WhatsApp.svg/150px-WhatsApp.svg.png" class="w-20 opacity-30 mb-6 grayscale" alt="">
                <h1 class="text-3xl font-light text-gray-700 mb-4">NeuroNest Web</h1>
                <p class="text-gray-500 text-sm max-w-md text-center leading-6">Select a conversation to start chatting.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// ═══════════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════════
const container  = document.getElementById('messagesContainer');
const input      = document.getElementById('messageInput');
const receiverId = <?php echo $current_doctor_id ? $current_doctor_id : 'null'; ?>;
let lastMsgId    = <?php echo $last_message_id; ?>;

if (container) container.scrollTop = container.scrollHeight;

// ═══════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════
function escapeHtml(t) {
    return t.replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}
function getTime() {
    return new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
}
function appendBubble(html) {
    container.insertAdjacentHTML('beforeend', html);
    container.scrollTop = container.scrollHeight;
}
function bubbleMeta(isMe) {
    return `<div class="flex justify-end items-center gap-1 mt-1 select-none">
        <span class="text-[11px] text-gray-500 min-w-[45px] text-right">${getTime()}</span>
        ${isMe ? '<span class="material-icons-outlined text-[#53bdeb] text-[15px]">done_all</span>' : ''}
    </div>`;
}
function wrapBubble(inner, isMe, compact = false) {
    const side = isMe ? 'justify-end' : 'justify-start';
    const cls  = isMe ? 'bubble-out'  : 'bubble-in';
    const pad  = compact ? 'style="padding:6px 8px;"' : '';
    return `<div class="flex w-full ${side}">
        <div class="message-bubble ${cls}" ${pad}>${inner}${bubbleMeta(isMe)}</div>
    </div>`;
}

/**
 * JS mirror of PHP resolveFilePath():
 * Prepend ../doctor/ for files that came from the doctor's uploads folder.
 */
function resolveFilePath(filePath, senderRole) {
    if (!filePath) return '';
    if (filePath.startsWith('../') || filePath.startsWith('http')) return filePath;
    if (senderRole === 'doctor') return '../doctor/' + filePath;
    return filePath;
}

// ═══════════════════════════════════════════════════════════
//  INPUT / ACTION BUTTON
// ═══════════════════════════════════════════════════════════
function handleEnter(e) { if (e.key === 'Enter') handleActionBtn(); }
function onInputChange() {
    const hasText = (input && input.value.trim().length > 0) || !!selectedImageFile;
    document.getElementById('actionBtnIcon').textContent = hasText ? 'send' : 'mic';
}
function handleActionBtn() {
    const hasText = (input && input.value.trim().length > 0) || !!selectedImageFile;
    hasText ? sendMessage() : toggleAudioRecording();
}

// ═══════════════════════════════════════════════════════════
//  SEND TEXT / IMAGE
// ═══════════════════════════════════════════════════════════
async function sendMessage() {
    closeEmojiPicker();
    if (selectedImageFile) { await sendImageMessage(); return; }

    const text = input.value.trim();
    if (!text || !receiverId) return;

    appendBubble(wrapBubble(`<div class="text-gray-900 break-words pr-2">${escapeHtml(text)}</div>`, true));
    input.value = '';
    onInputChange();

    const fd = new FormData();
    fd.append('action', 'send_message');
    fd.append('receiver_id', receiverId);
    fd.append('message', text);
    fd.append('message_type', 'text');
    try {
        const r = await fetch('chat_handler.php', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success && d.new_message_id) lastMsgId = d.new_message_id;
    } catch(e) { console.error('Send failed', e); }
}

// ─── IMAGE ────────────────────────────────────────────────
let selectedImageFile = null;
let selectedImageB64  = null;

function handleImageSelected(el) {
    const file = el.files[0];
    if (!file) return;
    selectedImageFile = file;

    const reader = new FileReader();
    reader.onload = e => {
        selectedImageB64 = e.target.result;
        document.getElementById('previewImg').src = selectedImageB64;
        document.getElementById('previewCaption').textContent = file.name;
        document.getElementById('imagePreviewBar').classList.add('visible');
        onInputChange();
        document.getElementById('actionBtnIcon').textContent = 'send';
    };
    reader.readAsDataURL(file);
}

function clearImageSelection() {
    selectedImageFile = null;
    selectedImageB64  = null;
    document.getElementById('imageFileInput').value = '';
    document.getElementById('imagePreviewBar').classList.remove('visible');
    onInputChange();
}

async function sendImageMessage() {
    if (!selectedImageFile || !receiverId) return;

    appendBubble(wrapBubble(
        `<img src="${selectedImageB64}" class="msg-image" onclick="openLightbox(this.src)" alt="Image">`,
        true, true
    ));

    const b64 = selectedImageB64;
    clearImageSelection();

    const fd = new FormData();
    fd.append('action',       'send_message');
    fd.append('receiver_id',  receiverId);
    fd.append('message_type', 'image');
    fd.append('image_base64', b64);

    try {
        const r = await fetch('chat_handler.php', {method:'POST', body:fd});
        const d = await r.json();
        if (d.success && d.new_message_id) lastMsgId = d.new_message_id;
    } catch(e) { console.error('Image send failed', e); }
}

// ═══════════════════════════════════════════════════════════
//  AUDIO RECORDING
// ═══════════════════════════════════════════════════════════
let mediaRecorder = null, audioChunks = [], recTimerInt = null,
    recSeconds = 0, animFrame = null, audioStream = null;

function toggleAudioRecording() {
    mediaRecorder && mediaRecorder.state === 'recording' ? stopAndSendAudio() : startRecording();
}

async function startRecording() {
    try { audioStream = await navigator.mediaDevices.getUserMedia({audio:true}); }
    catch(e) { alert('Microphone access denied.'); return; }

    audioChunks = [];
    mediaRecorder = new MediaRecorder(audioStream);
    mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
    mediaRecorder.start(100);

    document.getElementById('mainFooter').style.display = 'none';
    document.getElementById('audioRecordingBar').classList.add('visible');

    recSeconds = 0;
    recTimerInt = setInterval(() => {
        recSeconds++;
        const m = Math.floor(recSeconds / 60), s = recSeconds % 60;
        document.getElementById('recTimer').textContent = `${m}:${s.toString().padStart(2,'0')}`;
    }, 1000);

    const actx     = new (window.AudioContext || window.webkitAudioContext)();
    const analyser = actx.createAnalyser();
    analyser.fftSize = 64;
    actx.createMediaStreamSource(audioStream).connect(analyser);
    drawWave(analyser);
}

function drawWave(analyser) {
    const canvas = document.getElementById('recWave');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const buf = new Uint8Array(analyser.frequencyBinCount);
    (function render() {
        animFrame = requestAnimationFrame(render);
        analyser.getByteFrequencyData(buf);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const bw = (canvas.width / buf.length) * 2;
        let x = 0;
        buf.forEach(v => {
            const h = (v / 255) * canvas.height;
            ctx.fillStyle = '#00a884';
            ctx.fillRect(x, canvas.height - h, bw - 1, h);
            x += bw;
        });
    })();
}

function stopRecordingCleanup() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    if (audioStream) audioStream.getTracks().forEach(t => t.stop());
    clearInterval(recTimerInt);
    cancelAnimationFrame(animFrame);
    document.getElementById('audioRecordingBar').classList.remove('visible');
    document.getElementById('mainFooter').style.display = '';
    onInputChange();
}

function cancelRecording() { stopRecordingCleanup(); audioChunks = []; }

async function stopAndSendAudio() {
    if (!mediaRecorder) return;
    mediaRecorder.onstop = async () => {
        const blob = new Blob(audioChunks, {type:'audio/webm'});
        const url  = URL.createObjectURL(blob);
        appendBubble(wrapBubble(
            `<audio controls class="msg-audio"><source src="${url}"></audio>`,
            true, true
        ));

        const fd = new FormData();
        fd.append('action',       'send_message');
        fd.append('receiver_id',  receiverId);
        fd.append('message_type', 'audio');
        fd.append('audio_file',   blob, 'voice.webm');
        try {
            const r = await fetch('chat_handler.php', {method:'POST', body:fd});
            const d = await r.json();
            if (d.success && d.new_message_id) lastMsgId = d.new_message_id;
        } catch(e) { console.error('Audio send failed', e); }
    };
    stopRecordingCleanup();
}

// ═══════════════════════════════════════════════════════════
//  EMOJI PICKER
// ═══════════════════════════════════════════════════════════
const EMOJIS = {
    'Smileys': ['😊','😂','❤️','👍','😍','😭','😘','😅','😁','😆','😢','😎','😜','🤣','😏','🤔','😐','🙂','😮','😴','🤒','🤕','🤗','🤧','😷','🥺','🥰','🤩','😳','😬'],
    'People':  ['👋','🙌','👏','🙏','💪','🤝','✌️','👌','🤞','☝️','🧠','👩‍⚕️','👨‍⚕️','💊','💉','🩺','🩹','🏥','🧬','🔬'],
    'Nature':  ['🌟','🌈','☀️','🌙','⭐','🌸','🍀','🌺','🌻','🦋'],
    'Objects': ['🎉','🎊','🎁','📱','💻','📚','📋','📝','📊','🗂️'],
    'Symbols': ['✅','❌','⚡','🔥','💯','❓','❗','💤','🔔','💬'],
};
const CAT_ICONS = {'Smileys':'😊','People':'👋','Nature':'🌸','Objects':'🎉','Symbols':'✅'};
let activeEmojiCat = 'Smileys';

function buildEmojiPicker() {
    const tabs = document.getElementById('emojiTabs');
    tabs.innerHTML = Object.keys(EMOJIS).map(cat => `
        <div onclick="setEmojiCat('${cat}')" title="${cat}"
             style="flex:1;text-align:center;padding:8px 0;cursor:pointer;font-size:18px;
                    border-bottom:2px solid ${cat===activeEmojiCat?'#00a884':'transparent'};transition:all .15s;">
            ${CAT_ICONS[cat]}
        </div>`).join('');
    renderEmojiGrid();
}

function setEmojiCat(cat) { activeEmojiCat = cat; buildEmojiPicker(); }

function renderEmojiGrid(filter = '') {
    const grid   = document.getElementById('emojiGrid');
    const emojis = EMOJIS[activeEmojiCat].filter(e => !filter || e.includes(filter));
    grid.innerHTML = emojis.map(em =>
        `<div onclick="insertEmoji('${em}')" style="font-size:22px;cursor:pointer;text-align:center;padding:4px 2px;border-radius:6px;transition:background .1s;"
              onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background=''">
             ${em}
         </div>`).join('');
}

function filterEmojis(val) { renderEmojiGrid(val); }

function insertEmoji(em) {
    if (!input) return;
    const pos = input.selectionStart;
    input.value = input.value.slice(0, pos) + em + input.value.slice(pos);
    input.focus();
    input.selectionStart = input.selectionEnd = pos + em.length;
    onInputChange();
}

function toggleEmojiPicker() {
    const p = document.getElementById('emojiPicker');
    if (p.style.display === 'flex') { p.style.display = 'none'; }
    else { p.style.display = 'flex'; p.style.flexDirection = 'column'; buildEmojiPicker(); }
}
function closeEmojiPicker() {
    const p = document.getElementById('emojiPicker');
    if (p) p.style.display = 'none';
}

document.addEventListener('click', e => {
    const p = document.getElementById('emojiPicker');
    const b = document.getElementById('emojiToggleBtn');
    if (p && b && !p.contains(e.target) && !b.contains(e.target)) p.style.display = 'none';
});

// ═══════════════════════════════════════════════════════════
//  LIGHTBOX
// ═══════════════════════════════════════════════════════════
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }

// ═══════════════════════════════════════════════════════════
//  POLLING — incoming doctor messages
// ═══════════════════════════════════════════════════════════
if (receiverId) {
    setInterval(async () => {
        const fd = new FormData();
        fd.append('action', 'fetch_messages');
        fd.append('receiver_id', receiverId);
        fd.append('last_message_id', lastMsgId);
        try {
            const r = await fetch('chat_handler.php', {method:'POST', body:fd});
            const d = await r.json();
            if (!d.success || !d.messages.length) return;

            d.messages.forEach(msg => {
                if (msg.sender_role !== 'doctor') return;
                const mtype = msg.message_type || 'text';
                const fp    = msg.file_path    || '';
                // ── FIX: resolve doctor's file path ──
                const resolvedFp = resolveFilePath(fp, msg.sender_role);
                let inner   = '';

                if (mtype === 'image' && resolvedFp) {
                    inner = `<img src="${escapeHtml(resolvedFp)}" class="msg-image" onclick="openLightbox(this.src)" alt="Image">`;
                } else if (mtype === 'audio' && resolvedFp) {
                    inner = `<audio controls class="msg-audio"><source src="${escapeHtml(resolvedFp)}"></audio>`;
                } else {
                    inner = `<div class="text-gray-900 break-words pr-2">${escapeHtml(msg.message)}</div>`;
                }

                appendBubble(wrapBubble(inner, false, mtype !== 'text'));
                lastMsgId = msg.id;
            });
        } catch(e) { console.error(e); }
    }, 3000);
}

// Search filter
document.getElementById('searchInput')?.addEventListener('input', function() {
    const t = this.value.toLowerCase();
    document.querySelectorAll('.doctor-item').forEach(i => {
        i.style.display = i.querySelector('h4').textContent.toLowerCase().includes(t) ? 'flex' : 'none';
    });
});
</script>
</body>
</html>