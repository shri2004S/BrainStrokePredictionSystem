<?php
session_start();
require_once 'db_conn.php';

// --- 1. AUTHORIZATION & SETUP ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}
$doctor_id = $_SESSION['user_id'];

// --- 2. FETCH DOCTOR DETAILS (same as profile.php) ---
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

// Helper functions (same as profile.php)
function getVal($array, $key, $default = '') {
    return isset($array[$key]) ? htmlspecialchars($array[$key]) : $default;
}

function getProfileImage($doctor) {
    if (!empty($doctor['profile_image_path']) && file_exists($doctor['profile_image_path'])) {
        return htmlspecialchars($doctor['profile_image_path']) . '?v=' . filemtime($doctor['profile_image_path']);
    }
    return 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y';
}

// --- 3. FETCH APPOINTMENTS ---
$current_date_check = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$sql_appts = "SELECT a.*, p.full_name AS patient_name, p.email, p.phone 
              FROM appointments a 
              JOIN patient_signup p ON a.patient_id = p.id 
              WHERE a.doctor_id = ? 
              AND a.appointment_date = ? 
              ORDER BY a.appointment_time ASC";

$stmt_appts = mysqli_prepare($conn, $sql_appts);
mysqli_stmt_bind_param($stmt_appts, "is", $doctor_id, $current_date_check);
mysqli_stmt_execute($stmt_appts);
$result_appts = mysqli_stmt_get_result($stmt_appts);

$total_appts  = 0;
$video_calls  = 0;
$schedule_map = [];

if ($result_appts) {
    $total_appts = mysqli_num_rows($result_appts);
    while ($row = mysqli_fetch_assoc($result_appts)) {
        $hour_key = (int)date('H', strtotime($row['appointment_time']));
        $schedule_map[$hour_key] = $row;
        if ($row['type'] == 'Video Call') $video_calls++;
    }
}
mysqli_stmt_close($stmt_appts);
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Doctor Schedule | NeuroNest</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#4CAF50",
                    "background-light": "#F1F8E9",
                    "background-dark": "#121212",
                    "surface-light": "#FFFFFF",
                    "surface-dark": "#1E1E1E",
                },
                fontFamily: {
                    display: ["Plus Jakarta Sans", "sans-serif"],
                },
                borderRadius: {
                    DEFAULT: "12px",
                    "xl": "20px",
                },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .timeline-grid {
        display: grid;
        grid-template-columns: 80px 1fr;
    }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen transition-colors duration-200">
