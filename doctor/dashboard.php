<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_conn.php';

// Get Doctor Info from Session
$doctor_id = $_SESSION['user_id']; 
$doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';
$doctor_specialization = $_SESSION['doctor_specialization'] ?? 'Neurologist';

// Generate Initials
$name_parts = explode(' ', $doctor_name);
$initials = '';
foreach ($name_parts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
}
$initials = !empty($initials) ? substr($initials, 0, 2) : 'DR'; 
$first_name = $name_parts[0] ?? 'Doctor';

// --- REAL DATA QUERIES START ---

// 1. STATS: Total Patients (Count of unique patients associated with this doctor)
$total_pat_sql = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = '$doctor_id'";
$total_pat_res = mysqli_query($conn, $total_pat_sql);
$total_patients = ($total_pat_res) ? mysqli_fetch_assoc($total_pat_res)['count'] : 0;

// 2. STATS: Urgent Risks (High risk patients associated with this doctor)
$urgent_sql = "SELECT COUNT(*) as count FROM prediction_history ph 
               JOIN patient_signup p ON ph.user_id = p.id
               WHERE ph.risk_level = 'High' 
               AND p.id IN (SELECT patient_id FROM appointments WHERE doctor_id = '$doctor_id')";
$urgent_res = mysqli_query($conn, $urgent_sql);
$urgent_count = ($urgent_res) ? mysqli_fetch_assoc($urgent_res)['count'] : 0;

// 3. STATS: Today's Schedule Count
$today = date('Y-m-d');
$today_sched_sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = '$doctor_id' AND appointment_date = '$today'";
$today_sched_res = mysqli_query($conn, $today_sched_sql);
$today_count = ($today_sched_res) ? mysqli_fetch_assoc($today_sched_res)['count'] : 0;

// 4. TABLE: Recent Patients (Limit 5)
$recent_patients_query = "
    SELECT p.full_name, ph.created_at, ph.probability, ph.risk_level 
    FROM prediction_history ph 
    JOIN patient_signup p ON ph.user_id = p.id 
    WHERE p.id IN (SELECT patient_id FROM appointments WHERE doctor_id = '$doctor_id')
    ORDER BY ph.created_at DESC 
    LIMIT 5";
$recent_patients_result = mysqli_query($conn, $recent_patients_query);

// 5. LIST: Upcoming Appointments (Next 3)
$appointments_query = "
    SELECT a.appointment_time, a.type, p.full_name 
    FROM appointments a 
    JOIN patient_signup p ON a.patient_id = p.id 
    WHERE a.doctor_id = '$doctor_id' 
    AND a.appointment_date >= '$today' 
    ORDER BY a.appointment_date ASC, a.appointment_time ASC 
    LIMIT 3";
$appointments_result = mysqli_query($conn, $appointments_query);

