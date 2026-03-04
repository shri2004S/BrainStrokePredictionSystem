<?php
session_start();

// Include database connection
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
$patient_name = "Guest User";
$sql_name = "SELECT full_name FROM patient_signup WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
if ($row_name = $result_name->fetch_assoc()) {
    $patient_name = $row_name['full_name'];
}
$stmt_name->close();
?>

<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Emergency Response - NeuroNest</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#2D9F75",
                        emergency: "#EF4444",
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
    </style>
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
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="Recommendation.php">
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
<a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="Emergency.php">
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
<span class="material-icons-outlined text-slate-400">settings</span>
</div>
</div>
</aside>
<main class="flex-1 overflow-y-auto p-4 md:p-8">
<header class="flex justify-between items-center mb-8">
<div>
<h1 class="text-3xl font-bold text-gray-800 dark:text-white font-display">Stroke Awareness & Emergency Response</h1>
<p class="text-gray-500 dark:text-gray-400">Immediate action saves lives. Learn the signs and find help fast.</p>
</div>
<div class="flex items-center gap-4">
<button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
<span class="material-symbols-outlined">dark_mode</span>
</button>
<button class="px-6 py-2.5 bg-emergency text-white font-bold rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 flex items-center gap-2">
<span class="material-symbols-outlined text-xl">call</span>
                    Call Emergency Services
                </button>
</div>
</header>
<div class="max-w-6xl mx-auto space-y-8">
<section>
<div class="flex items-center gap-3 mb-6">
<div class="bg-primary/10 p-2 rounded-lg">
<span class="material-symbols-outlined text-primary">info</span>
</div>
<h2 class="text-2xl font-bold text-gray-800 dark:text-white">Identify the Signs: B.E. F.A.S.T.</h2>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
<div class="be-fast-card bg-white dark:bg-surface-dark p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm transition-all hover:shadow-md">
<div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center mb-4 symbol-icon">
<span class="material-symbols-outlined text-blue-600">accessibility_new</span>
</div>
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><span class="text-blue-600">B</span>alance</h3>
<p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Sudden loss of balance, dizziness, or headache.</p>
</div>
<div class="be-fast-card bg-white dark:bg-surface-dark p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm transition-all hover:shadow-md">
<div class="w-12 h-12 rounded-full bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center mb-4 symbol-icon">
<span class="material-symbols-outlined text-purple-600">visibility</span>
</div>
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><span class="text-purple-600">E</span>yes</h3>
<p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Sudden loss of vision in one or both eyes.</p>
</div>
<div class="be-fast-card bg-white dark:bg-surface-dark p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm transition-all hover:shadow-md">
<div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center mb-4 symbol-icon">
<span class="material-symbols-outlined text-amber-600">face</span>
</div>
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><span class="text-amber-600">F</span>ace</h3>
<p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Does one side of the face droop or feel numb?</p>
</div>
<div class="be-fast-card bg-white dark:bg-surface-dark p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm transition-all hover:shadow-md">
<div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center mb-4 symbol-icon">
<span class="material-symbols-outlined text-orange-600">front_hand</span>
</div>
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><span class="text-orange-600">A</span>rms</h3>
<p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Is one arm weak or numb? Ask the person to raise both arms.</p>
</div>
<div class="be-fast-card bg-white dark:bg-surface-dark p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm transition-all hover:shadow-md">
<div class="w-12 h-12 rounded-full bg-pink-50 dark:bg-pink-900/20 flex items-center justify-center mb-4 symbol-icon">
<span class="material-symbols-outlined text-pink-600">record_voice_over</span>
</div>
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><span class="text-pink-600">S</span>peech</h3>
<p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Is speech slurred? Are they unable to speak or hard to understand?</p>
</div>
<div class="be-fast-card bg-emergency/5 dark:bg-emergency/10 p-5 rounded-xl border-2 border-emergency/20 shadow-sm transition-all hover:shadow-md">
<div class="w-12 h-12 rounded-full bg-emergency/10 flex items-center justify-center mb-4 symbol-icon">
<span class="material-symbols-outlined text-emergency">schedule</span>
</div>
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><span class="text-emergency">T</span>ime</h3>
<p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">Time to call 911! Note the time symptoms started.</p>
</div>
</div>
</section>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
<div class="lg:col-span-2 bg-white dark:bg-surface-dark rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden flex flex-col h-[500px]">
<div class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/20">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary">map</span>
<h3 class="font-bold text-gray-800 dark:text-white">Stroke-Ready Hospitals Nearby</h3>
</div>
<div class="flex gap-2">
<button class="px-3 py-1 text-xs bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-sm">Street</button>
<button class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-600 text-gray-500 rounded-md">Satellite</button>
</div>
</div>
<div class="flex-1 relative bg-gray-200 dark:bg-gray-900 group overflow-hidden">

