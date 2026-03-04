<?php
session_start();
require_once 'db_conn.php';

// --- 1. AUTHORIZATION ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: login.php');
    exit;
}
$doctor_id = $_SESSION['user_id'];

$current_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);

// --- 2. FETCH DOCTOR DETAILS ---
$sql_doctor = "SELECT * FROM doctors WHERE id = ?";
$stmt_doctor = mysqli_prepare($conn, $sql_doctor);
mysqli_stmt_bind_param($stmt_doctor, "i", $doctor_id);
mysqli_stmt_execute($stmt_doctor);
$result_doctor = mysqli_stmt_get_result($stmt_doctor);
$doctor_data   = mysqli_fetch_assoc($result_doctor);
mysqli_stmt_close($stmt_doctor);

if (!$doctor_data) {
    $doctor_data = ['full_name' => 'Doctor', 'specialization' => 'Specialist', 'profile_image_path' => ''];
}

function getVal($array, $key, $default = '') {
    return isset($array[$key]) ? htmlspecialchars($array[$key]) : $default;
}
function getProfileImage($doctor) {
    if (!empty($doctor['profile_image_path']) && file_exists($doctor['profile_image_path'])) {
        return htmlspecialchars($doctor['profile_image_path']) . '?v=' . filemtime($doctor['profile_image_path']);
    }
    return 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y';
}

/**
 * FIX: Resolve the correct public path for a file in the DOCTOR view.
 *  - Doctor files  → stored as  uploads/...  relative to /doctor/  → use as-is
 *  - Patient files → stored as  uploads/...  relative to /patient/ → prefix ../patient/
 */
function resolveFilePath($file_path, $sender_role) {
    if (!$file_path) return '';
    if (strpos($file_path, '../') === 0 || strpos($file_path, 'http') === 0) return $file_path;
    if ($sender_role === 'patient') {
        return '../patient/' . $file_path;
    }
    return $file_path; // doctor files are already relative to this folder
}

$doctor_name           = $doctor_data['full_name'];
$doctor_specialization = $doctor_data['specialization'] ?: 'Specialist';
$name_parts            = explode(' ', $doctor_name);
$initials = '';
foreach ($name_parts as $part) { if (!empty($part)) $initials .= strtoupper($part[0]); }
$doctor_initials = !empty($initials) ? substr($initials, 0, 2) : 'DR';

// --- 3. FETCH PATIENT LIST ---
$patients = [];
$sql_patients = "SELECT DISTINCT ps.id, ps.full_name, 
                   (SELECT MAX(created_at) FROM chat_messages WHERE (sender_id=ps.id AND receiver_id=?) OR (sender_id=? AND receiver_id=ps.id)) as last_message_time,
                   (SELECT COUNT(*) FROM chat_messages WHERE sender_id=ps.id AND receiver_id=? AND is_read=0 AND sender_role='patient') as unread_count
                FROM patient_signup ps LEFT JOIN appointments a ON ps.id=a.patient_id
                WHERE (a.doctor_id=? AND a.status IN ('Scheduled','Confirmed','Completed'))
                OR EXISTS (SELECT 1 FROM chat_messages cm WHERE (cm.sender_id=ps.id AND cm.receiver_id=? AND cm.sender_role='patient') OR (cm.sender_id=? AND cm.receiver_id=ps.id AND cm.receiver_role='patient'))
                ORDER BY last_message_time DESC, ps.full_name ASC";
$stmt_p = mysqli_prepare($conn, $sql_patients);
mysqli_stmt_bind_param($stmt_p, "iiiiii", $doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id);
mysqli_stmt_execute($stmt_p);
$result_p = mysqli_stmt_get_result($stmt_p);
while ($row = $result_p->fetch_assoc()) { $patients[] = $row; }
mysqli_stmt_close($stmt_p);

// --- 4. RESOLVE CURRENT PATIENT ---
$current_patient_name  = "Select a Patient";
$current_patient_valid = false;
if ($current_patient_id) {
    foreach ($patients as $p) {
        if ($p['id'] == $current_patient_id) {
            $current_patient_name  = $p['full_name'];
            $current_patient_valid = true;
            break;
        }
    }
}

