<?php
session_start();
require_once 'db_conn.php';

// --- CONFIGURATION & DOCTOR INFO (Ported from Code 1) ---
$doctor_id = $_SESSION['user_id'] ?? null;

// Redirect if not logged in
if (!$doctor_id) {
    header('Location: login.php');
    exit;
}

$doctor_name = "Doctor";
$doctor_initials = "DR";
$doctor_specialization = "Neurologist";

try {
    if (isset($conn)) {
        $sql = "SELECT full_name FROM doctors WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $doctor_name = $row['full_name'];
            $name_parts = explode(' ', $doctor_name);
            $initials = '';
            foreach ($name_parts as $part) {
                if (!empty($part)) {
                    $initials .= strtoupper($part[0]);
                }
            }
            $doctor_initials = !empty($initials) ? substr($initials, 0, 2) : 'DR';
        }
        $stmt->close();
    }
} catch (Exception $e) {}

// --- PATIENT DATA FETCHING (From Code 2) ---
// Get patient ID from URL
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id === 0) {
    header("Location: patient1.php");
    exit();
}

// Fetch patient details
$patient_sql = "SELECT * FROM patient_signup WHERE id = '$patient_id'";
$patient_result = mysqli_query($conn, $patient_sql);
$patient = mysqli_fetch_assoc($patient_result);

if (!$patient) {
    header("Location: patient1.php");
    exit();
}

// Fetch latest prediction/stroke risk data
$pred_sql = "SELECT * FROM prediction_history 
             WHERE user_id = '$patient_id' 
             ORDER BY created_at DESC 
             LIMIT 1";
$pred_result = mysqli_query($conn, $pred_sql);
$prediction = mysqli_fetch_assoc($pred_result);

// Set default values if no prediction exists
$age = $prediction ? $prediction['age'] : 'N/A';
$risk_score = $prediction ? round($prediction['probability'], 2) : 0;
$risk_level = $prediction ? $prediction['risk_level'] : 'Not Assessed';
$last_assessment = $prediction ? date('M d, Y', strtotime($prediction['created_at'])) : 'Pending';

// Determine risk badge styling
$risk_badge_class = 'bg-gray-100 text-gray-600';
$risk_text = 'NOT ASSESSED';
if ($risk_score > 70) {
    $risk_badge_class = 'bg-red-50 text-red-600 border-red-100';
    $risk_text = "HIGH RISK ({$risk_score}%)";
} elseif ($risk_score >= 30) {
    $risk_badge_class = 'bg-orange-50 text-orange-600 border-orange-100';
    $risk_text = "MODERATE RISK ({$risk_score}%)";
} elseif ($risk_score > 0) {
    $risk_badge_class = 'bg-green-50 text-green-600 border-green-100';
    $risk_text = "LOW RISK ({$risk_score}%)";
}

// Get patient initials
$name = $patient['full_name'];
$initials = strtoupper(substr($name, 0, 2));

// Fetch all historical predictions for trend chart
$history_sql = "SELECT probability, created_at FROM prediction_history 
                WHERE user_id = '$patient_id' 
                ORDER BY created_at ASC";