</div>
</div>
<div class="bg-white dark:bg-surface-dark rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm p-6 overflow-y-auto h-[500px]">
<h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Closest Facilities</h3>
<div class="space-y-4">
<div class="p-4 rounded-xl border border-primary/20 bg-primary/5">
<div class="flex justify-between items-start mb-2">
<h4 class="font-bold text-gray-800 dark:text-white">Central Medical Center</h4>
<span class="text-xs font-bold text-primary bg-white dark:bg-gray-800 px-2 py-1 rounded-full shadow-sm">0.8 mi</span>
</div>
<p class="text-xs text-gray-500 dark:text-gray-400 mb-3">123 Health Ave, Downtown</p>
<div class="flex items-center gap-2 mb-3">
<span class="text-[10px] uppercase font-bold px-2 py-0.5 bg-green-500 text-white rounded">Level 1 Trauma</span>
<span class="text-[10px] uppercase font-bold px-2 py-0.5 bg-blue-500 text-white rounded">Stroke Gold Plus</span>
</div>
<button onclick="openGoogleMaps('Central Medical Center', '123 Health Ave, Downtown')" class="w-full py-2 bg-primary text-white text-sm font-bold rounded-lg hover:bg-opacity-90 transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">directions</span>
                                Start Navigation
                            </button>
</div>
<div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800">
<div class="flex justify-between items-start mb-2">
<h4 class="font-bold text-gray-800 dark:text-white">Northside Neurology</h4>
<span class="text-xs font-bold text-gray-500 bg-gray-50 dark:bg-gray-800 px-2 py-1 rounded-full">2.4 mi</span>
</div>
<p class="text-xs text-gray-500 dark:text-gray-400 mb-3">456 Parkway Blvd, North District</p>
<button onclick="openGoogleMaps('Northside Neurology', '456 Parkway Blvd, North District')" class="w-full py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm font-bold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">directions</span>
                                Directions
                            </button>
</div>
<div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800">
<div class="flex justify-between items-start mb-2">
<h4 class="font-bold text-gray-800 dark:text-white">Mercy General Hospital</h4>
<span class="text-xs font-bold text-gray-500 bg-gray-50 dark:bg-gray-800 px-2 py-1 rounded-full">4.1 mi</span>
</div>
<p class="text-xs text-gray-500 dark:text-gray-400 mb-3">789 River Rd, Eastside</p>
<button onclick="openGoogleMaps('Mercy General Hospital', '789 River Rd, Eastside')" class="w-full py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm font-bold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">directions</span>
                                Directions
                            </button>
</div>
</div>
</div>
</div>
<section class="bg-white dark:bg-surface-dark rounded-2xl p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
<div class="flex items-center gap-3 mb-6">
<span class="material-symbols-outlined text-primary text-3xl">medical_services</span>
<h3 class="text-2xl font-bold text-gray-800 dark:text-white">What to Do While Waiting for Help</h3>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<div class="space-y-4">
<div class="flex gap-4 items-start">
<div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">1</div>
<div>
<h4 class="font-bold text-gray-800 dark:text-white">Keep them comfortable</h4>
<p class="text-sm text-gray-600 dark:text-gray-400">Help the person lie down on their side with their head slightly raised. Keep them warm and reassured.</p>
</div>
</div>
<div class="flex gap-4 items-start">
<div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">2</div>
<div>
<h4 class="font-bold text-gray-800 dark:text-white">Monitor Symptoms</h4>
<p class="text-sm text-gray-600 dark:text-gray-400">Check for changes in their pulse and breathing. Note down every symptom and the exact time it started.</p>
</div>
</div>
<div class="flex gap-4 items-start">
<div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">3</div>
<div>
<h4 class="font-bold text-gray-800 dark:text-white">Collect Information</h4>
<p class="text-sm text-gray-600 dark:text-gray-400">Gather their current medications, allergies, and existing medical conditions for the emergency crew.</p>
</div>
</div>
</div>
<div class="bg-red-50 dark:bg-red-900/20 p-6 rounded-xl border border-red-100 dark:border-red-800/50">
<h4 class="text-emergency font-bold mb-4 flex items-center gap-2">
<span class="material-symbols-outlined">dangerous</span>
                            DO NOT DO THESE:
                        </h4>
<ul class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-emergency text-lg">close</span>
                                Do NOT give them anything to eat or drink.
                            </li>
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-emergency text-lg">close</span>
                                Do NOT give them aspirin or medication.
                            </li>
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-emergency text-lg">close</span>
                                Do NOT let them go to sleep or wait to see if it passes.
                            </li>
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-emergency text-lg">close</span>
                                Do NOT drive them to the hospital yourself if ambulance is available.
                            </li>
</ul>
</div>
</div>
</section>
</div>
</main>
</div>
<div class="fixed bottom-6 right-6 max-w-sm bg-white dark:bg-surface-dark border-l-4 border-emergency p-5 rounded-xl shadow-2xl flex gap-4 transform transition-all hover:-translate-y-1">
<div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center flex-shrink-0">
<span class="material-symbols-outlined text-emergency animate-pulse">emergency_share</span>
</div>
<div>
<p class="text-sm font-bold text-gray-800 dark:text-white uppercase tracking-wider mb-1">Emergency Checklist</p>
<p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">If symptoms persist for even 1 minute, call emergency services immediately. Every second counts for brain tissue recovery.</p>
</div>
</div>

<script>
function openGoogleMaps(hospitalName, address) {
    const encodedAddress = encodeURIComponent(hospitalName + ', ' + address);
    const mapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodedAddress}`;
    window.open(mapsUrl, '_blank');
}
</script>

</body></html>