// --- 5. FETCH CHAT HISTORY ---
$chat_history   = [];
$last_message_id = 0;
if ($current_patient_id && $current_patient_valid) {
    // Mark as read
    $ustmt = mysqli_prepare($conn, "UPDATE chat_messages SET is_read=1 WHERE sender_id=? AND sender_role='patient' AND receiver_id=? AND receiver_role='doctor'");
    mysqli_stmt_bind_param($ustmt, "ii", $current_patient_id, $doctor_id);
    mysqli_stmt_execute($ustmt);
    mysqli_stmt_close($ustmt);

    // FIX: scope by BOTH doctor_id AND patient_id so each doctor sees only their own conversation
    $sql_chat = "SELECT * FROM chat_messages
                 WHERE (
                     (sender_role='doctor'  AND sender_id=?   AND receiver_role='patient' AND receiver_id=?)
                  OR (sender_role='patient' AND sender_id=?   AND receiver_role='doctor'  AND receiver_id=?)
                 )
                 ORDER BY created_at ASC";
    $stmt_chat = mysqli_prepare($conn, $sql_chat);
    mysqli_stmt_bind_param($stmt_chat, "iiii", $doctor_id, $current_patient_id, $current_patient_id, $doctor_id);
    mysqli_stmt_execute($stmt_chat);
    $result_chat = mysqli_stmt_get_result($stmt_chat);
    while ($row = $result_chat->fetch_assoc()) {
        $chat_history[]  = $row;
        if ($row['id'] > $last_message_id) $last_message_id = $row['id'];
    }
    mysqli_stmt_close($stmt_chat);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Doctor Chat | NeuroNest</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: { extend: { colors: { primary:"#4CAF50","primary-light":"#E6F4EE","mint-bg":"#E8F5E9","mint-text":"#1B5E20","active-card":"#F0FDF4","background-light":"#F1F8E9","background-dark":"#121212","surface-light":"#FFFFFF","surface-dark":"#1E1E1E" }, fontFamily: { display: ["Plus Jakarta Sans","sans-serif"] }, borderRadius: { DEFAULT:"12px","xl":"20px" } } }
    };
