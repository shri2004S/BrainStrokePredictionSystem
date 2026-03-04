<?php
// Start session at the very beginning
session_start();

include 'db_conn.php';

// 2. Security Check
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("Location: Login.php");
    exit();
}

$prediction_result = null;
$error_message = null;
$success_message = null;
$history = [];
$user_id = null;

// --- Fetch user_id AND full_name from patient_signup ---
$patient_name = "Guest User";
$stmt_user = $conn->prepare("SELECT id, full_name FROM patient_signup WHERE email = ?");
if ($stmt_user) {
    $stmt_user->bind_param("s", $_SESSION['email']);
    $stmt_user->execute();
    $stmt_user->bind_result($user_id, $patient_name);
    $stmt_user->fetch();
    $stmt_user->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["predict_stroke"])) {
    if (empty($user_id)) {
        $error_message = "User profile not found in Patient records. Please ensure you are fully registered.";
    } else {
        $age = (int) $_POST["age"];
        $gender = $_POST["gender"];
        $avg_glucose = (float) $_POST["avg_glucose_level"];
        $bmi = (float) $_POST["bmi"];
        $hypertension = (int) $_POST["hypertension"];
        $heart_disease = (int) $_POST["heart_disease"];
        $smoking_status = $_POST["smoking_status"];
        $ever_married = $_POST["ever_married"];
        $work_type = $_POST["work_type"];
        $residence_type = $_POST["residence_type"];

        if ($age < 1 || $age > 120) {
            $error_message = "Please enter a valid age between 1 and 120.";
        } elseif ($avg_glucose < 0 || $avg_glucose > 500) {
            $error_message = "Please enter a valid glucose level between 0 and 500.";
        } elseif ($bmi < 10 || $bmi > 100) {
            $error_message = "Please enter a valid BMI between 10 and 100.";
        } else {
            $url = "http://127.0.0.1:5000/api/predict";
            $data = [
                "age" => $age, "gender" => $gender, "avg_glucose_level" => $avg_glucose,
                "bmi" => $bmi, "hypertension" => $hypertension, "heart_disease" => $heart_disease,
                "smoking_status" => $smoking_status, "ever_married" => $ever_married,
                "work_type" => $work_type, "Residence_type" => $residence_type
            ];

            $options = [
                "http" => [
                    "header" => "Content-type: application/json\r\n",
                    "method" => "POST",
                    "content" => json_encode($data),
                    "timeout" => 30
                ]
            ];

            $context = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);

            if ($result === FALSE) {
                $error_message = "Error: Could not connect to the prediction model. Please ensure the Flask server is running.";
            } else {
                $prediction_result = json_decode($result, true);
                if ($prediction_result && isset($prediction_result['success']) && $prediction_result['success'] === true) {
                    $risk_level = $prediction_result['risk_level'];
                    $probability = (float) $prediction_result['probability'];
                    $recommendations = $prediction_result['recommendations'];

                    if (is_array($recommendations)) {
                        $recommendations = implode(". ", $recommendations);
                    }

                    $stmt_insert = $conn->prepare(
                        "INSERT INTO prediction_history (user_id, age, gender, avg_glucose_level, bmi, hypertension, heart_disease, smoking_status, ever_married, work_type, residence_type, risk_level, probability, recommendations) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    if ($stmt_insert) {
                        $stmt_insert->bind_param("iisddsisssssds",
                            $user_id, $age, $gender, $avg_glucose, $bmi, $hypertension,
                            $heart_disease, $smoking_status, $ever_married, $work_type,
                            $residence_type, $risk_level, $probability, $recommendations
                        );

                        if ($stmt_insert->execute()) {
                            $success_message = "Prediction completed and saved successfully!";
                        } else {
                            $error_message = "Prediction completed but failed to save to database. Error: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                        $error_message = "Database prepare error: " . $conn->error;
                    }
                } else {
                    $error_message = isset($prediction_result['error']) ? "Prediction error: " . $prediction_result['error'] : "Received an invalid response from the prediction model.";
                }
            }
        }
    }
}

