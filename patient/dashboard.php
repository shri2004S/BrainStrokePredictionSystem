<?php
// --- 1. PHP DATABASE CONNECTION (Top of File) ---
session_start();

// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "brain";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 2. GET LOGGED-IN USER ID (DYNAMIC) ---
// Check if user is logged in via session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    // Fallback for testing (Remove or comment out in production)
    $user_id = 6; 
    // header("Location: login.php"); // Uncomment in production to force login
    // exit();
}

// --- 3. FETCH PATIENT DATA ---
// A. Fetch Name
$sql = "SELECT full_name FROM patient_signup WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$patient_name = "Guest User";
$first_name = "Guest";

if ($row = $result->fetch_assoc()) {
    $patient_name = $row['full_name'];
    $name_parts = explode(" ", $patient_name);
    $first_name = $name_parts[0]; 
}
$stmt->close();

$pred_sql = "
    SELECT probability, risk_level, created_at 
    FROM prediction_history 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
";

$pred_stmt = $conn->prepare($pred_sql);
$pred_stmt->bind_param("i", $user_id);
$pred_stmt->execute();
$pred_result = $pred_stmt->get_result();

$current_probability = "0.0";
$current_risk_level = "Low";
$gauge_progress = 0;
$prediction_date = "No prediction yet";

$risk_color_class = "text-emerald-500";
$gauge_stroke_color = "#10B981";

if ($row = $pred_result->fetch_assoc()) {

    $display_probability = floatval($row['probability']); // already %

    $current_probability = number_format($display_probability, 1);
    $gauge_progress = min($display_probability, 100);
    $current_risk_level = ucfirst($row['risk_level']);
    $prediction_date = date('M d, Y', strtotime($row['created_at']));

    if ($display_probability >= 30) {
        $risk_color_class = "text-red-500";
        $gauge_stroke_color = "#EF4444";
    } elseif ($display_probability >= 15) {
        $risk_color_class = "text-orange-500";
        $gauge_stroke_color = "#F97316";
    }
}