</script>
<style type="text/tailwindcss">
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .chat-bg-pattern { background-color: #efeae2; }
    .message-bubble { position:relative; max-width:70%; padding:8px 12px; border-radius:8px; font-size:14px; line-height:1.4; box-shadow:0 1px 0.5px rgba(0,0,0,0.13); }
    .bubble-out { background-color:#d9fdd3; border-top-right-radius:0; }
    .bubble-in  { background-color:#ffffff;  border-top-left-radius:0;  }
    .custom-scrollbar::-webkit-scrollbar { width:5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background:transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background:#CBD5E1; border-radius:10px; }
    .msg-image { max-width:220px; max-height:200px; border-radius:6px; margin-bottom:4px; cursor:zoom-in; display:block; }
    .msg-audio { width:200px; height:34px; border-radius:20px; outline:none; }
    #imagePreviewBar { display:none; align-items:center; gap:8px; padding:8px 12px; background:#f0f2f5; border-top:1px solid #ddd; }
    #imagePreviewBar.visible { display:flex; }
    #previewImg { width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #ddd; }
    #audioRecordingBar { display:none; align-items:center; gap:10px; padding:0 12px; background:#f0f2f5; min-height:64px; border-top:1px solid #e5e5e5; }
    #audioRecordingBar.visible { display:flex; }
    .rec-dot { width:10px; height:10px; background:#e53935; border-radius:50%; animation:blink 1s infinite; }
    @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.2;} }
    #recTimer { font-size:14px; font-weight:600; color:#333; min-width:44px; }
    #recWave { flex:1; height:32px; }
    #lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; cursor:zoom-out; }
    #lightbox.open { display:flex; }
    #lightbox img { max-width:90vw; max-height:90vh; border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,0.5); }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark h-screen overflow-hidden flex transition-colors duration-200">

<div id="lightbox" onclick="closeLightbox()"><img id="lightboxImg" src="" alt="Full size"></div>

<!-- SIDEBAR -->
<aside class="w-64 bg-white dark:bg-surface-dark border-r border-gray-200 dark:border-gray-800 flex flex-col hidden lg:flex">
    <div class="p-6">
        <div class="bg-primary/10 p-4 rounded-xl flex flex-col items-center">
            <img alt="NeuroNest Logo" class="w-16 h-16 rounded-full mb-2"
                 src="https://lh3.googleusercontent.com/aida-public/AB6AXuBsiakfPww10MtmL2qGSrMBjr_O8GicBwwdsUq9XguZ6kmRW1StDc0OhJsG01YURu6Exc1LX6xKUOL1YOreQE2j7GY0nj_zTSYbMZ8m16XoDNIMwBgXiEozZIgRdO-BtrNcNJyndkyORBs17utAYlcecD7Q_W_ejBSkY7j5Aw8DEDwiEgIZxWoBTpl4uXHxD7myPjiNvHuLln1eCdRi5SqbL7O4RLxtztB5Yc--fCuIdmoAw7ooNdw52X27xrSEMU2oj804hx2C1Ew"/>
            <span class="text-xl font-bold text-gray-800 dark:text-white">NeuroNest</span>
        </div>
    </div>
    <nav class="flex-1 px-4 space-y-1 overflow-y-auto">
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="dashboard.php">
            <span class="material-symbols-outlined mr-3">dashboard</span> Dashboard
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="patient1.php">
            <span class="material-symbols-outlined mr-3">group</span> Patients
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="schedule.php">
            <span class="material-symbols-outlined mr-3">calendar_month</span> Appointments
        </a>
        <a class="flex items-center px-4 py-3 text-primary bg-primary/10 border-r-4 border-primary rounded-lg group font-semibold" href="chatdoc.php">
            <span class="material-symbols-outlined mr-3">chat_bubble</span> Messages
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="stroke.php">
            <span class="material-symbols-outlined mr-3">analytics</span> Analytics
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="profile.php">
            <span class="material-symbols-outlined mr-3">settings</span> Settings
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="logout.php">
            <span class="material-symbols-outlined mr-3">logout</span> Logout
        </a>
    </nav>

    <!-- DYNAMIC DOCTOR INFO -->
    <div class="p-4 border-t border-gray-100 dark:border-gray-800">
        <div class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 relative group">
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold mr-3 overflow-hidden">
                <?php if (!empty($doctor_data['profile_image_path']) && file_exists($doctor_data['profile_image_path'])): ?>
                    <img src="<?= getProfileImage($doctor_data) ?>" alt="Profile" class="w-full h-full object-cover"/>
                <?php else: ?>
                    <?= $doctor_initials ?>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                    Dr. <?= getVal($doctor_data, 'full_name', 'Doctor') ?>
                </p>
                <p class="text-xs text-gray-500 truncate">
                    <?= getVal($doctor_data, 'specialization', 'Specialist') ?>
                </p>
            </div>
            <button onclick="document.getElementById('logout-menu').classList.toggle('hidden')"
                    class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded">
                <span class="material-symbols-outlined text-gray-400">more_vert</span>
            </button>
            <div id="logout-menu" class="hidden absolute bottom-full left-4 right-4 mb-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2 z-50">
                <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <span class="material-symbols-outlined text-sm mr-2">person</span>Profile
                </a>
                <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                    <span class="material-symbols-outlined text-sm mr-2">logout</span>Logout
                </a>
            </div>
        </div>
    </div>
</aside>

<!-- PATIENT LIST -->
<aside class="w-full lg:w-[28%] bg-white dark:bg-surface-dark border-r border-gray-200 dark:border-gray-800 flex flex-col <?php echo $current_patient_id ? 'hidden lg:flex' : 'flex'; ?>" id="patientList">
    <div class="p-6 pb-2">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">My Patients</h2>
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
            <input type="text" id="searchPatients" onkeyup="filterPatients()"
                   class="w-full bg-gray-50 dark:bg-gray-800 border-none rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 placeholder-gray-400 text-gray-900 dark:text-white"
                   placeholder="Find a patient...">
        </div>
    </div>
    <div class="px-6 py-3">
        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Recent Conversations (<?php echo count($patients); ?>)</p>
    </div>
    <div class="flex-1 overflow-y-auto custom-scrollbar px-4 space-y-2 pb-4">
        <?php if (!empty($patients)): ?>
            <?php foreach ($patients as $p): ?>
                <?php
                $activeClass = ($current_patient_id == $p['id'])
                    ? 'bg-active-card border border-primary/20 shadow-sm'
                    : 'hover:bg-gray-50 dark:hover:bg-gray-800/50 border border-transparent';
                $p_initials = strtoupper(substr($p['full_name'], 0, 2));
                $hasUnread  = isset($p['unread_count']) && $p['unread_count'] > 0;
                ?>
                <a href="?patient_id=<?php echo $p['id']; ?>"
                   class="patient-item block rounded-2xl p-3 transition-all cursor-pointer <?php echo $activeClass; ?>"
                   data-name="<?php echo strtolower($p['full_name']); ?>">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-mint-bg flex items-center justify-center text-mint-text font-bold text-sm"><?php echo $p_initials; ?></div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-[#4CAF50] border-2 border-white rounded-full"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-center">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate"><?php echo htmlspecialchars($p['full_name']); ?></h3>
                                <?php if ($hasUnread): ?>
                                    <span class="bg-primary text-white text-[10px] px-1.5 py-0.5 rounded-full"><?php echo $p['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400 truncate mt-0.5">Click to view chat</p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-10 text-gray-400"><p class="text-sm">No patients found</p></div>
        <?php endif; ?>
    </div>
</aside>

<!-- CHAT AREA -->
<main class="flex-1 flex flex-col h-full bg-[#efeae2] dark:bg-gray-900 relative w-full lg:w-auto <?php echo $current_patient_id ? 'flex' : 'hidden lg:flex'; ?>">
    <?php if ($current_patient_id): ?>
        <header class="bg-[#f0f2f5] dark:bg-surface-dark px-4 py-3 flex items-center justify-between border-b border-gray-300 dark:border-gray-700 h-16 shrink-0 z-10">
            <div class="flex items-center gap-3">
                <a href="chatdoc.php" class="lg:hidden text-gray-500 dark:text-gray-400 mr-1">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
                <div class="w-10 h-10 rounded-full bg-mint-bg flex items-center justify-center text-mint-text font-bold">
                    <?php echo strtoupper(substr($current_patient_name, 0, 1)); ?>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-gray-800 dark:text-white text-sm"><?php echo htmlspecialchars($current_patient_name); ?></span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Patient</span>
                </div>
            </div>
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"><span class="material-symbols-outlined">videocam</span></button>
                <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"><span class="material-symbols-outlined">call</span></button>
                <button class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"><span class="material-symbols-outlined">more_vert</span></button>
                <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                    <span class="material-symbols-outlined">dark_mode</span>
                </button>
            </div>
        </header>

        <!-- Messages -->
        <div id="messagesContainer" class="flex-1 overflow-y-auto custom-scrollbar p-4 lg:p-8 space-y-2 chat-bg-pattern">
            <div class="flex justify-center mb-6">
                <div class="bg-[#ffeba6] text-[#554a28] text-xs px-3 py-1.5 rounded-lg shadow-sm text-center">
                    <span class="material-symbols-outlined text-[12px] align-middle mr-1">lock</span>Messages are end-to-end encrypted.
                </div>
            </div>
            <?php if (empty($chat_history)): ?>
                <div class="flex flex-col items-center justify-center h-full text-center opacity-60">
                    <span class="material-symbols-outlined text-4xl mb-2 text-gray-400">forum</span>
                    <p class="text-sm bg-white/60 px-4 py-1 rounded-full text-gray-600">Start a conversation with <?php echo htmlspecialchars($current_patient_name); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($chat_history as $msg):
                    $is_me    = ($msg['sender_role'] === 'doctor');
                    $time     = date('h:i A', strtotime($msg['created_at']));
                    $msg_type = $msg['message_type'] ?? 'text';
                    // FIX: resolve the correct public path based on who sent it
                    $media_src = resolveFilePath($msg['file_path'] ?? '', $msg['sender_role']);
                    if (!$media_src) $media_src = ''; // fallback
                ?>
                <div class="flex w-full <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                    <div class="message-bubble <?php echo $is_me ? 'bubble-out' : 'bubble-in'; ?>" <?php echo $msg_type !== 'text' ? 'style="padding:6px 8px;"' : ''; ?>>
                        <?php if ($msg_type === 'image' && $media_src): ?>
                            <img src="<?php echo htmlspecialchars($media_src); ?>"
                                 class="msg-image"
                                 onclick="openLightbox(this.src)"
                                 alt="Image"
                                 onerror="this.style.display='none';this.nextSibling.style.display='inline'">
                            <span style="display:none;font-size:12px;color:#999;">Image unavailable</span>
                        <?php elseif ($msg_type === 'audio' && $media_src): ?>
                            <audio controls class="msg-audio">
                                <source src="<?php echo htmlspecialchars($media_src); ?>">
                            </audio>
                        <?php else: ?>
                            <div class="text-gray-900 break-words pr-2"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <?php endif; ?>
                        <div class="flex justify-end items-center gap-1 mt-1 select-none">
                            <span class="text-[10px] text-gray-500 min-w-[45px] text-right"><?php echo $time; ?></span>
                            <?php if ($is_me): ?>
                                <span class="material-symbols-outlined <?php echo $msg['is_read'] == 1 ? 'text-blue-400' : 'text-gray-400'; ?> text-[14px]">done_all</span>
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
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <!-- Audio Recording Bar -->
        <div id="audioRecordingBar">
            <button onclick="cancelRecording()" class="text-gray-400 hover:text-red-500 p-2"><span class="material-symbols-outlined">delete</span></button>
            <div class="rec-dot"></div>
            <span id="recTimer">0:00</span>
            <canvas id="recWave" width="160" height="32"></canvas>
            <button onclick="stopAndSendAudio()" class="bg-[#4CAF50] text-white rounded-full p-2 hover:bg-[#43A047] transition-colors">
                <span class="material-symbols-outlined">send</span>
            </button>
        </div>

        <!-- Emoji Picker -->
        <div id="emojiPicker" style="position:absolute;bottom:70px;left:4px;width:300px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.18);z-index:100;display:none;flex-direction:column;overflow:hidden;">
            <input type="text" id="emojiSearch" placeholder="🔍 Search emoji..."
                   style="margin:8px;padding:6px 10px;border-radius:8px;border:1px solid #e5e5e5;font-size:13px;width:calc(100% - 16px);outline:none;"
                   oninput="filterEmojis(this.value)">
            <div style="display:flex;border-bottom:1px solid #f0f0f0;background:#f9f9f9;" id="emojiTabs"></div>
            <div id="emojiGrid" style="display:grid;grid-template-columns:repeat(8,1fr);gap:2px;padding:6px 8px;max-height:220px;overflow-y:auto;"></div>
        </div>

        <input type="file" id="imageFileInput" accept="image/*" style="display:none;" onchange="handleImageSelected(this)">

        <!-- Footer -->
        <footer class="bg-[#f0f2f5] dark:bg-surface-dark px-4 py-2 min-h-[64px] flex items-center gap-2 z-20 shrink-0 border-t border-gray-200 dark:border-gray-700" id="mainFooter">
            <button id="emojiToggleBtn" onclick="toggleEmojiPicker()" class="text-gray-500 dark:text-gray-400 p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full">
                <span class="material-symbols-outlined text-2xl">sentiment_satisfied</span>
            </button>
            <button onclick="document.getElementById('imageFileInput').click()" class="text-gray-500 dark:text-gray-400 p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full" title="Send image">
                <span class="material-symbols-outlined text-2xl">attach_file</span>
            </button>
            <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg flex items-center px-4 py-2 border border-gray-100 dark:border-gray-700">
                <input type="text" id="messageInput"
                       class="w-full border-none focus:ring-0 text-sm bg-transparent placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white"
                       placeholder="Type a message..." autocomplete="off" oninput="onInputChange()">
            </div>
            <button onclick="handleActionBtn()" id="actionBtn" class="text-gray-500 dark:text-gray-400 p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full transition-colors">
                <span class="material-symbols-outlined text-2xl text-primary" id="actionBtnIcon">mic</span>
            </button>
        </footer>

    <?php else: ?>
        <div class="hidden lg:flex flex-col items-center justify-center w-full h-full bg-[#f0f2f5] dark:bg-surface-dark border-b-[6px] border-[#25d366]">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/WhatsApp.svg/150px-WhatsApp.svg.png" class="w-20 opacity-20 mb-6 grayscale" alt="Logo">
            <h1 class="text-3xl font-light text-gray-700 dark:text-gray-300 mb-4">NeuroNest Web</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm text-center">Select a patient from the list to start chatting.</p>
        </div>
    <?php endif; ?>
</main>

<script>
const container  = document.getElementById('messagesContainer');
const input      = document.getElementById('messageInput');
const receiverId = <?php echo $current_patient_id ? $current_patient_id : 'null'; ?>;
let lastMsgId    = <?php echo $last_message_id; ?>;

if (container) container.scrollTop = container.scrollHeight;

function escapeHtml(text) {
    const map = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'};
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
function getTime() { return new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }
function appendBubble(html) { container.insertAdjacentHTML('beforeend', html); container.scrollTop = container.scrollHeight; }

/**
 * FIX (JS mirror of PHP resolveFilePath):
 * Patient files stored as uploads/... relative to /patient/ folder.
 * When displayed in /doctor/ context, prefix with ../patient/
 */
function resolveFilePath(filePath, senderRole) {
    if (!filePath) return '';
    if (filePath.startsWith('../') || filePath.startsWith('http')) return filePath;
    if (senderRole === 'patient') return '../patient/' + filePath;
    return filePath; // doctor files are already relative to this folder
}

function sentBubbleText(text) {
    return `<div class="flex w-full justify-end"><div class="message-bubble bubble-out"><div class="text-gray-900 break-words pr-2">${escapeHtml(text)}</div><div class="flex justify-end items-center gap-1 mt-1 select-none"><span class="text-[10px] text-gray-500 min-w-[45px] text-right">${getTime()}</span><span class="material-symbols-outlined text-gray-400 text-[14px]">done_all</span></div></div></div>`;
}
function sentBubbleImage(src) {
    return `<div class="flex w-full justify-end"><div class="message-bubble bubble-out" style="padding:6px 8px;"><img src="${escapeHtml(src)}" class="msg-image" onclick="openLightbox(this.src)" alt="Image"><div class="flex justify-end items-center gap-1 mt-1 select-none"><span class="text-[10px] text-gray-500 min-w-[45px] text-right">${getTime()}</span><span class="material-symbols-outlined text-gray-400 text-[14px]">done_all</span></div></div></div>`;
}
function sentBubbleAudio(src) {
    return `<div class="flex w-full justify-end"><div class="message-bubble bubble-out" style="padding:8px 10px;"><audio controls class="msg-audio"><source src="${escapeHtml(src)}"></audio><div class="flex justify-end items-center gap-1 mt-1 select-none"><span class="text-[10px] text-gray-500 min-w-[45px] text-right">${getTime()}</span><span class="material-symbols-outlined text-gray-400 text-[14px]">done_all</span></div></div></div>`;
}

function onInputChange() {
    const hasText = input && input.value.trim().length > 0;
    document.getElementById('actionBtnIcon').textContent = (hasText || selectedImageFile) ? 'send' : 'mic';
}
function handleActionBtn() {
    const hasContent = (input && input.value.trim().length > 0) || selectedImageFile;
    if (hasContent) sendTextMessage(); else toggleAudioRecording();
}
async function sendTextMessage() {
    if (!input || !receiverId) return;
    closeEmojiPicker();
    if (selectedImageFile) { await sendImageMessage(); return; }
    const text = input.value.trim();
    if (!text) return;
    appendBubble(sentBubbleText(text));
    input.value = ''; onInputChange();
    const fd = new FormData();
    fd.append('action','send_message'); fd.append('receiver_id', receiverId);
    fd.append('message', text); fd.append('message_type','text');
    try {
        const res = await fetch('chat_handler.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success && data.new_message_id) lastMsgId = data.new_message_id;
    } catch(e) { console.error('Send failed', e); }
}
if (input) input.addEventListener('keypress', e => { if(e.key==='Enter') sendTextMessage(); });

let selectedImageFile = null;
function handleImageSelected(input_el) {
    const file = input_el.files[0]; if (!file) return;
    selectedImageFile = file;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('previewCaption').textContent = file.name;
        document.getElementById('imagePreviewBar').classList.add('visible');
    };
    reader.readAsDataURL(file);
    onInputChange();
    document.getElementById('actionBtnIcon').textContent = 'send';
}
function clearImageSelection() {
    selectedImageFile = null;
    document.getElementById('imageFileInput').value = '';
    document.getElementById('imagePreviewBar').classList.remove('visible');
    onInputChange();
}
async function sendImageMessage() {
    if (!selectedImageFile || !receiverId) return;
    const fileRef = selectedImageFile;
    clearImageSelection();
    const reader = new FileReader();
    reader.onload = async (e) => {
        appendBubble(sentBubbleImage(e.target.result));
        const fd = new FormData();
        fd.append('action','send_message'); fd.append('receiver_id', receiverId);
        fd.append('message_type','image');
        fd.append('image_file', fileRef, fileRef.name); // send as actual file, not base64
        try {
            const res = await fetch('chat_handler.php', {method:'POST', body:fd});
            const data = await res.json();
            if (data.success && data.new_message_id) lastMsgId = data.new_message_id;
        } catch(e) { console.error('Image send failed', e); }
    };
    reader.readAsDataURL(fileRef);
}

let mediaRecorder=null, audioChunks=[], recTimerInt=null, recSeconds=0, animFrame=null, audioStream=null;
function toggleAudioRecording() { if(mediaRecorder && mediaRecorder.state==='recording') stopAndSendAudio(); else startRecording(); }
async function startRecording() {
    try { audioStream = await navigator.mediaDevices.getUserMedia({audio:true}); } catch(e) { alert('Microphone access denied.'); return; }
    audioChunks=[]; mediaRecorder = new MediaRecorder(audioStream);
    mediaRecorder.ondataavailable = e => { if(e.data.size>0) audioChunks.push(e.data); };
    mediaRecorder.start(100);
    document.getElementById('mainFooter').style.display='none';
    document.getElementById('audioRecordingBar').classList.add('visible');
    recSeconds=0;
    recTimerInt=setInterval(()=>{ recSeconds++; const m=Math.floor(recSeconds/60),s=recSeconds%60; document.getElementById('recTimer').textContent=`${m}:${s.toString().padStart(2,'0')}`; },1000);
    const ctx=new (window.AudioContext||window.webkitAudioContext)(), analyser=ctx.createAnalyser();
    analyser.fftSize=64; ctx.createMediaStreamSource(audioStream).connect(analyser); drawWave(analyser);
}
function drawWave(analyser) {
    const canvas=document.getElementById('recWave'); if(!canvas) return;
    const cCtx=canvas.getContext('2d'), bufLen=analyser.frequencyBinCount, dataArr=new Uint8Array(bufLen);
    function render() {
        animFrame=requestAnimationFrame(render); analyser.getByteFrequencyData(dataArr);
        cCtx.clearRect(0,0,canvas.width,canvas.height);
        const barW=(canvas.width/bufLen)*2; let x=0;
        dataArr.forEach(v=>{ const h=(v/255)*canvas.height; cCtx.fillStyle='#4CAF50'; cCtx.fillRect(x,canvas.height-h,barW-1,h); x+=barW; });
    } render();
}
function cancelRecording() { stopRecordingCleanup(); audioChunks=[]; }
function stopRecordingCleanup() {
    if(mediaRecorder && mediaRecorder.state!=='inactive') mediaRecorder.stop();
    if(audioStream) audioStream.getTracks().forEach(t=>t.stop());
    clearInterval(recTimerInt); cancelAnimationFrame(animFrame);
    document.getElementById('audioRecordingBar').classList.remove('visible');
    document.getElementById('mainFooter').style.display=''; onInputChange();
}
async function stopAndSendAudio() {
    if(!mediaRecorder) return;
    mediaRecorder.onstop = async () => {
        const blob=new Blob(audioChunks,{type:'audio/webm'}), url=URL.createObjectURL(blob);
        appendBubble(sentBubbleAudio(url));
        const fd=new FormData();
        fd.append('action','send_message'); fd.append('receiver_id', receiverId);
        fd.append('message_type','audio'); fd.append('audio_file', blob, 'voice_message.webm');
        try {
            const res=await fetch('chat_handler.php',{method:'POST',body:fd});
            const data=await res.json();
            if(data.success && data.new_message_id) lastMsgId=data.new_message_id;
        } catch(e) { console.error('Audio send failed',e); }
    };
    stopRecordingCleanup();
}

const EMOJI_DATA={'😊':'Smileys','😂':'Smileys','❤️':'Smileys','👍':'Smileys','😍':'Smileys','😭':'Smileys','😘':'Smileys','😅':'Smileys','😁':'Smileys','😆':'Smileys','😢':'Smileys','😎':'Smileys','😜':'Smileys','🤣':'Smileys','😏':'Smileys','🤔':'Smileys','😐':'Smileys','🙂':'Smileys','😮':'Smileys','😴':'Smileys','🤒':'Smileys','🤕':'Smileys','🤗':'Smileys','🤧':'Smileys','😷':'Smileys','🥺':'Smileys','🥰':'Smileys','🤩':'Smileys','😳':'Smileys','😬':'Smileys','👋':'People','🙌':'People','👏':'People','🙏':'People','💪':'People','🤝':'People','✌️':'People','👌':'People','🤞':'People','☝️':'People','🧠':'People','👩‍⚕️':'People','👨‍⚕️':'People','💊':'People','💉':'People','🩺':'People','🩹':'People','🏥':'People','🧬':'People','🔬':'People','🌟':'Nature','🌈':'Nature','☀️':'Nature','🌙':'Nature','⭐':'Nature','🌸':'Nature','🍀':'Nature','🌺':'Nature','🌻':'Nature','🦋':'Nature','🎉':'Objects','🎊':'Objects','🎁':'Objects','📱':'Objects','💻':'Objects','📚':'Objects','📋':'Objects','📝':'Objects','📊':'Objects','🗂️':'Objects','✅':'Symbols','❌':'Symbols','⚡':'Symbols','🔥':'Symbols','💯':'Symbols','❓':'Symbols','❗':'Symbols','💤':'Symbols','🔔':'Symbols','💬':'Symbols'};
const CATEGORY_ICONS={'Smileys':'😊','People':'👋','Nature':'🌸','Objects':'🎉','Symbols':'✅'};
let activeCategory='Smileys';
function buildEmojiPicker(){const tabBar=document.getElementById('emojiTabs'),cats=[...new Set(Object.values(EMOJI_DATA))];tabBar.innerHTML=cats.map(cat=>`<div onclick="setEmojiCategory('${cat}')" title="${cat}" style="flex:1;text-align:center;padding:8px 0;cursor:pointer;font-size:18px;border-bottom:2px solid ${cat===activeCategory?'#4CAF50':'transparent'};transition:all .15s;">${CATEGORY_ICONS[cat]||'🔹'}</div>`).join('');renderEmojiGrid(activeCategory);}
function setEmojiCategory(cat){activeCategory=cat;buildEmojiPicker();}
function renderEmojiGrid(cat,filter=''){const grid=document.getElementById('emojiGrid'),emojis=Object.entries(EMOJI_DATA).filter(([em,c])=>c===cat&&(filter===''||em.includes(filter))).map(([em])=>em);grid.innerHTML=emojis.map(em=>`<div onclick="insertEmoji('${em}')" title="${em}" style="font-size:22px;cursor:pointer;text-align:center;padding:4px 2px;border-radius:6px;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background=''">${em}</div>`).join('');}
function filterEmojis(val){renderEmojiGrid(activeCategory,val);}
function insertEmoji(em){if(!input)return;const pos=input.selectionStart,before=input.value.substring(0,pos),after=input.value.substring(pos);input.value=before+em+after;input.focus();input.selectionStart=input.selectionEnd=pos+em.length;onInputChange();}
function toggleEmojiPicker(){const picker=document.getElementById('emojiPicker'),isOpen=picker.style.display==='flex';if(isOpen){picker.style.display='none';}else{picker.style.display='flex';picker.style.flexDirection='column';buildEmojiPicker();}}
function closeEmojiPicker(){const picker=document.getElementById('emojiPicker');if(picker)picker.style.display='none';}

document.addEventListener('click', e => {
    const picker=document.getElementById('emojiPicker'),btn=document.getElementById('emojiToggleBtn');
    if(picker && btn && !picker.contains(e.target) && !btn.contains(e.target)) picker.style.display='none';
    const logoutMenu=document.getElementById('logout-menu');
    if(logoutMenu && !e.target.closest('#logout-menu') && !e.target.closest('[onclick*="logout-menu"]')) logoutMenu.classList.add('hidden');
});

function openLightbox(src){document.getElementById('lightboxImg').src=src;document.getElementById('lightbox').classList.add('open');}
function closeLightbox(){document.getElementById('lightbox').classList.remove('open');}

// ── POLLING — incoming patient messages ─────────────────────────────────────
if (receiverId) {
    setInterval(async () => {
        const fd = new FormData();
        fd.append('action','fetch_messages');
        fd.append('receiver_id', receiverId);   // patient ID
        fd.append('last_message_id', lastMsgId);
        try {
            const res  = await fetch('chat_handler.php', {method:'POST', body:fd});
            const data = await res.json();
            if (!data.success || !data.messages.length) return;

            data.messages.forEach(msg => {
                if (msg.sender_role !== 'patient') return; // safety guard

                const mType = msg.message_type || 'text';
                // FIX: resolve patient file path for doctor view
                const rawPath  = (msg.file_path && msg.file_path !== '') ? msg.file_path : '';
                const mediaSrc = resolveFilePath(rawPath, 'patient');

                let bubbleContent = '';
                if (mType === 'image' && mediaSrc) {
                    bubbleContent = `<img src="${escapeHtml(mediaSrc)}" class="msg-image" onclick="openLightbox(this.src)" alt="Image">`;
                } else if (mType === 'audio' && mediaSrc) {
                    bubbleContent = `<audio controls class="msg-audio"><source src="${escapeHtml(mediaSrc)}"></audio>`;
                } else {
                    bubbleContent = `<div class="text-gray-900 break-words pr-2">${escapeHtml(msg.message)}</div>`;
                }

                appendBubble(`<div class="flex w-full justify-start">
                    <div class="message-bubble bubble-in" ${mType!=='text'?'style="padding:6px 8px;"':''}>
                        ${bubbleContent}
                        <div class="flex justify-end items-center gap-1 mt-1 select-none">
                            <span class="text-[10px] text-gray-500 min-w-[45px] text-right">${msg.formatted_time}</span>
                        </div>
                    </div>
                </div>`);
                lastMsgId = msg.id;
            });
        } catch(e) { console.error('Polling Error:', e); }
    }, 3000);
}

function filterPatients(){const term=document.getElementById('searchPatients').value.toLowerCase();document.querySelectorAll('.patient-item').forEach(item=>{item.style.display=item.dataset.name.includes(term)?'block':'none';});}
</script>
</body>
</html>