if (!empty($user_id)) {
    $stmt_history = $conn->prepare("SELECT * FROM prediction_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    if ($stmt_history) {
        $stmt_history->bind_param("i", $user_id);
        $stmt_history->execute();
        $history = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_history->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Stroke Risk Prediction - NeuroNest</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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
    <style>
        :root {
            --primary: #2D9F75;
            --primary-light: #E6F4EE;
            --bg-light: #F8FBF9;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .sidebar-active { 
            background-color: var(--primary-light); 
            color: var(--primary); 
            border-left: 4px solid var(--primary); 
        }
        .risk-low { color: #2D9F75; background: rgba(45, 159, 117, 0.1); padding: 0.25rem 1rem; border-radius: 9999px; }
        .risk-moderate { color: #d69e2e; background: rgba(214, 158, 46, 0.1); padding: 0.25rem 1rem; border-radius: 9999px; }
        .risk-high { color: #e53e3e; background: rgba(229, 62, 62, 0.1); padding: 0.25rem 1rem; border-radius: 9999px; }
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
        <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="prediction.php">
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
<h1 class="text-2xl font-bold text-gray-800 dark:text-white">Stroke Risk Prediction</h1>
<p class="text-gray-500 dark:text-gray-400">AI-powered health assessment based on clinical metrics</p>
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

<div class="max-w-6xl mx-auto space-y-6">
<?php if ($success_message): ?>
<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 rounded-xl flex items-start gap-3">
<span class="material-icons text-green-600">check_circle</span>
<div>
<p class="font-semibold text-green-800 dark:text-green-200">Success</p>
<p class="text-sm text-green-700 dark:text-green-300"><?= htmlspecialchars($success_message); ?></p>
</div>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 rounded-xl flex items-start gap-3">
<span class="material-icons text-red-600">error</span>
<div>
<p class="font-semibold text-red-800 dark:text-red-200">Error</p>
<p class="text-sm text-red-700 dark:text-red-300"><?= htmlspecialchars($error_message); ?></p>
</div>
</div>
<?php endif; ?>

<section class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
<div class="p-6 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3 bg-primary/5">
<span class="material-icons text-primary">analytics</span>
<h2 class="text-lg font-semibold text-gray-800 dark:text-white">Clinical Data Entry</h2>
</div>

<form method="POST" action="Prediction.php" id="predictionForm" class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
<input type="hidden" name="predict_stroke" value="1">

<div class="space-y-6">
<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Age</label>
<input class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none" 
type="number" name="age" min="1" max="120" required 
value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" placeholder="Enter age"/>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gender</label>
<select name="gender" required class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none">
<option value="">Select Gender</option>
<option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
<option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
<option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
</select>
</div>

<div class="grid grid-cols-2 gap-4">
<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hypertension</label>
<div class="flex items-center gap-4">
<label class="flex items-center cursor-pointer">
<input class="text-primary focus:ring-primary h-4 w-4" name="hypertension" type="radio" value="1" required/>
<span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Yes</span>
</label>
<label class="flex items-center cursor-pointer">
<input class="text-primary focus:ring-primary h-4 w-4" name="hypertension" type="radio" value="0" checked/>
<span class="ml-2 text-sm text-gray-600 dark:text-gray-400">No</span>
</label>
</div>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Heart Disease</label>
<div class="flex items-center gap-4">
<label class="flex items-center cursor-pointer">
<input class="text-primary focus:ring-primary h-4 w-4" name="heart_disease" type="radio" value="1" required/>
<span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Yes</span>
</label>
<label class="flex items-center cursor-pointer">
<input class="text-primary focus:ring-primary h-4 w-4" name="heart_disease" type="radio" value="0" checked/>
<span class="ml-2 text-sm text-gray-600 dark:text-gray-400">No</span>
</label>
</div>
</div>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Smoking Status</label>
<select name="smoking_status" required class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none">
<option value="">Select Status</option>
<option value="never smoked">Never smoked</option>
<option value="formerly smoked">Formerly smoked</option>
<option value="smokes">Currently Smokes</option>
<option value="Unknown">Unknown</option>
</select>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Marital Status</label>
<select name="ever_married" required class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none">
<option value="">Select Status</option>
<option value="Yes">Married</option>
<option value="No">Never Married</option>
</select>
</div>
</div>

<div class="space-y-6">
<div>
<div class="flex justify-between mb-2">
<label class="text-sm font-medium text-gray-700 dark:text-gray-300">Avg. Glucose Level (mg/dL)</label>
<span class="text-primary font-semibold" id="glucoseValue"><?php echo isset($_POST['avg_glucose_level']) ? htmlspecialchars($_POST['avg_glucose_level']) : '105'; ?></span>
</div>
<input class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer" 
type="range" name="avg_glucose_level" min="50" max="300" step="0.1" 
value="<?php echo isset($_POST['avg_glucose_level']) ? htmlspecialchars($_POST['avg_glucose_level']) : '105'; ?>"
oninput="document.getElementById('glucoseValue').textContent = this.value" required/>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Body Mass Index (BMI)</label>
<input class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none" 
type="number" name="bmi" step="0.1" min="10" max="100" required
value="<?php echo isset($_POST['bmi']) ? htmlspecialchars($_POST['bmi']) : ''; ?>" placeholder="e.g. 24.5"/>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Work Type</label>
<select name="work_type" required class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none">
<option value="">Select Work Type</option>
<option value="Private">Private Sector</option>
<option value="Self-employed">Self-employed</option>
<option value="Govt_job">Government Job</option>
<option value="children">Child/Student</option>
<option value="Never_worked">Never Worked</option>
</select>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Residence Type</label>
<select name="residence_type" required class="w-full px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none">
<option value="">Select Type</option>
<option value="Urban">Urban</option>
<option value="Rural">Rural</option>
</select>
</div>

<button type="submit" class="w-full bg-primary hover:bg-opacity-90 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 group mt-auto">
Run Prediction 
<span class="material-icons group-hover:translate-x-1 transition-transform">bolt</span>
</button>
</div>
</form>
</section>

<?php if ($prediction_result && isset($prediction_result["risk_level"])): ?>
<?php
$probability = round($prediction_result["probability"], 2);
$circumference = 2 * pi() * 58;
$offset = $circumference - ($probability / 100) * $circumference;
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex flex-col items-center justify-center text-center">
<h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-4">Risk Probability</h3>
<div class="relative w-32 h-32 flex items-center justify-center">
<svg class="w-full h-full transform -rotate-90">
<circle class="text-gray-100 dark:text-gray-800" cx="64" cy="64" fill="transparent" r="58" stroke="currentColor" stroke-width="8"></circle>
<circle class="text-primary transition-all duration-1000" cx="64" cy="64" fill="transparent" r="58" stroke="currentColor" stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $offset ?>" stroke-width="8"></circle>
</svg>
<span class="absolute text-2xl font-bold text-gray-800 dark:text-white"><?= $probability ?>%</span>
</div>
<p class="mt-4 text-sm font-semibold risk-<?= strtolower($prediction_result["risk_level"]) ?>">
<?= htmlspecialchars($prediction_result["risk_level"]) ?> Risk
</p>
</div>

<div class="md:col-span-2 bg-white dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
<h3 class="text-gray-800 dark:text-white font-bold mb-4 flex items-center gap-2">
<span class="material-icons text-primary">tips_and_updates</span>
Personalized Recommendations
</h3>
<div class="space-y-4">
<div class="flex gap-3">
<span class="material-icons text-primary text-lg">check_circle</span>
<p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($prediction_result["recommendations"]) ?></p>
</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if (!empty($history)): ?>
<section class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
<div class="p-6 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3 bg-primary/5">
<span class="material-icons text-primary">history</span>
<h2 class="text-lg font-semibold text-gray-800 dark:text-white">Prediction History</h2>
</div>
<div class="overflow-x-auto">
<table class="w-full">
<thead class="bg-gray-50 dark:bg-gray-800/50">
<tr>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Age</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Gender</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Glucose</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">BMI</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Risk Level</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Probability</th>
</tr>
</thead>
<tbody class="bg-white dark:bg-surface-dark divide-y divide-gray-100 dark:divide-gray-800">
<?php foreach ($history as $row): ?>
<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
<?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at'] ?? 'now'))); ?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium">
<?php echo htmlspecialchars($row['age']); ?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
<?php echo htmlspecialchars($row['gender']); ?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
<?php echo htmlspecialchars($row['avg_glucose_level']); ?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
<?php echo htmlspecialchars($row['bmi']); ?>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<?php 
$riskClass = 'risk-low';
$riskText = $row['risk_level'];
if (stripos($riskText, 'High') !== false) {
    $riskClass = 'risk-high';
} elseif (stripos($riskText, 'Moderate') !== false) {
    $riskClass = 'risk-moderate';
}
?>
<span class="<?php echo $riskClass; ?> text-xs font-bold uppercase tracking-wide">
<?php echo htmlspecialchars($riskText); ?>
</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700 dark:text-gray-300">
<?php echo number_format((float)$row['probability'], 2); ?>%
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<?php endif; ?>
</div>
</main>
</div>
</body>
</html>