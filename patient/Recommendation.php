<?php
session_start();
require_once 'db_conn.php';

// --- GET LOGGED-IN USER ID ---
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = 6; // Fallback for testing
    // header("Location: login.php"); // Uncomment in production
    // exit();
}

// --- FETCH PATIENT NAME ---
$sql_name = "SELECT full_name FROM patient_signup WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();

$patient_name = "Guest User";
if ($row_name = $result_name->fetch_assoc()) {
    $patient_name = $row_name['full_name'];
}
$stmt_name->close();
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Health Recommendations - NeuroNest</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<style type="text/tailwindcss">
    :root {
        --primary: #2D9F75;
        --primary-light: #E6F4EE;
        --bg-light: #F8FBF9;
    }
    body { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
    }
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .sidebar-active {
        @apply bg-[var(--primary-light)] text-[var(--primary)] border-l-4 border-[var(--primary)];
    }
    .progress-bar-animate {
        transition: width 0.5s ease-in-out;
    }
    .goal-card {
        transition: all 0.3s ease;
    }
    .goal-card:hover {
        transform: translateY(-2px);
    }
    @keyframes pulse-success {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .pulse-success {
        animation: pulse-success 0.5s ease-in-out;
    }
    .edit-mode .goal-card {
        border: 1px dashed var(--primary);
        background: #f0fdf4;
    }
    .dark .edit-mode .goal-card {
        background: #064e3b;
        border-color: #059669;
    }
</style>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#2D9F75",
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
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen transition-colors duration-200">

<div class="flex h-screen overflow-hidden">
    <aside class="w-64 bg-white dark:bg-surface-dark border-r border-gray-200 dark:border-gray-800 flex flex-col hidden lg:flex">
        <div class="p-6">
            <div class="flex flex-col items-center gap-3">
                <div class="p-4 bg-[var(--bg-light)] rounded-2xl w-full flex justify-center">
                    <img alt="NeuroNest Logo" class="w-16 h-16 rounded-full mb-2" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD08vK3RuRVPr4VLfS9NA66YU-jHnsXxx_GDi0hRAy5gUTFQzGJbGCX_v00FtADpWg3pPq24mphnahaaPqQb3100id1cTD6-_phwOZlS0KGUe-mYGEL6pPKAxtHy8QIVC5ShomIy0C8lPaegvgmEzP7G1R9u-QEfVVfDtV2fCFwyIny6XZJuOtkSLrjyLtsHOwSDKU8SZA_d6UIHCznruKu3WXwj2Zm9GcnH5q6GWmmJWmLzG9ZGovjV7EHnxZsWOjXCUjvtohPA7g"/>
                </div>
                <h1 class="text-2xl font-bold text-[var(--primary)] tracking-tight">NeuroNest</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 space-y-2 mt-4">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="dashboard.php">
                <span class="material-icons-outlined">home</span>
                <span class="font-medium">Home</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="chat.php">
                <span class="material-icons-outlined">chat</span>
                <span class="font-medium">Chat</span>
                <span class="ml-auto bg-slate-200 px-2 py-0.5 rounded-full text-xs">3</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="notes.php">
                <span class="material-icons-outlined">description</span>
                <span class="font-medium">Notes</span>
            </a>
            <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="Recommendation.php">
                <span class="material-icons-outlined">auto_awesome</span>
                <span class="font-medium">Recommendations</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="prediction.php">
                <span class="material-icons-outlined">psychology</span>
                <span class="font-medium">Prediction</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="community.php">
                <span class="material-icons-outlined">group</span>
                <span class="font-medium">Community</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="Emergency.php">
                <span class="material-icons-outlined">report_problem</span>
                <span class="font-medium text-red-500">Emergency</span>
            </a>
        </nav>

        <!-- ✅ UPDATED: Dynamic user name & avatar -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                    <img 
                        alt="User Profile" 
                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($patient_name); ?>&background=random"
                    />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($patient_name); ?></p>
                    <p class="text-xs text-slate-500 truncate">Patient Account</p>
                </div>
                <a href="logout.php"><span class="material-icons-outlined text-slate-400">logout</span></a>
            </div>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto p-4 md:p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Personalized Health Recommendations</h1>
                <p class="text-gray-500 dark:text-gray-400">Tailored lifestyle guides based on your stroke risk assessment</p>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 text-gray-400 hover:text-primary transition-colors">
                    <span class="material-icons">notifications</span>
                </button>
                <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                    <span class="material-icons">dark_mode</span>
                </button>
            </div>
        </header>

        <div class="flex flex-col xl:flex-row gap-8">
            <div class="flex-1 space-y-12">
                <section>
                    <div class="flex items-center gap-2 mb-6 border-b border-gray-200 dark:border-gray-800 pb-2">
                        <span class="material-symbols-outlined text-orange-500">restaurant</span>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Dietary Guidelines</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden group hover:shadow-md transition-shadow">
                            <img alt="Healthy Meal" class="w-full h-40 object-cover" src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=500"/>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Low-Sodium Diet Guide</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">Reduce hypertension risk by limiting salt intake. Learn how to use herbs and spices for flavor...</p>
                                <button onclick="openResource('dietary', 'low-sodium')" class="mt-4 text-sm font-semibold text-primary hover:underline flex items-center gap-1">Read More <span class="material-symbols-outlined text-sm">arrow_forward</span></button>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden group hover:shadow-md transition-shadow">
                            <img alt="Heart Healthy" class="w-full h-40 object-cover" src="https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&q=80&w=500"/>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Mediterranean Meal Plan</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">Rich in healthy fats and lean proteins. Essential for maintaining optimal cardiovascular health...</p>
                                <button onclick="openResource('dietary', 'mediterranean')" class="mt-4 text-sm font-semibold text-primary hover:underline flex items-center gap-1">Read More <span class="material-symbols-outlined text-sm">arrow_forward</span></button>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="flex items-center gap-2 mb-6 border-b border-gray-200 dark:border-gray-800 pb-2">
                        <span class="material-symbols-outlined text-blue-500">fitness_center</span>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Physical Activity</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden group hover:shadow-md transition-shadow">
                            <img alt="Walking" class="w-full h-40 object-cover" src="https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?auto=format&fit=crop&q=80&w=500"/>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Cardio for Beginners</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">Simple walking routines designed to strengthen your heart without overexertion...</p>
                                <button onclick="openResource('exercise', 'cardio')" class="mt-4 text-sm font-semibold text-primary hover:underline flex items-center gap-1">Watch Tutorial <span class="material-symbols-outlined text-sm">play_circle</span></button>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden group hover:shadow-md transition-shadow">
                            <img alt="Stretching" class="w-full h-40 object-cover" src="https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&q=80&w=500"/>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Daily Flexibility Routine</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">10-minute morning stretching to improve circulation and reduce vascular stiffness...</p>
                                <button onclick="openResource('exercise', 'stretching')" class="mt-4 text-sm font-semibold text-primary hover:underline flex items-center gap-1">View Guide <span class="material-symbols-outlined text-sm">arrow_forward</span></button>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="flex items-center gap-2 mb-6 border-b border-gray-200 dark:border-gray-800 pb-2">
                        <span class="material-symbols-outlined text-purple-500">spa</span>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Mental Wellbeing</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden group hover:shadow-md transition-shadow">
                            <img alt="Meditation" class="w-full h-40 object-cover" src="https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&q=80&w=500"/>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Mindfulness Meditation</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">Combat stress-induced hypertension with daily breathing exercises and mindfulness...</p>
                                <button onclick="openResource('mental', 'meditation')" class="mt-4 text-sm font-semibold text-primary hover:underline flex items-center gap-1">Listen Now <span class="material-symbols-outlined text-sm">headphones</span></button>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden group hover:shadow-md transition-shadow">
                            <img alt="Sleep" class="w-full h-40 object-cover" src="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&q=80&w=500"/>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-800 dark:text-white mb-2">Sleep Hygiene</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">The relationship between deep sleep and brain health. Tips for a better rest cycle...</p>
                                <button onclick="openResource('mental', 'sleep')" class="mt-4 text-sm font-semibold text-primary hover:underline flex items-center gap-1">Read More <span class="material-symbols-outlined text-sm">arrow_forward</span></button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="w-full xl:w-80 space-y-6">
                <div id="goalTrackerPanel" class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 sticky top-8 transition-colors duration-300">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">Daily Goal Tracker</h3>
                    
                    <div id="goalsContainer" class="space-y-6">
                        <div class="goal-card" data-goal="hydration">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-blue-400 text-lg">water_drop</span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hydration</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="goal-value text-xs font-bold text-primary">
                                        <span id="hydration-current">6</span> / <span id="hydration-target">8</span> glasses
                                    </span>
                                    <div class="goal-controls hidden flex gap-1">
                                        <button onclick="updateGoal('hydration', -1)" class="text-red-500 bg-red-50 hover:bg-red-100 rounded p-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm">remove</span>
                                        </button>
                                        <button onclick="updateGoal('hydration', 1)" class="text-green-500 bg-green-50 hover:bg-green-100 rounded p-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm">add</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                                <div id="hydration-progress" class="bg-blue-400 h-full rounded-full progress-bar-animate" style="width: 75%"></div>
                            </div>
                        </div>

                        <div class="goal-card" data-goal="steps">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-orange-400 text-lg">directions_walk</span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Steps</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="goal-value text-xs font-bold text-primary">
                                        <span id="steps-current">4,230</span> / <span id="steps-target">10,000</span>
                                    </span>
                                    <div class="goal-controls hidden flex gap-1">
                                        <button onclick="updateGoal('steps', -100)" class="text-red-500 bg-red-50 hover:bg-red-100 rounded p-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm">remove</span>
                                        </button>
                                        <button onclick="updateGoal('steps', 100)" class="text-green-500 bg-green-50 hover:bg-green-100 rounded p-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm">add</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                                <div id="steps-progress" class="bg-orange-400 h-full rounded-full progress-bar-animate" style="width: 42%"></div>
                            </div>
                        </div>

                        <div class="goal-card" data-goal="meditation">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-purple-400 text-lg">self_improvement</span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Meditation</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="goal-value text-xs font-bold text-primary">
                                        <span id="meditation-current">10</span> / <span id="meditation-target">20</span> min
                                    </span>
                                    <div class="goal-controls hidden flex gap-1">
                                        <button onclick="updateGoal('meditation', -1)" class="text-red-500 bg-red-50 hover:bg-red-100 rounded p-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm">remove</span>
                                        </button>
                                        <button onclick="updateGoal('meditation', 1)" class="text-green-500 bg-green-50 hover:bg-green-100 rounded p-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm">add</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                                <div id="meditation-progress" class="bg-purple-400 h-full rounded-full progress-bar-animate" style="width: 50%"></div>
                            </div>
                        </div>
                    </div>

                    <button id="updateGoalsBtn" onclick="toggleEditMode()" class="w-full mt-8 py-3 bg-primary text-white text-sm font-bold rounded-xl hover:bg-opacity-90 transition-all shadow-lg shadow-primary/20">
                        Edit Goals
                    </button>

                    <div id="successMessage" class="hidden mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
                        <p class="text-xs text-green-700 dark:text-green-400 text-center font-medium">✓ Goals updated successfully!</p>
                    </div>

                    <div class="mt-8 p-4 bg-primary/5 rounded-xl border border-primary/10">
                        <p class="text-xs text-primary font-bold uppercase tracking-wider mb-2">Pro Tip</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            Consistent daily habits reduce stroke risk by up to 80% over 10 years. Keep up the good work!
                        </p>
                    </div>
                </div>

                <div class="bg-surface-dark/5 dark:bg-surface-dark border-dashed border-2 border-gray-200 dark:border-gray-800 p-6 rounded-xl">
                    <h4 class="text-sm font-bold text-gray-800 dark:text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">edit_note</span> Doctor's Note
                    </h4>
                    <p class="text-xs text-gray-500 italic">"Focus on the low-sodium guide this week to help stabilize your morning BP readings."</p>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="fixed bottom-6 right-6 max-w-sm bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 p-4 rounded-xl shadow-xl flex gap-3 z-50">
    <span class="material-symbols-outlined text-amber-500">warning</span>
    <div>
        <p class="text-sm font-bold text-amber-800 dark:text-amber-200">Medical Disclaimer</p>
        <p class="text-xs text-amber-700 dark:text-amber-400/80">Recommendations are for educational purposes and should not replace clinical medical advice.</p>
    </div>
</div>

<script>
// Resource Links Database
const resourceLinks = {
    dietary: {
        'low-sodium': 'https://www.heart.org/en/healthy-living/healthy-eating/eat-smart/sodium/sodium-and-salt',
        'mediterranean': 'https://www.mayoclinic.org/healthy-lifestyle/nutrition-and-healthy-eating/in-depth/mediterranean-diet/art-20047801'
    },
    exercise: {
        'cardio': 'https://www.heart.org/en/healthy-living/fitness/walking',
        'stretching': 'https://www.health.harvard.edu/staying-healthy/the-importance-of-stretching'
    },
    mental: {
        'meditation': 'https://www.uclahealth.org/programs/marc/free-guided-meditations',
        'sleep': 'https://www.sleepfoundation.org/sleep-hygiene'
    }
};

// Open Resource Function
function openResource(category, key) {
    const url = resourceLinks[category] && resourceLinks[category][key];
    if (url) {
        window.open(url, '_blank');
    } else {
        alert("Resource link not found.");
    }
}

// Goal Tracker State
let isEditMode = false;
const goals = {
    hydration: { current: 6, target: 8, step: 1 },
    steps: { current: 4230, target: 10000, step: 100 },
    meditation: { current: 10, target: 20, step: 5 }
};

// Toggle Edit Mode
function toggleEditMode() {
    isEditMode = !isEditMode;
    const panel = document.getElementById('goalTrackerPanel');
    const btn = document.getElementById('updateGoalsBtn');
    const controls = document.querySelectorAll('.goal-controls');
    
    if (isEditMode) {
        panel.classList.add('edit-mode');
        btn.textContent = 'Save Changes';
        btn.classList.remove('bg-primary');
        btn.classList.add('bg-slate-700');
        controls.forEach(el => el.classList.remove('hidden'));
        document.getElementById('successMessage').classList.add('hidden');
    } else {
        panel.classList.remove('edit-mode');
        btn.textContent = 'Edit Goals';
        btn.classList.add('bg-primary');
        btn.classList.remove('bg-slate-700');
        controls.forEach(el => el.classList.add('hidden'));
        
        // Show success animation
        const msg = document.getElementById('successMessage');
        msg.classList.remove('hidden');
        msg.classList.add('pulse-success');
        setTimeout(() => msg.classList.add('hidden'), 3000);
    }
}

// Update Goal Values
function updateGoal(type, change) {
    const goal = goals[type];
    
    let newVal = goal.current + change;
    if (newVal < 0) newVal = 0;
    if (newVal > goal.target * 1.5) newVal = goal.target * 1.5;
    goal.current = newVal;

    document.getElementById(`${type}-current`).textContent = newVal.toLocaleString();
    
    let percentage = (newVal / goal.target) * 100;
    if (percentage > 100) percentage = 100;
    
    document.getElementById(`${type}-progress`).style.width = `${percentage}%`;
}
</script>
</body>
</html>