$pred_stmt->close();
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>NeuroNest | <?php echo htmlspecialchars($patient_name); ?> Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <style type="text/tailwindcss">
        :root {
            --primary: #2D9F75;
            --primary-light: #E6F4EE;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .sidebar-active {
            @apply bg-[var(--primary-light)] text-[var(--primary)] border-l-4 border-[var(--primary)];
        }
        .mood-bar {
            @apply w-10 rounded-t-xl transition-all duration-300;
        }
        .modal {
            @apply hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center;
        }
        .modal.active {
            @apply flex;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#2D9F75",
                    },
                    borderRadius: {
                        'xl': '1rem',
                        '2xl': '1.5rem',
                        '3xl': '2rem',
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen">
    
    <div id="goalModal" class="modal">
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-8 border-b border-gray-100">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">Edit My Goals</h2>
                        <p class="text-sm text-slate-500 mt-1">Update your daily progress & targets</p>
                    </div>
                    <button onclick="closeGoalModal()" class="p-2 hover:bg-gray-100 rounded-xl transition-all">
                        <span class="material-symbols-outlined text-slate-600">close</span>
                    </button>
                </div>
            </div>
            
            <div id="goalsList" class="p-8 space-y-6">
            </div>
            
            <div class="p-8 border-t border-gray-100 flex gap-4">
                <button onclick="closeGoalModal()" class="flex-1 py-3 border-2 border-gray-200 text-slate-600 font-semibold rounded-2xl hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button onclick="saveGoals()" class="flex-1 py-3 bg-primary text-white font-semibold rounded-2xl hover:bg-primary/90 transition-all">
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <div class="flex">
        <aside class="w-64 bg-white border-r border-gray-200 min-h-screen fixed left-0 top-0 z-30 flex flex-col">
            <div class="p-6">
                <div class="flex flex-col items-center gap-3">
                    <div class="p-4 bg-gray-50 rounded-2xl w-full flex justify-center">
                        <img alt="NeuroNest Logo" class="w-16 h-16 rounded-full mb-2" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD08vK3RuRVPr4VLfS9NA66YU-jHnsXxx_GDi0hRAy5gUTFQzGJbGCX_v00FtADpWg3pPq24mphnahaaPqQb3100id1cTD6-_phwOZlS0KGUe-mYGEL6pPKAxtHy8QIVC5ShomIy0C8lPaegvgmEzP7G1R9u-QEfVVfDtV2fCFwyIny6XZJuOtkSLrjyLtsHOwSDKU8SZA_d6UIHCznruKu3WXwj2Zm9GcnH5q6GWmmJWmLzG9ZGovjV7EHnxZsWOjXCUjvtohPA7g"/>
                    </div>
                    <h1 class="text-2xl font-bold text-[var(--primary)] tracking-tight">NeuroNest</h1>
                </div>
            </div>
            <nav class="flex-1 px-4 space-y-2 mt-4">
                <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="#">
                    <span class="material-icons-outlined">home</span>
                    <span class="font-medium">Home</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl transition-colors" href="chat.php">
                    <span class="material-icons-outlined">chat</span>
                    <span class="font-medium">Chat</span>
                    <span class="ml-auto bg-gray-200 px-2 py-0.5 rounded-full text-xs">3</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl transition-colors" href="notes.php">
                    <span class="material-icons-outlined">description</span>
                    <span class="font-medium">Notes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl transition-colors" href="Recommendation.php">
                    <span class="material-icons-outlined">auto_awesome</span>
                    <span class="font-medium">Recommendations</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl transition-colors" href="prediction.php">
                    <span class="material-icons-outlined">psychology</span>
                    <span class="font-medium">Prediction</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl transition-colors" href="community.php">
                    <span class="material-icons-outlined">group</span>
                    <span class="font-medium">Community</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-gray-50 rounded-xl transition-colors" href="Emergency.php">
                    <span class="material-icons-outlined">report_problem</span>
                    <span class="font-medium text-red-500">Emergency</span>
                </a>
            </nav>
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-50 cursor-pointer">
                    <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden">
                        <img alt="User Profile" src="https://ui-avatars.com/api/?name=<?php echo urlencode($patient_name); ?>&background=random"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($patient_name); ?></p>
                        <p class="text-xs text-slate-500 truncate">Patient Account</p>
                    </div>
                    <span class="material-icons-outlined text-slate-400">settings</span>
                </div>
            </div>
        </aside>
        
        <main class="ml-64 flex-1 p-10 max-w-[1400px]">
            <header class="flex justify-between items-center mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Welcome back, <?php echo htmlspecialchars($first_name); ?>! 👋</h1>
                    <p class="text-slate-500 mt-1">Everything looks steady. Here is your daily overview.</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative group cursor-pointer p-3 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <span class="material-symbols-outlined text-slate-600">notifications</span>
                        <span class="absolute top-3 right-3 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                    </div>
                    <button class="flex items-center gap-2 px-4 py-3 bg-white border border-gray-200 rounded-2xl shadow-sm font-semibold text-sm" onclick="document.documentElement.classList.toggle('dark')">
                        <span class="material-symbols-outlined text-slate-600">dark_mode</span>
                    </button>
                </div>
            </header>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                
                <!-- UPDATED STROKE RISK CARD -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Stroke Risk Level</h3>
                            <p class="text-xs text-slate-400">Last updated: <?php echo htmlspecialchars($prediction_date); ?></p>
                        </div>
                        <button class="p-2 hover:bg-gray-50 rounded-full transition-all">
                            <span class="material-symbols-outlined text-slate-300 text-sm">info</span>
                        </button>
                    </div>
                    
                    <!-- Partial Arc Gauge with Percentage -->
                    <div class="flex items-center justify-center my-10">
                        <div class="relative" style="width: 280px; height: 160px;">
                            <!-- SVG Half Circle Gauge -->
                            <svg class="w-full h-full" viewBox="0 0 200 120" style="overflow: visible;">
                                <!-- Background arc (180 degrees) -->
                                <path
                                    d="M 20,100 A 80,80 0 0,1 180,100"
                                    fill="none"
                                    stroke="#E8EAF0"
                                    stroke-width="16"
                                    stroke-linecap="round"
                                />
                                <!-- Progress arc -->
                                <?php 
                                    // Calculate the arc for progress (0-180 degrees based on percentage)
                                    $progressAngle = ($gauge_progress / 100) * 180;
                                    $endX = 100 + 80 * cos(pi() - ($progressAngle * pi() / 180));
                                    $endY = 100 - 80 * sin(pi() - ($progressAngle * pi() / 180));
                                    $largeArcFlag = $progressAngle > 180 ? 1 : 0;
                                ?>
                                <path
                                    d="M 20,100 A 80,80 0 0,1 <?php echo $endX . ',' . $endY; ?>"
                                    fill="none"
                                    stroke="<?php echo $gauge_stroke_color; ?>"
                                    stroke-width="16"
                                    stroke-linecap="round"
                                    class="transition-all duration-1000 ease-out"
                                />
                                <!-- End indicator dot -->
                                <circle 
                                    cx="<?php echo $endX; ?>" 
                                    cy="<?php echo $endY; ?>" 
                                    r="8" 
                                    fill="<?php echo $gauge_stroke_color; ?>"
                                    class="transition-all duration-1000"
                                />
                            </svg>
                            
                            <!-- Centered Percentage and Dot -->
                            <div class="absolute" style="bottom: 20px; left: 50%; transform: translateX(-50%);">
                                <div class="flex items-center gap-3 justify-center">
                                    <!-- Status indicator dot -->
                                    <div class="w-3.5 h-3.5 rounded-full <?php 
                                        if ($gauge_progress >= 66) echo 'bg-red-500';
                                        elseif ($gauge_progress >= 33) echo 'bg-orange-400';
                                        else echo 'bg-emerald-500';
                                    ?>"></div>
                                    <!-- Large percentage -->
                                    <span class="text-5xl font-bold <?php echo $risk_color_class; ?>">
                                        <?php echo htmlspecialchars($current_probability); ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Risk Status Message -->
                    <div class="text-center mb-6 px-6">
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Your latest prediction indicates a <strong class="<?php echo $risk_color_class; ?>"><?php echo $current_risk_level; ?> Risk</strong>. Keep maintaining your healthy habits.
                        </p>
                    </div>
                    
                    <!-- See Details Link -->
                    <div class="text-center">
                        <button onclick="window.location.href='prediction.php'" class="text-primary font-semibold text-sm inline-flex items-center gap-2 hover:gap-3 transition-all group">
                            See Details
                            <span class="material-symbols-outlined text-base group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </button>
                    </div>
                </div>
                
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex flex-col">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xl font-bold text-slate-800">Daily Goal Tracker</h3>
                    </div>
                    
                    <div id="goalsProgress" class="space-y-6 flex-1">
                    </div>
                    
                    <button onclick="openGoalModal()" class="w-full mt-8 py-3.5 bg-primary text-white font-bold rounded-2xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                        Edit Goals
                    </button>

                    <div class="mt-8 bg-emerald-50 rounded-2xl p-5 border border-emerald-100">
                         <h4 class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-2">Pro Tip</h4>
                         <p class="text-sm text-slate-600 leading-relaxed">
                           Consistent daily habits reduce stroke risk by up to 80% over 10 years. Keep up the momentum!
                         </p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
                <div class="lg:col-span-2 bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-10">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900">Weekly Mood Journey</h3>
                            <p class="text-sm text-slate-400">Track your emotional recovery</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="p-2 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </button>
                            <button class="p-2 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-between items-end h-48 px-4">
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">😊</span>
                            <div class="mood-bar bg-gray-100 h-20"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Mon</span>
                        </div>
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">😐</span>
                            <div class="mood-bar bg-gray-100 h-16"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Tue</span>
                        </div>
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">😔</span>
                            <div class="mood-bar bg-gray-100 h-12"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Wed</span>
                        </div>
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">😊</span>
                            <div class="mood-bar bg-gray-100 h-24"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Thu</span>
                        </div>
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">🤩</span>
                            <div class="mood-bar bg-primary h-40"></div>
                            <span class="text-[10px] font-bold text-primary uppercase">Fri</span>
                        </div>
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">😊</span>
                            <div class="mood-bar bg-gray-100 h-22"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Sat</span>
                        </div>
                        <div class="flex flex-col items-center gap-3 flex-1">
                            <span class="text-2xl">😐</span>
                            <div class="mood-bar bg-gray-100 h-14"></div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Sun</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-primary p-8 rounded-3xl shadow-lg text-white flex flex-col">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-white">lightbulb</span>
                        </div>
                        <h3 class="font-bold text-lg">Daily Tip</h3>
                    </div>
                    <p class="text-white/90 text-sm leading-relaxed mb-8">
                        "Increasing your magnesium intake can help improve neuroplasticity and overall brain health. Try adding some spinach to your lunch today!"
                    </p>
                    <div class="space-y-4 mt-auto">
                        <div class="bg-white/10 rounded-2xl p-4 flex items-center gap-4 cursor-pointer hover:bg-white/20 transition-all border border-white/10">
                            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm">self_improvement</span>
                            </div>
                            <div class="text-left">
                                <p class="text-xs font-bold">Activity</p>
                                <p class="text-xs text-white/70">5m Mindfulness</p>
                            </div>
                        </div>
                        <div class="bg-white/10 rounded-2xl p-4 flex items-center gap-4 cursor-pointer hover:bg-white/20 transition-all border border-white/10">
                            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm">menu_book</span>
                            </div>
                            <div class="text-left">
                                <p class="text-xs font-bold">Read</p>
                                <p class="text-xs text-white/70">Neural Recovery Signs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="group bg-white p-6 rounded-3xl shadow-sm border border-gray-100 hover:border-primary transition-all cursor-pointer text-center">
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined">forum</span>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-1">Community</h4>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Connect</p>
                </div>
                <div class="group bg-white p-6 rounded-3xl shadow-sm border border-gray-100 hover:border-primary transition-all cursor-pointer text-center">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined">edit_note</span>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-1">Journal</h4>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Daily Log</p>
                </div>
                <div class="group bg-white p-6 rounded-3xl shadow-sm border border-gray-100 hover:border-primary transition-all cursor-pointer text-center">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined">fitness_center</span>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-1">Rehab</h4>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Exercises</p>
                </div>
                <div class="group bg-white p-6 rounded-3xl shadow-sm border border-gray-100 hover:border-primary transition-all cursor-pointer text-center">
                    <div class="w-12 h-12 bg-slate-50 text-slate-600 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined">settings</span>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-1">Settings</h4>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Config</p>
                </div>
            </div>
        </main>
    </div>

    <script>
        let goalsData = [];

        const goalConfig = {
            'hydration': { 
                icon: 'water_drop', 
                color: 'bg-blue-500', 
                iconColor: 'text-blue-500' 
            },
            'steps': { 
                icon: 'directions_walk', 
                color: 'bg-orange-400', 
                iconColor: 'text-orange-400' 
            },
            'meditation': { 
                icon: 'self_improvement', 
                color: 'bg-purple-400', 
                iconColor: 'text-purple-400' 
            }
        };

        const defaultGoalConfig = {
            icon: 'bar_chart',
            color: 'bg-emerald-500',
            iconColor: 'text-emerald-500'
        };

        document.addEventListener('DOMContentLoaded', () => {
            loadGoals();
        });

        async function loadGoals() {
            try {
                // MOCK DATA (Replace with actual fetch call if needed)
                const data = {
                    success: true,
                    goals: [
                        { id: 1, goal_type: 'hydration', current_value: 6, target_value: 8, unit: 'glasses' },
                        { id: 2, goal_type: 'steps', current_value: 4230, target_value: 10000, unit: '' },
                        { id: 3, goal_type: 'meditation', current_value: 10, target_value: 20, unit: 'min' }
                    ]
                };

                if(data.success) {
                    goalsData = data.goals;
                    updateGoalsDisplay();
                }
            } catch(error) {
                console.error('Error loading goals:', error);
            }
        }

        function updateGoalsDisplay() {
            const goalsProgress = document.getElementById('goalsProgress');
            const goalsList = document.getElementById('goalsList');
            
            if(!goalsData || goalsData.length === 0) {
                goalsProgress.innerHTML = '<p class="text-center text-slate-400">No goals set yet</p>';
                return;
            }

            let progressHTML = '';
            let listHTML = '';

            goalsData.forEach(goal => {
                const safeTarget = goal.target_value || 1; 
                const progress = (goal.current_value / safeTarget) * 100;
                
                const config = goalConfig[goal.goal_type.toLowerCase()] || defaultGoalConfig;
                const currentFormatted = goal.current_value.toLocaleString();
                const targetFormatted = goal.target_value.toLocaleString();

                // Dashboard Progress Bars
                progressHTML += `
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined ${config.iconColor} text-xl">${config.icon}</span>
                                <span class="text-slate-700 font-medium capitalize text-sm">${goal.goal_type}</span>
                            </div>
                            <span class="text-primary font-bold text-sm">
                                ${currentFormatted} <span class="text-slate-300 font-normal">/</span> ${targetFormatted} <span class="text-xs text-primary/70">${goal.unit}</span>
                            </span>
                        </div>
                        <div class="h-2.5 bg-gray-50 rounded-full overflow-hidden">
                            <div class="h-full ${config.color} rounded-full transition-all duration-700 ease-out" style="width: ${Math.min(progress, 100)}%"></div>
                        </div>
                    </div>
                `;

                // Modal Input Fields
                listHTML += `
                    <div class="bg-gray-50 rounded-2xl p-5 border-2 border-gray-100 hover:border-primary transition-all">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-xl shadow-sm">
                                <span class="material-symbols-outlined ${config.iconColor}">${config.icon}</span>
                            </div>
                            <h4 class="font-bold text-slate-900 capitalize">${goal.goal_type}</h4>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Current</label>
                                <input type="number" id="current-${goal.id}" value="${goal.current_value}" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-primary focus:outline-none font-semibold text-slate-700"/>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Target</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" id="target-${goal.id}" value="${goal.target_value}" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-primary focus:outline-none font-semibold text-slate-700"/>
                                    <span class="text-xs font-bold text-slate-400 shrink-0">${goal.unit}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            goalsProgress.innerHTML = progressHTML;
            goalsList.innerHTML = listHTML;
        }

        function openGoalModal() {
            document.getElementById('goalModal').classList.add('active');
        }

        function closeGoalModal() {
            document.getElementById('goalModal').classList.remove('active');
        }

        async function saveGoals() {
            // Retrieve values from the inputs
            const updatedGoals = goalsData.map(goal => {
                const currentInput = document.getElementById(`current-${goal.id}`);
                const targetInput = document.getElementById(`target-${goal.id}`);
                
                return {
                    id: goal.id,
                    goal_type: goal.goal_type,
                    unit: goal.unit,
                    current_value: currentInput ? (parseFloat(currentInput.value) || 0) : goal.current_value,
                    target_value: targetInput ? (parseFloat(targetInput.value) || 0) : goal.target_value
                };
            });

            // Update local data and re-render
            goalsData = updatedGoals;
            updateGoalsDisplay();
            closeGoalModal();
            showNotification('Goals updated successfully!', 'success');
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-8 right-8 px-6 py-4 rounded-2xl shadow-lg z-50 transition-all transform ${
                type === 'success' ? 'bg-primary text-white' : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined">${type === 'success' ? 'check_circle' : 'error'}</span>
                    <span class="font-semibold">${message}</span>
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Close modal when clicking outside content area
        document.getElementById('goalModal').addEventListener('click', (e) => {
            if(e.target.id === 'goalModal') {
                closeGoalModal();
            }
        });

        // Auto-refresh prediction data every 5 seconds
        setInterval(() => {
            fetch('fetch_latest_prediction.php')
                .then(res => res.json())
                .then(data => {
                    if(data.probability) {
                        location.reload(); // Reload to update all risk displays
                    }
                })
                .catch(err => console.error('Error fetching prediction:', err));
        }, 5000);
    </script>
</body>
</html>