$history_result = mysqli_query($conn, $history_sql);
$history_data = [];
while ($row = mysqli_fetch_assoc($history_result)) {
    $history_data[] = [
        'date' => date('M', strtotime($row['created_at'])),
        'risk' => round($row['probability'], 2)
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Patient Medical Case Details - NeuroNest</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#2D9F75",
                    secondary: "#E8F5E9",
                    "pale-mint": "#F0F9F6",
                    "dark-slate": "#2D3748",
                    "surface-light": "#FFFFFF",
                    "surface-dark": "#121212",
                },
                fontFamily: {
                    sans: ["Plus Jakarta Sans", "sans-serif"],
                },
                borderRadius: {
                    "2xl": "1rem",
                    "3xl": "1.5rem",
                },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    :root {
        --primary-brand: #2D9F75;
        --brand-bg: #E8F5E9;
        --page-bg: #F0F9F6;
    }
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .active-nav-item {
        @apply bg-[#F0F9F6] text-[#2D9F75] border-r-4 border-[#2D9F75] font-semibold;
    }
    .chart-gradient-bg {
        background: linear-gradient(180deg, rgba(45, 159, 117, 0.1) 0%, rgba(45, 159, 117, 0) 100%);
    }
    ::-webkit-scrollbar {
        width: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
</style>
</head>
<body class="bg-[var(--page-bg)] dark:bg-black min-h-screen transition-colors duration-200">
<div class="flex h-screen overflow-hidden">
    
    <aside class="w-64 bg-white dark:bg-surface-dark border-r border-gray-200 dark:border-gray-800 flex flex-col hidden lg:flex">
        <div class="p-6">
            <div class="bg-primary/10 p-4 rounded-xl flex flex-col items-center">
                <img alt="NeuroNest Logo" class="w-16 h-16 rounded-full mb-2" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBsiakfPww10MtmL2qGSrMBjr_O8GicBwwdsUq9XguZ6kmRW1StDc0OhJsG01YURu6Exc1LX6xKUOL1YOreQE2j7GY0nj_zTSYbMZ8m16XoDNIMwBgXiEozZIgRdO-BtrNcNJyndkyORBs17utAYlcecD7Q_W_ejBSkY7j5Aw8DEDwiEgIZxWoBTpl4uXHxD7myPjiNvHuLln1eCdRi5SqbL7O4RLxtztB5Yc--fCuIdmoAw7ooNdw52X27xrSEMU2oj804hx2C1Ew"/>
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
        </nav>
        
        <div class="p-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 relative group">
                <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold mr-3">
                    <?php echo $doctor_initials; ?>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        Dr. <?php echo htmlspecialchars($doctor_name); ?>
                    </p>
                    <p class="text-xs text-gray-500">
                        <?php echo htmlspecialchars($doctor_specialization); ?>
                    </p>
                </div>
                <button onclick="document.getElementById('logout-menu').classList.toggle('hidden')" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded">
                    <span class="material-symbols-outlined text-gray-400">more_vert</span>
                </button>
                
                <div id="logout-menu" class="hidden absolute bottom-full left-4 right-4 mb-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2">
                    <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <span class="material-symbols-outlined text-sm mr-2">person</span>
                        Profile
                    </a>
                    <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                        <span class="material-symbols-outlined text-sm mr-2">logout</span>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </aside>
    <main class="flex-1 overflow-y-auto">
        <header class="sticky top-0 z-30 bg-[var(--page-bg)]/80 dark:bg-black/80 backdrop-blur-md px-8 py-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <nav class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">
                        <a class="hover:text-primary transition-colors" href="patient1.php">Patients</a>
                        <span class="material-symbols-outlined text-[12px]">chevron_right</span>
                        <span class="text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($name); ?></span>
                    </nav>
                    <div class="flex items-center gap-4">
                        <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white"><?php echo htmlspecialchars($name); ?></h1>
                        <span class="px-3 py-1 <?php echo $risk_badge_class; ?> text-[10px] font-black rounded-full border uppercase tracking-tighter">
                            <?php echo $risk_text; ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <a href="patient1.php" class="p-2.5 text-slate-500 bg-white dark:bg-surface-dark border border-slate-200 dark:border-gray-800 rounded-2xl hover:text-primary transition-all">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </a>
                    <button class="p-2.5 text-slate-500 bg-white dark:bg-surface-dark border border-slate-200 dark:border-gray-800 rounded-2xl hover:text-primary transition-all" onclick="document.documentElement.classList.toggle('dark')">
                        <span class="material-symbols-outlined">dark_mode</span>
                    </button>
                   <button onclick="window.location.href='chatdoc.php'"
        class="flex items-center gap-2 px-6 py-3 bg-[var(--primary-brand)] text-white rounded-2xl font-bold hover:opacity-90 transition-all shadow-md">
    <span class="material-symbols-outlined text-xl">send</span>
    Message Patient
</button>
                </div>
            </div>
        </header>

        <div class="px-8 pb-12">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-4 space-y-8">
                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex flex-col items-center text-center mb-8">
                            <div class="w-24 h-24 rounded-3xl bg-orange-50 flex items-center justify-center text-orange-400 font-bold text-3xl mb-4">
                                <?php echo $initials; ?>
                            </div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($name); ?></h2>
                            <p class="text-slate-400 text-sm mt-1">ID: #PN-<?php echo 1000 + $patient_id; ?> • <?php echo date('M Y', strtotime($patient['created_at'])); ?></p>
                        </div>
                        <div class="space-y-4 border-t border-slate-50 pt-6">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400 font-medium">Age / Gender</span>
                                <span class="font-bold text-slate-700"><?php echo $age; ?> / <?php echo isset($patient['gender']) ? $patient['gender'] : 'N/A'; ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400 font-medium">Email</span>
                                <span class="font-bold text-slate-700 text-xs"><?php echo htmlspecialchars($patient['email']); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400 font-medium">Phone</span>
                                <span class="font-bold text-slate-700"><?php echo htmlspecialchars($patient['phone']); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400 font-medium">Last Assessment</span>
                                <span class="font-bold text-slate-700"><?php echo $last_assessment; ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($prediction): ?>
                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">analytics</span> 
                            Risk Factors
                        </h3>
                        <div class="space-y-4">
                        <?php
                        $risk_factors = [
                            ['name' => 'Hypertension', 'value' => $prediction['hypertension'] ?? 0, 'severity' => 'high'],
                            ['name' => 'Heart Disease', 'value' => $prediction['heart_disease'] ?? 0, 'severity' => 'high'],
                            ['name' => 'Smoking', 'value' => $prediction['smoking_status'] ?? 'Unknown', 'severity' => 'medium'],
                            ['name' => 'BMI', 'value' => $prediction['bmi'] ?? 'N/A', 'severity' => 'low'],
                        ];

                        foreach ($risk_factors as $factor):
                            $border_color = $factor['severity'] === 'high' ? 'border-red-400' : ($factor['severity'] === 'medium' ? 'border-orange-400' : 'border-green-400');
                        ?>
                            <div class="p-4 bg-slate-50 rounded-2xl border-l-4 <?php echo $border_color; ?>">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-sm font-bold text-slate-900"><?php echo $factor['name']; ?></span>
                                    <span class="text-xs font-bold text-slate-700"><?php echo is_numeric($factor['value']) ? ($factor['value'] == 1 ? 'Yes' : 'No') : $factor['value']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="lg:col-span-8 space-y-8">
                    <?php if (!empty($history_data)): ?>
                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex justify-between items-center mb-8">
                            <div>
                                <h3 class="text-xl font-bold text-dark-slate dark:text-white">Stroke Risk Trend</h3>
                                <p class="text-xs text-slate-400 mt-1">Probability calculated by NeuroNest AI engine</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Risk Level</span>
                            </div>
                        </div>
                        <div class="relative w-full h-48">
                            <canvas id="riskChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100">
                        <h3 class="text-xl font-bold text-dark-slate dark:text-white mb-8">Assessment Details</h3>
                        <?php if ($prediction): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-6 rounded-2xl border border-slate-100">
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Prediction Details</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-slate-600">Risk Score</span>
                                        <span class="text-lg font-bold text-slate-900"><?php echo $risk_score; ?>%</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-slate-600">Risk Level</span>
                                        <span class="text-sm font-bold text-slate-900"><?php echo $risk_level; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-slate-600">Assessment Date</span>
                                        <span class="text-sm font-bold text-slate-900"><?php echo $last_assessment; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 rounded-2xl border border-slate-100">
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Vital Statistics</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-slate-600">Age</span>
                                        <span class="text-lg font-bold text-slate-900"><?php echo $age; ?> years</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-slate-600">BMI</span>
                                        <span class="text-sm font-bold text-slate-900"><?php echo isset($prediction['bmi']) ? round($prediction['bmi'], 1) : 'N/A'; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-slate-600">Glucose Level</span>
                                        <span class="text-sm font-bold text-slate-900"><?php echo isset($prediction['avg_glucose_level']) ? round($prediction['avg_glucose_level'], 1) : 'N/A'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-slate-300">assessment</span>
                            <p class="text-slate-500 mt-4">No assessment data available for this patient yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex flex-wrap gap-4">
                            <button class="flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold hover:opacity-90 transition-all">
                                <span class="material-symbols-outlined text-[20px]">add</span>
                                Schedule Follow-up
                            </button>
                            <button class="flex items-center gap-2 px-6 py-3 bg-blue-500 text-white rounded-xl font-bold hover:opacity-90 transition-all">
                                <span class="material-symbols-outlined text-[20px]">assessment</span>
                                New Assessment
                            </button>
                            <button class="flex items-center gap-2 px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all">
                                <span class="material-symbols-outlined text-[20px]">download</span>
                                Export Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($history_data)): ?>
const ctx = document.getElementById('riskChart');
const chartData = <?php echo json_encode($history_data); ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.map(d => d.date),
        datasets: [{
            label: 'Risk Score (%)',
            data: chartData.map(d => d.risk),
            borderColor: '#2D9F75',
            backgroundColor: 'rgba(45, 159, 117, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 5,
            pointBackgroundColor: '#2D9F75',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                titleColor: '#fff',
                bodyColor: '#cbd5e1',
                padding: 12,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                grid: { color: 'rgba(0, 0, 0, 0.05)' },
                ticks: { callback: function(value) { return value + '%'; } }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
<?php endif; ?> 

// Script to handle the dropdown menu in the sidebar (ported from Code 1)
document.addEventListener('click', function(event) {
    const logoutMenu = document.getElementById('logout-menu');
    if (logoutMenu && !event.target.closest('.group')) {
        logoutMenu.classList.add('hidden');
    }
});
</script>

</body>
</html>