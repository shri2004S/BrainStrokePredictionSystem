<?php
session_start();

// Include database connection
require_once 'db_conn.php';

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// --- 1. GET THE LOGGED-IN DOCTOR'S ID ---
$doctor_id = $_SESSION['user_id'];

// --- 2. FETCH DOCTOR PROFILE DATA (same as profile.php) ---
$sql_doctor = "SELECT * FROM doctors WHERE id = ?";
$stmt_doctor = mysqli_prepare($conn, $sql_doctor);
mysqli_stmt_bind_param($stmt_doctor, "i", $doctor_id);
mysqli_stmt_execute($stmt_doctor);
$result_doctor = mysqli_stmt_get_result($stmt_doctor);
$doctor = mysqli_fetch_assoc($result_doctor);
mysqli_stmt_close($stmt_doctor);

if (!$doctor) {
    die("Doctor profile not found.");
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

// --- 3. CHECK IF A SPECIFIC PATIENT IS REQUESTED ---
$specific_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

// --- 4. FETCH PATIENTS QUERY ---
if ($specific_patient_id) {
    $sql = "SELECT DISTINCT p.* FROM patient_signup p
            INNER JOIN appointments a ON p.id = a.patient_id
            WHERE a.doctor_id = ? 
            AND p.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $doctor_id, $specific_patient_id);
} else {
    $sql = "SELECT DISTINCT p.* FROM patient_signup p
            INNER JOIN appointments a ON p.id = a.patient_id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_date DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}

$total_patients = mysqli_num_rows($result);