// --- REAL DATA QUERIES END ---
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Doctor Dashboard - NeuroNest</title>
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
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-white dark:bg-surface-dark border-r border-gray-200 dark:border-gray-800 flex flex-col hidden lg:flex">
            <div class="p-6">
                <div class="bg-primary/10 p-4 rounded-xl flex flex-col items-center">
                    <img alt="NeuroNest Logo" class="w-16 h-16 rounded-full mb-2" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBsiakfPww10MtmL2qGSrMBjr_O8GicBwwdsUq9XguZ6kmRW1StDc0OhJsG01YURu6Exc1LX6xKUOL1YOreQE2j7GY0nj_zTSYbMZ8m16XoDNIMwBgXiEozZIgRdO-BtrNcNJyndkyORBs17utAYlcecD7Q_W_ejBSkY7j5Aw8DEDwiEgIZxWoBTpl4uXHxD7myPjiNvHuLln1eCdRi5SqbL7O4RLxtztB5Yc--fCuIdmoAw7ooNdw52X27xrSEMU2oj804hx2C1Ew"/>
                    <span class="text-xl font-bold text-gray-800 dark:text-white">NeuroNest</span>
                </div>
            </div>
            
            <nav class="flex-1 px-4 space-y-1 overflow-y-auto">
                <a class="flex items-center px-4 py-3 text-primary bg-primary/10 border-r-4 border-primary rounded-lg group font-semibold" href="dashboard.php">
                    <span class="material-symbols-outlined mr-3">dashboard</span> Dashboard
                </a>
                <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="patient1.php">
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
                        <?php echo $initials; ?>
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

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                        Welcome back, Dr. <?php echo htmlspecialchars($first_name); ?>
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400">Here's what's happening with your patients today.</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative hidden md:block">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                        <input class="pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-700 dark:bg-surface-dark rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm" placeholder="Search patients..." type="text"/>
                    </div>
                    <button class="p-2 text-gray-400 hover:text-primary transition-colors relative">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-surface-dark"></span>
                    </button>
                    <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                        <span class="material-symbols-outlined">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <span class="material-symbols-outlined text-blue-600">group</span>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-full">Active</span>
                    </div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total Patients</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($total_patients); ?></p>
                </div>
                
                <div class="bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
                            <span class="material-symbols-outlined text-red-600">warning</span>
                        </div>
                        <span class="text-xs font-bold text-red-600 bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded-full">High Priority</span>
                    </div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Urgent Risks Detected</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $urgent_count; ?></p>
                </div>
                
                <div class="bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <span class="material-symbols-outlined text-primary">event</span>
                        </div>
                        <span class="text-xs font-bold text-primary bg-primary/10 px-2 py-1 rounded-full">Today</span>
                    </div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Today's Schedule</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $today_count; ?> Appts</p>
                </div>
            </div>

            <section class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">recent_actors</span>
                        Recent Patients
                    </h2>
                    <button class="text-sm text-primary font-semibold hover:underline">View All Patients</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Patient Name</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Last Assessment</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Stroke Risk</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <?php 
                            if(mysqli_num_rows($recent_patients_result) > 0) {
                                while($row = mysqli_fetch_assoc($recent_patients_result)) {
                                    $name = htmlspecialchars($row['full_name']);
                                    $date = date('M d, Y', strtotime($row['created_at']));
                                    $risk = ucfirst($row['risk_level']); // Ensure Title Case
                                    $prob = round($row['probability'], 1);
                                    
                                    // Initials
                                    $pat_parts = explode(' ', $name);
                                    $pat_init = strtoupper(substr($pat_parts[0], 0, 1) . (isset($pat_parts[1]) ? substr($pat_parts[1], 0, 1) : ''));
                                    
                                    // Visual Logic
                                    $color_class = 'text-green-600 bg-green-50 dark:bg-green-900/20';
                                    $bar_color = 'bg-primary';
                                    $avatar_bg = 'bg-green-100 text-green-600';
                                    
                                    if(strtolower($risk) == 'high') {
                                        $color_class = 'text-red-600 bg-red-50 dark:bg-red-900/20';
                                        $bar_color = 'bg-red-500';
                                        $avatar_bg = 'bg-orange-100 text-orange-600';
                                    } elseif(strtolower($risk) == 'moderate') {
                                        $color_class = 'text-orange-600 bg-orange-50 dark:bg-orange-900/20';
                                        $bar_color = 'bg-orange-400';
                                        $avatar_bg = 'bg-blue-100 text-blue-600';
                                    }
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full <?php echo $avatar_bg; ?> flex items-center justify-center font-bold text-xs">
                                            <?php echo $pat_init; ?>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-800 dark:text-white"><?php echo $name; ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?php echo $date; ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full max-w-[100px]">
                                            <div class="h-full <?php echo $bar_color; ?> rounded-full" style="width: <?php echo min($prob, 100); ?>%"></div>
                                        </div>
                                        <span class="text-sm font-bold <?php echo str_replace('bg-', 'text-', $bar_color); ?>"><?php echo $prob; ?>%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-[10px] font-bold uppercase rounded-full <?php echo $color_class; ?>"><?php echo $risk; ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="text-primary font-semibold text-sm hover:underline">View Details</button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No recent patient assessments found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <section class="lg:col-span-2 bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Regional Risk Trends</h3>
                        <select class="text-xs bg-gray-50 dark:bg-gray-800 border-none rounded-lg text-gray-500">
                            <option>Last 30 days</option>
                            <option>Last 6 months</option>
                        </select>
                    </div>
                    <div class="h-48 w-full flex items-end justify-between gap-4 px-2">
                        <div class="flex-1 bg-primary/20 rounded-t-lg relative group h-[40%]"></div>
                        <div class="flex-1 bg-primary/20 rounded-t-lg relative group h-[35%]"></div>
                        <div class="flex-1 bg-primary/20 rounded-t-lg relative group h-[60%]"></div>
                        <div class="flex-1 bg-primary/20 rounded-t-lg relative group h-[55%]"></div>
                        <div class="flex-1 bg-primary/60 rounded-t-lg relative group h-[80%]"></div>
                        <div class="flex-1 bg-primary/20 rounded-t-lg relative group h-[45%]"></div>
                        <div class="flex-1 bg-primary/20 rounded-t-lg relative group h-[30%]"></div>
                    </div>
                    <div class="flex justify-between mt-4 text-[10px] text-gray-400 uppercase font-bold px-2">
                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                    </div>
                </section>
                
                <section class="bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">Upcoming Appointments</h3>
                    <div class="space-y-4">
                        <?php 
                        if(mysqli_num_rows($appointments_result) > 0) {
                            while($appt = mysqli_fetch_assoc($appointments_result)) {
                                $time_obj = new DateTime($appt['appointment_time']);
                                $time_display = $time_obj->format('h:i');
                                $ampm = $time_obj->format('A');
                        ?>
                        <div class="flex items-center gap-4 p-3 rounded-lg border border-gray-50 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <div class="text-center min-w-[50px]">
                                <p class="text-xs font-bold text-gray-400"><?php echo $time_display; ?></p>
                                <p class="text-[10px] text-gray-400"><?php echo $ampm; ?></p>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($appt['full_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($appt['type']); ?></p>
                            </div>
                            <span class="material-symbols-outlined text-gray-300">chevron_right</span>
                        </div>
                        <?php 
                            }
                        } else {
                            echo '<p class="text-sm text-gray-500 text-center py-4">No upcoming appointments.</p>';
                        }
                        ?>
                    </div>
                    <button class="w-full mt-6 py-3 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-sm font-semibold text-gray-500 hover:border-primary hover:text-primary transition-all">
                        + Add New Appointment
                    </button>
                </section>
            </div>
        </main>
    </div>

    <button class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-white rounded-full shadow-xl flex items-center justify-center hover:scale-110 transition-transform z-50">
        <span class="material-symbols-outlined">add</span>
    </button>

    <script>
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const logoutMenu = document.getElementById('logout-menu');
            if (!event.target.closest('.group')) {
                logoutMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>