<div class="flex h-screen overflow-hidden">

    <!-- ===================== SIDEBAR (Updated from profile.php) ===================== -->
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
            <a class="flex items-center px-4 py-3 text-primary bg-primary/10 border-r-4 border-primary rounded-lg group font-semibold" href="schedule.php">
                <span class="material-symbols-outlined mr-3">calendar_month</span> Appointments
            </a>
            <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="chatdoc.php">
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

        <!-- ===================== DYNAMIC DOCTOR INFO (from profile.php) ===================== -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold mr-3 overflow-hidden">
                    <?php if (!empty($doctor_data['profile_image_path']) && file_exists($doctor_data['profile_image_path'])): ?>
                        <img src="<?= getProfileImage($doctor_data) ?>" alt="Profile" class="w-full h-full object-cover"/>
                    <?php else: ?>
                        <?= strtoupper(substr(getVal($doctor_data, 'full_name', 'Dr'), 0, 2)) ?>
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
            </div>
        </div>
    </aside>

    <!-- ===================== MAIN CONTENT ===================== -->
    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="flex justify-between items-center p-4 md:p-8 bg-white/50 dark:bg-surface-dark/50 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Doctor Schedule</h1>
                <p class="text-gray-500 dark:text-gray-400">Manage your daily appointments and consultations.</p>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                    <span class="material-symbols-outlined">dark_mode</span>
                </button>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">

            <!-- ===================== CALENDAR SIDEBAR ===================== -->
            <div class="w-80 bg-white dark:bg-surface-dark border-r border-gray-200 dark:border-gray-800 p-6 overflow-y-auto hidden xl:block">
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="font-bold text-gray-800 dark:text-white"><?php echo date('F Y'); ?></h2>
                    </div>
                    <div class="grid grid-cols-7 text-center text-xs gap-y-4">
                        <span class="text-gray-400 font-bold">SU</span>
                        <span class="text-gray-400 font-bold">MO</span>
                        <span class="text-gray-400 font-bold">TU</span>
                        <span class="text-gray-400 font-bold">WE</span>
                        <span class="text-gray-400 font-bold">TH</span>
                        <span class="text-gray-400 font-bold">FR</span>
                        <span class="text-gray-400 font-bold">SA</span>

                        <?php
                        $days_in_month     = date('t');
                        $current_month_year = date('Y-m');
                        $selected_day      = date('j', strtotime($current_date_check));

                        for ($i = 1; $i <= $days_in_month; $i++) {
                            $day_padded = str_pad($i, 2, "0", STR_PAD_LEFT);
                            $link_date  = "$current_month_year-$day_padded";
                            $class = ($i == $selected_day)
                                ? "bg-primary text-white font-bold rounded-lg block py-2"
                                : "text-gray-800 dark:text-white hover:bg-primary/10 rounded-lg block py-2";
                            echo "<a href='?date=$link_date' class='$class'>$i</a>";
                        }
                        ?>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Schedule Summary</h3>
                    <div class="p-4 bg-primary/5 rounded-xl border border-primary/20">
                        <p class="text-sm font-semibold text-primary"><?php echo $total_appts; ?> Appointments</p>
                        <p class="text-xs text-gray-500 mt-1">For <?php echo date('M j', strtotime($current_date_check)); ?></p>
                    </div>
                    <div class="p-4 bg-orange-50 dark:bg-orange-900/10 rounded-xl border border-orange-100 dark:border-orange-900/30">
                        <p class="text-sm font-semibold text-orange-600"><?php echo $video_calls; ?> Video Calls</p>
                    </div>
                </div>
            </div>

            <!-- ===================== TIMELINE ===================== -->
            <div class="flex-1 overflow-y-auto bg-gray-50/50 dark:bg-background-dark p-4 md:p-8">
                <div class="max-w-5xl mx-auto">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">
                            <?php echo date('l, M j', strtotime($current_date_check)); ?>
                        </h2>
                        <div class="flex bg-white dark:bg-surface-dark rounded-lg p-1 shadow-sm border border-gray-100 dark:border-gray-800">
                            <button class="px-4 py-1.5 text-sm font-semibold rounded-md bg-primary text-white">Day</button>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">

                            <?php
                            for ($h = 8; $h <= 17; $h++) {
                                $display_time = date("h A", strtotime("$h:00"));
                                $current_appt = isset($schedule_map[$h]) ? $schedule_map[$h] : null;
                            ?>

                            <div class="timeline-grid group min-h-[120px]">
                                <div class="p-4 text-xs font-bold text-gray-400 text-right pr-6 pt-6 uppercase">
                                    <?php echo $display_time; ?>
                                </div>

                                <div class="p-4 border-l border-gray-100 dark:border-gray-800 relative w-full">

                                    <?php if ($current_appt): ?>
                                        <?php
                                        $initials     = strtoupper(substr($current_appt['patient_name'], 0, 2));
                                        $is_video     = ($current_appt['type'] == 'Video Call');
                                        $border_color = $is_video ? 'border-primary' : 'border-blue-400';
                                        $bg_initial   = $is_video ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600';
                                        $tag_style    = $is_video ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600';
                                        ?>

                                        <div class="bg-white dark:bg-surface-dark border-l-4 <?php echo $border_color; ?> shadow-sm rounded-lg p-4 ml-2 mr-6 relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full <?php echo $bg_initial; ?> flex items-center justify-center font-bold">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-gray-800 dark:text-white">
                                                        <?php echo htmlspecialchars($current_appt['patient_name']); ?>
                                                    </h4>
                                                    <p class="text-xs text-gray-500 flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px]">medical_services</span>
                                                        <?php echo htmlspecialchars($current_appt['status']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        <?php echo date("h:i A", strtotime($current_appt['appointment_time'])); ?>
                                                    </p>
                                                </div>
                                                <span class="px-2 py-1 text-[10px] font-bold uppercase rounded-full <?php echo $tag_style; ?>">
                                                    <?php echo htmlspecialchars($current_appt['type']); ?>
                                                </span>
                                            </div>

                                            <div class="flex gap-2">
                                                <?php if ($is_video): ?>
                                                    <a href="video_call.php?room=<?php echo $current_appt['id']; ?>"
                                                       class="flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-primary border border-primary/20 rounded-lg hover:bg-primary hover:text-white transition-colors">
                                                        <span class="material-symbols-outlined text-[16px]">videocam</span> Join
                                                    </a>
                                                <?php endif; ?>

                                                <?php
                                                $raw_phone   = $current_appt['phone'];
                                                $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);
                                                if (substr($clean_phone, 0, 1) === '0') {
                                                    $clean_phone = '91' . substr($clean_phone, 1);
                                                } elseif (strlen($clean_phone) === 10) {
                                                    $clean_phone = '91' . $clean_phone;
                                                }

                                                $msg_date      = date('Y-m-d', strtotime($current_appt['appointment_date']));
                                                $msg_time      = date('h:iA',  strtotime($current_appt['appointment_time']));
                                                $pt_name       = $current_appt['patient_name'];
                                                $whatsapp_msg  = "Hello *$pt_name* ✨\n";
                                                $whatsapp_msg .= "Your appointment request has been *ACCEPTED*.\n\n";
                                                $whatsapp_msg .= "✦ Date: $msg_date\n";
                                                $whatsapp_msg .= "✦ Time: $msg_time\n\n";
                                                $whatsapp_msg .= "Please be ready at the right time.\n\n";
                                                $whatsapp_msg .= "Thank You ✦ NeuroNest Team";
                                                $whatsapp_url  = "https://wa.me/" . $clean_phone . "?text=" . urlencode($whatsapp_msg);
                                                ?>

                                                <a href="<?php echo $whatsapp_url; ?>" target="_blank"
                                                   class="flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-green-50 hover:text-green-600 dark:hover:bg-gray-800 transition-colors">
                                                    <span class="material-symbols-outlined text-[16px]">whatsapp</span>
                                                    Schedule
                                                </a>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        <div class="h-full flex items-center">
                                            <span class="text-xs text-gray-300 ml-4 group-hover:text-primary transition-colors">
                                                Available Slot
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>

                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>