// Get patient name for breadcrumb if viewing specific patient
$patient_name = '';
if ($specific_patient_id && $total_patients > 0) {
    $temp_row = mysqli_fetch_assoc($result);
    $patient_name = $temp_row['full_name'];
    mysqli_data_seek($result, 0);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>My Patients - NeuroNest</title>
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
    .table-row-hover:hover {
        background-color: rgba(76, 175, 80, 0.03);
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
        <a class="flex items-center px-4 py-3 text-primary bg-primary/10 border-r-4 border-primary rounded-lg group font-semibold" href="patient1.php">
            <span class="material-symbols-outlined mr-3">group</span> Patients
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="schedule.php">
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
                <?php if (!empty($doctor['profile_image_path']) && file_exists($doctor['profile_image_path'])): ?>
                    <img src="<?= getProfileImage($doctor) ?>" alt="Profile" class="w-full h-full object-cover"/>
                <?php else: ?>
                    <?= strtoupper(substr(getVal($doctor, 'full_name', 'Dr'), 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                    Dr. <?= getVal($doctor, 'full_name', 'Doctor') ?>
                </p>
                <p class="text-xs text-gray-500 truncate">
                    <?= getVal($doctor, 'specialization', 'Specialist') ?>
                </p>
            </div>
        </div>
    </div>
</aside>

<!-- ===================== MAIN CONTENT ===================== -->
<main class="flex-1 overflow-y-auto p-4 md:p-8">
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <?php if ($specific_patient_id): ?>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <a href="patient1.php" class="hover:text-primary">All Patients</a>
                    <span class="material-symbols-outlined text-xs">chevron_right</span>
                    <span class="text-gray-800 dark:text-white font-semibold"><?php echo htmlspecialchars($patient_name); ?></span>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Patient Details</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Viewing information for <?php echo htmlspecialchars($patient_name); ?></p>
            <?php else: ?>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">My Appointed Patients</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Viewing patients who have scheduled appointments with you.</p>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
            <?php if ($specific_patient_id): ?>
                <a href="patient1.php" class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg font-semibold hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Back to All Patients
                </a>
            <?php else: ?>
                <button class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-primary/90 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    Add New Patient
                </button>
            <?php endif; ?>
            <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                <span class="material-symbols-outlined">dark_mode</span>
            </button>
        </div>
    </header>

    <?php if (!$specific_patient_id): ?>
    <div class="bg-white dark:bg-surface-dark p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 mb-6 flex flex-col md:flex-row items-center gap-4">
        <div class="relative flex-1 w-full">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
            <input class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm transition-all"
                   placeholder="Search by name, ID, or condition..." type="text" id="searchInput"/>
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="relative w-full md:w-40">
                <select class="w-full appearance-none pl-3 pr-10 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-600 focus:ring-2 focus:ring-primary outline-none" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="urgent">Urgent</option>
                    <option value="moderate">Moderate</option>
                    <option value="stable">Stable</option>
                </select>
            </div>
            <div class="relative w-full md:w-40">
                <select class="w-full appearance-none pl-3 pr-10 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-600 focus:ring-2 focus:ring-primary outline-none" id="riskFilter">
                    <option value="">Risk: Any</option>
                    <option value="high">Risk: High (&gt;70%)</option>
                    <option value="medium">Risk: Medium (30-70%)</option>
                    <option value="low">Risk: Low (&lt;30%)</option>
                </select>
            </div>
            <button class="p-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-500 hover:text-primary">
                <span class="material-symbols-outlined">filter_list</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Patient ID</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Age</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Last Assessment</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Stroke Risk Score</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="patientTableBody">

                <?php
                if ($total_patients > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $id    = $row['id'];
                        $name  = $row['full_name'];
                        $email = $row['email'];
                        $initials = strtoupper(substr($name, 0, 2));

                        // Fetch latest prediction report
                        $pred_sql = "SELECT * FROM prediction_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
                        $pred_stmt = mysqli_prepare($conn, $pred_sql);
                        mysqli_stmt_bind_param($pred_stmt, "i", $id);
                        mysqli_stmt_execute($pred_stmt);
                        $pred_result = mysqli_stmt_get_result($pred_stmt);
                        $pred_data   = mysqli_fetch_assoc($pred_result);
                        mysqli_stmt_close($pred_stmt);

                        if ($pred_data) {
                            $age             = $pred_data['age'];
                            $raw_risk_score  = $pred_data['probability'];
                            $risk_level_text = $pred_data['risk_level'];
                            $last_assessment = date('M d, Y', strtotime($pred_data['created_at']));
                            $display_score   = round($raw_risk_score, 2);
                            $status_check    = strtolower($risk_level_text);
                        } else {
                            $age             = 'N/A';
                            $display_score   = 0;
                            $risk_level_text = 'Not Assessed';
                            $last_assessment = 'Pending';
                            $status_check    = 'none';
                        }

                        if ($status_check === 'high' || $status_check === 'urgent') {
                            $status_label = 'Urgent';    $bar_color = 'bg-red-500';
                            $score_text   = 'text-red-600';
                            $status_bg    = 'bg-red-50 dark:bg-red-900/20';
                            $status_text  = 'text-red-600';   $status_value = 'urgent';
                        } elseif ($status_check === 'moderate' || $status_check === 'medium') {
                            $status_label = 'Moderate';  $bar_color = 'bg-orange-400';
                            $score_text   = 'text-orange-500';
                            $status_bg    = 'bg-orange-50 dark:bg-orange-900/20';
                            $status_text  = 'text-orange-600'; $status_value = 'moderate';
                        } elseif ($status_check === 'low') {
                            $status_label = 'Stable';    $bar_color = 'bg-primary';
                            $score_text   = 'text-primary';
                            $status_bg    = 'bg-green-50 dark:bg-green-900/20';
                            $status_text  = 'text-green-600';  $status_value = 'stable';
                        } else {
                            $status_label = 'New';       $bar_color = 'bg-gray-300';
                            $score_text   = 'text-gray-400';
                            $status_bg    = 'bg-gray-100 dark:bg-gray-800';
                            $status_text  = 'text-gray-500';   $status_value = 'new';
                        }

                        $colors    = [
                            ['bg-orange-100', 'dark:bg-orange-900/30', 'text-orange-600'],
                            ['bg-blue-100',   'dark:bg-blue-900/30',   'text-blue-600'],
                            ['bg-green-100',  'dark:bg-green-900/30',  'text-green-600'],
                            ['bg-purple-100', 'dark:bg-purple-900/30', 'text-purple-600'],
                            ['bg-gray-100',   'dark:bg-gray-700',      'text-gray-600'],
                        ];
                        $color_idx  = $id % 5;
                        $avatar_bg  = $colors[$color_idx][0];
                        $avatar_dark = $colors[$color_idx][1];
                        $avatar_text = $colors[$color_idx][2];
                ?>
                    <tr class="table-row-hover transition-colors"
                        data-patient-name="<?php echo strtolower($name); ?>"
                        data-patient-id="<?php echo $id; ?>"
                        data-status="<?php echo $status_value; ?>"
                        data-risk="<?php echo $display_score; ?>">

                        <td class="px-6 py-4 text-sm font-medium text-gray-400">#PN-<?php echo 1000 + $id; ?></td>

                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full <?php echo $avatar_bg . ' ' . $avatar_dark . ' ' . $avatar_text; ?> flex items-center justify-center font-bold text-xs">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($name); ?></span>
                                    <span class="text-[10px] text-gray-400"><?php echo htmlspecialchars($email); ?></span>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 text-center"><?php echo $age; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?php echo $last_assessment; ?></td>

                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full min-w-[120px]">
                                    <div class="h-full <?php echo $bar_color; ?> rounded-full" style="width: <?php echo min($display_score, 100); ?>%"></div>
                                </div>
                                <span class="text-sm font-bold <?php echo $score_text; ?>"><?php echo $display_score; ?>%</span>
                            </div>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 text-[10px] font-bold uppercase rounded-full <?php echo $status_bg . ' ' . $status_text; ?>">
                                <?php echo $status_label; ?>
                            </span>
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="patient1.php?patient_id=<?php echo $id; ?>"
                                   class="text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 p-2 rounded-lg transition-colors inline-block"
                                   title="View Patient Details">
                                    <span class="material-symbols-outlined align-middle text-[20px]">info</span>
                                </a>
                                <a href="stroke.php?patient_id=<?php echo $id; ?>"
                                   class="text-primary hover:bg-primary/10 p-2 rounded-lg transition-colors inline-block"
                                   title="View Stroke Report">
                                    <span class="material-symbols-outlined align-middle text-[20px]">visibility</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                    if ($specific_patient_id) {
                        echo '<tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">Patient not found or has no appointments with you.</td></tr>';
                    } else {
                        echo '<tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No patients have booked appointments with you yet.</td></tr>';
                    }
                }
                ?>

                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
            <span class="text-sm text-gray-500">
                Showing <span class="font-semibold text-gray-800 dark:text-white">1</span>
                to <span class="font-semibold text-gray-800 dark:text-white"><?php echo $total_patients; ?></span>
                of <span class="font-semibold text-gray-800 dark:text-white"><?php echo $total_patients; ?></span> entries
            </span>
            <div class="flex items-center gap-1">
                <button class="p-2 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-400 hover:text-primary disabled:opacity-50" disabled>
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold">1</button>
                <button class="p-2 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-400 hover:text-primary">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        </div>
    </div>
</main>
</div>

<?php if (!$specific_patient_id): ?>
<button class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-white rounded-full shadow-xl flex items-center justify-center hover:scale-110 transition-transform z-50">
    <span class="material-symbols-outlined">person_add</span>
</button>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput  = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const riskFilter   = document.getElementById('riskFilter');
    const tableBody    = document.getElementById('patientTableBody');

    if (searchInput && statusFilter && riskFilter) {
        function filterTable() {
            const searchTerm  = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();
            const riskValue   = riskFilter.value.toLowerCase();
            const rows        = tableBody.querySelectorAll('tr[data-patient-name]');

            rows.forEach(row => {
                const name   = row.getAttribute('data-patient-name');
                const id     = row.getAttribute('data-patient-id');
                const status = row.getAttribute('data-status');
                const risk   = parseFloat(row.getAttribute('data-risk'));
                let showRow  = true;

                if (searchTerm && !name.includes(searchTerm) && !id.includes(searchTerm)) showRow = false;
                if (statusValue && status !== statusValue) showRow = false;
                if (riskValue) {
                    if (riskValue === 'high'   && risk <= 70)              showRow = false;
                    if (riskValue === 'medium' && (risk < 30 || risk > 70)) showRow = false;
                    if (riskValue === 'low'    && risk >= 30)              showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
        riskFilter.addEventListener('change', filterTable);
    }
});
</script>
</body>
</html>