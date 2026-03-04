<?php
session_start();
require_once 'db_conn.php';

// Check if patient is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];

// Get doctor ID from URL
// Note: Using 'id' as per existing code structure
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: book.php");
    exit();
}

$doctor_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch doctor details
$sql_doctor = "SELECT * FROM doctors WHERE id = '$doctor_id'";
$result_doctor = mysqli_query($conn, $sql_doctor);

if (!$result_doctor || mysqli_num_rows($result_doctor) == 0) {
    header("Location: book.php");
    exit();
}

$doctor = mysqli_fetch_assoc($result_doctor);

// Fetch patient details
$sql_patient = "SELECT full_name FROM patient_signup WHERE id = '$patient_id'";
$result_patient = mysqli_query($conn, $sql_patient);

if ($result_patient && mysqli_num_rows($result_patient) > 0) {
    $patient = mysqli_fetch_assoc($result_patient);
    $patient_name = $patient['full_name'];
} else {
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session");
    exit();
}

// Handle form submission
$booking_success = false;
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $appointment_type = mysqli_real_escape_string($conn, $_POST['appointment_type']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Validate date is not in the past
    $today = date('Y-m-d');
    if ($appointment_date < $today) {
        $error_message = "Please select a future date.";
    } else {
    $sql_insert = "INSERT INTO appointments 
               (doctor_id, patient_id, appointment_date, appointment_time, `type`, status, notes) 
               VALUES 
               ('$doctor_id', '$patient_id', '$appointment_date', '$appointment_time', '$appointment_type', 'Pending', '$notes')";
        
        if (mysqli_query($conn, $sql_insert)) {
            $booking_success = true;
        } else {
            $error_message = "Booking failed: " . mysqli_error($conn); 
        }
    }
}
$doc_initial = strtoupper(substr($doctor['full_name'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Book Appointment - Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> | NeuroNest</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>

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
    /* Added consistent sidebar active state style */
    .sidebar-active {
        @apply bg-[var(--primary-light)] text-[var(--primary)] border-l-4 border-[var(--primary)];
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
                <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="dashboard.php">
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
        
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                    <img alt="User Profile" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDY6hqXrQDMZk3b9jgreKZAuIrWplhD8UrxCaELlDZbOu9YwH7amz4IIilPoPZZuD7jvanH5zfqwrlFMWomrku5PwyuZAhrhwwhExJCpX5XXpdGrJ7YAx1n7zArPtp1FxcvhFoWp1WZlyGh43YQtdv_lV972znOUDLd7hSGpmcbOGxX4AZGUkJMnO9Px8H5cxHUshFVqjqwGLvmEGP0SyBZhSR0vVS5-roDgckuL6F2dpC2yS37MFvQQRhVBcqHXCcLhy9dRVP8C08"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($patient_name); ?></p>
                    <p class="text-xs text-slate-500 truncate">Patient Account</p>
                </div>
                <span class="material-icons-outlined text-slate-400">settings</span>
            </div>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto bg-background-light dark:bg-background-dark p-8 lg:p-12">
        <div class="max-w-4xl mx-auto">
            
            <?php if ($booking_success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-6 flex items-start gap-4">
                <span class="material-symbols-outlined text-green-600 text-3xl">check_circle</span>
                <div>
                    <h3 class="text-lg font-bold text-green-800 mb-1">Appointment Booked Successfully!</h3>
                    <p class="text-green-700">Your appointment has been scheduled.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-6 flex items-start gap-4">
                <span class="material-symbols-outlined text-red-600 text-3xl">error</span>
                <div>
                    <h3 class="text-lg font-bold text-red-800 mb-1">Booking Failed</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <header class="bg-white dark:bg-surface-dark rounded-[20px] p-8 shadow-sm border border-gray-100 dark:border-gray-800 mb-8">
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold shadow-md ring-4 ring-green-50">
                        <?php echo $doc_initial; ?>
                    </div>
                    <div>
                        <h1 class="text-3xl font-extrabold text-[var(--primary)] mb-2">Book Appointment</h1>
                        <p class="text-lg text-slate-600 dark:text-slate-400">Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></p>
                        <p class="text-sm text-slate-500"><?php echo htmlspecialchars($doctor['specialization']); ?> • <?php echo htmlspecialchars($doctor['experience']); ?> Years Experience</p>
                    </div>
                </div>
            </header>

            <div class="bg-white dark:bg-surface-dark rounded-[20px] p-8 shadow-sm border border-gray-100 dark:border-gray-800">
                <form method="POST" action="" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                <span class="material-symbols-outlined text-primary align-middle mr-1">calendar_today</span>
                                Appointment Date
                            </label>
                            <input type="date" name="appointment_date" required
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-4 py-3 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                <span class="material-symbols-outlined text-primary align-middle mr-1">schedule</span>
                                Appointment Time
                            </label>
                            <select name="appointment_time" required
                                    class="w-full px-4 py-3 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary focus:outline-none">
                                <option value="">Select Time</option>
                                <option value="08:00:00">08:00 AM</option>
                                <option value="09:00:00">09:00 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="12:00:00">12:00 PM</option>
                                <option value="13:00:00">01:00 PM</option>
                                <option value="14:00:00">02:00 PM</option>
                                <option value="15:00:00">03:00 PM</option>
                                <option value="16:00:00">04:00 PM</option>
                                <option value="17:00:00">05:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            <span class="material-symbols-outlined text-primary align-middle mr-1">video_call</span>
                            Consultation Type
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="relative flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-primary transition-colors">
                                <input type="radio" name="appointment_type" value="Video Call" required class="mr-3 text-primary focus:ring-primary">
                                <div>
                                    <p class="font-semibold text-slate-700 dark:text-slate-300">Video Call</p>
                                    <p class="text-xs text-slate-500">Online consultation</p>
                                </div>
                            </label>
                            <label class="relative flex items-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-primary transition-colors">
                               <input type="radio" name="appointment_type" value="In Person" required class="mr-3 text-primary focus:ring-primary">
                                <div>
                                    <p class="font-semibold text-slate-700 dark:text-slate-300">In-Person Visit</p>
                                    <p class="text-xs text-slate-500">Clinic appointment</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            <span class="material-symbols-outlined text-primary align-middle mr-1">notes</span>
                            Additional Notes (Optional)
                        </label>
                        <textarea name="notes" rows="4" 
                                  placeholder="Describe your symptoms or reason for visit..."
                                  class="w-full px-4 py-3 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary focus:outline-none"></textarea>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" 
                                class="flex-1 py-3 bg-primary hover:bg-green-700 text-white rounded-xl font-semibold transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined">check_circle</span>
                            Confirm Booking
                        </button>
                        <a href="`-`ts.php" 
                           class="px-8 py-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-slate-300 rounded-xl font-semibold transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

</body>
</html>