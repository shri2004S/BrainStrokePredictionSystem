<?php
session_start();
require_once 'db_conn.php';

// Check if doctor is logged in - FIXED to match login.php session variable
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['user_id']; // Changed from doctor_id to user_id
$message = "";
$error = "";

// INSERT/UPDATE when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $specialization = mysqli_real_escape_string($conn, $_POST['specialization']);
    $experience = intval($_POST['experience']);
    $education = mysqli_real_escape_string($conn, $_POST['education']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    // Handle notification checkboxes
    $notify_risk = isset($_POST['notify_risk']) ? 1 : 0;
    $notify_appoint = isset($_POST['notify_appoint']) ? 1 : 0;
    $notify_analytics = isset($_POST['notify_analytics']) ? 1 : 0;
    
    // Handle Profile Image Upload
    $profile_image_path = null;
    $image_uploaded = false;
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Get actual MIME type from file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_mime = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
        
        // Validate file
        if (!in_array($file_mime, $allowed_types)) {
            $error = "Invalid file type. Detected: " . $file_mime . ". Please upload JPG, PNG, or GIF only.";
        } elseif (!in_array($file_extension, $allowed_extensions)) {
            $error = "Invalid file extension. Please upload JPG, PNG, or GIF only.";
        } elseif ($file_size > $max_size) {
            $error = "File size exceeds 5MB limit. Your file: " . round($file_size / 1024 / 1024, 2) . "MB";
        } else {
            $upload_dir = 'uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $error = "Failed to create upload directory. Check server permissions.";
                }
            }
            
            if (empty($error)) {
                // Delete old image first
                $old_image_query = "SELECT profile_image_path FROM doctors WHERE id = ?";
                $stmt_old = mysqli_prepare($conn, $old_image_query);
                mysqli_stmt_bind_param($stmt_old, "i", $doctor_id);
                mysqli_stmt_execute($stmt_old);
                $old_result = mysqli_stmt_get_result($stmt_old);
                $old_data = mysqli_fetch_assoc($old_result);
                mysqli_stmt_close($stmt_old);
                
                if ($old_data && !empty($old_data['profile_image_path']) && file_exists($old_data['profile_image_path'])) {
                    @unlink($old_data['profile_image_path']);
                }
                
                // Generate unique filename
                $new_filename = 'doctor_' . $doctor_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $target_file)) {
                    // Verify file was actually written
                    if (file_exists($target_file) && filesize($target_file) > 0) {
                        $profile_image_path = $target_file;
                        $image_uploaded = true;
                    } else {
                        $error = "Image upload failed. File not saved properly.";
                        @unlink($target_file);
                    }
                } else {
                    $error = "Failed to move uploaded file. Check directory permissions (755 or 777).";
                }
            }
        }
    } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle specific upload errors
        switch ($_FILES['profile_image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = "File size exceeds allowed limit.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error = "File upload was interrupted. Please try again.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error = "Server configuration error: No temp directory.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error = "Failed to write file to disk.";
                break;
            default:
                $error = "Unknown upload error occurred.";
        }
    }
    
    // Update query using prepared statements (prevents SQL injection)
    if (empty($error)) {
        if ($image_uploaded && $profile_image_path) {
            $sql = "UPDATE doctors SET 
                    full_name = ?,
                    email = ?,
                    specialization = ?,
                    experience = ?,
                    education = ?,
                    bio = ?,
                    phone = ?,
                    address = ?,
                    notify_risk = ?,
                    notify_appoint = ?,
                    notify_analytics = ?,
                    profile_image_path = ?
                    WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssississiisi", 
                $full_name, $email, $specialization, $experience, $education, 
                $bio, $phone, $address, $notify_risk, $notify_appoint, 
                $notify_analytics, $profile_image_path, $doctor_id
            );
        } else {
            $sql = "UPDATE doctors SET 
                    full_name = ?,
                    email = ?,
                    specialization = ?,
                    experience = ?,
                    education = ?,
                    bio = ?,
                    phone = ?,
                    address = ?,
                    notify_risk = ?,
                    notify_appoint = ?,
                    notify_analytics = ?
                    WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssississiii", 
                $full_name, $email, $specialization, $experience, $education, 
                $bio, $phone, $address, $notify_risk, $notify_appoint, 
                $notify_analytics, $doctor_id
            );
        }
        
        if (mysqli_stmt_execute($stmt)) {
            // Update session data with new name
            $_SESSION['doctor_name'] = $full_name;
            
            $message = $image_uploaded ? "Profile updated successfully with new image!" : "Profile updated successfully!";
            // Refresh the page to show updated data
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $error = "Error updating profile: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Show success message if redirected
if (isset($_GET['success'])) {
    $message = "Profile updated successfully!";
}

// FETCH current data using prepared statement
$sql_fetch = "SELECT * FROM doctors WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt, "i", $doctor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$doctor = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$doctor) {
    die("Doctor profile not found.");
}

function getVal($array, $key, $default = '') {
    return isset($array[$key]) ? htmlspecialchars($array[$key]) : $default;
}

// Function to get profile image with fallback
function getProfileImage($doctor) {
    if (!empty($doctor['profile_image_path']) && file_exists($doctor['profile_image_path'])) {
        return htmlspecialchars($doctor['profile_image_path']) . '?v=' . filemtime($doctor['profile_image_path']); // Cache busting with file modification time
    }
    return 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y';
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Doctor Professional Profile - NeuroNest</title>
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
          },
        },
      };
</script>
<style type="text/tailwindcss">
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    input:focus, textarea:focus, select:focus {
        outline: none; border-color: #4CAF50; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
    }
    .image-upload-indicator {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(76, 175, 80, 0.9);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        z-index: 10;
    }
    .image-upload-indicator.active {
        display: block;
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
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="dashboard.php">
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
        <a class="flex items-center px-4 py-3 text-primary bg-primary/10 border-r-4 border-primary rounded-lg group font-semibold" href="profile.php">
            <span class="material-symbols-outlined mr-3">settings</span> Settings
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group" href="logout.php">
            <span class="material-symbols-outlined mr-3">logout</span> Logout
        </a>
    </nav>
    <div class="p-4 border-t border-gray-100 dark:border-gray-800">
        <div class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold mr-3">
                <?= substr(getVal($doctor, 'full_name', 'Dr'), 0, 2) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">Dr. <?= getVal($doctor, 'full_name', 'Doctor') ?></p>
                <p class="text-xs text-gray-500 truncate"><?= getVal($doctor, 'specialization', 'Specialist') ?></p>
            </div>
        </div>
    </div>
</aside>

<main class="flex-1 overflow-y-auto p-4 md:p-8">
    <header class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Profile Settings</h1>
            <p class="text-gray-500 dark:text-gray-400">Manage your professional presence and account preferences.</p>
            <?php if($message): ?>
                <div class="mt-2 p-3 bg-green-100 text-green-700 rounded-lg text-sm border border-green-200">
                    ✓ <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="mt-2 p-3 bg-red-100 text-red-700 rounded-lg text-sm border border-red-200">
                    ✗ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                <span class="material-symbols-outlined">dark_mode</span>
            </button>
            <button type="submit" form="profileForm" name="update_profile" class="bg-primary text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-primary/90 transition-all shadow-sm">
                Save Changes
            </button>
        </div>
    </header>

    <form id="profileForm" method="POST" action="" enctype="multipart/form-data">
        <div class="max-w-4xl mx-auto space-y-8">
            
            <section class="bg-white dark:bg-surface-dark p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex flex-col md:flex-row items-center gap-8">
                    <div class="relative group">
                        <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-primary/20 relative">
                            <img id="profilePreview" alt="Profile" class="w-full h-full object-cover" 
                                 src="<?= getProfileImage($doctor) ?>"/>
                            <div id="uploadIndicator" class="image-upload-indicator">Image Selected ✓</div>
                        </div>
                        <input type="file" id="profileImageInput" name="profile_image" accept="image/jpeg,image/jpg,image/png,image/gif" class="hidden" onchange="previewImage(this)">
                        <button type="button" onclick="document.getElementById('profileImageInput').click()" class="absolute bottom-0 right-0 bg-primary text-white p-2 rounded-full shadow-lg hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-sm">photo_camera</span>
                        </button>
                        <p class="text-xs text-gray-500 text-center mt-2">Max 5MB (JPG, PNG, GIF)</p>
                    </div>
                    <div class="text-center md:text-left">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                            Dr. <?= getVal($doctor, 'full_name', 'Doctor Name') ?>
                        </h2>
                        <p class="text-primary font-medium mb-2">
                            <?= getVal($doctor, 'specialization', 'Specialization not set') ?>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Profile completion: 92%</p>
                        <div class="w-48 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full mt-2 mx-auto md:mx-0">
                            <div class="h-full bg-primary rounded-full" style="width: 92%"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-surface-dark p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">badge</span>
                    Professional Information
                </h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
                        <input name="full_name" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="text" 
                               value="<?= getVal($doctor, 'full_name') ?>" required/>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Professional Bio</label>
                        <textarea name="bio" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400 h-32" 
                                  placeholder="Write a brief professional summary..."><?= getVal($doctor, 'bio') ?></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Specialization</label>
                            <input name="specialization" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="text" 
                                   value="<?= getVal($doctor, 'specialization') ?>"/>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Years of Experience</label>
                            <input name="experience" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="number" min="0"
                                   value="<?= getVal($doctor, 'experience', 0) ?>"/>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Education</label>
                        <input name="education" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="text" 
                               value="<?= getVal($doctor, 'education') ?>"/>
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-surface-dark p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">contact_mail</span>
                    Contact Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                        <input name="email" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="email" 
                               value="<?= getVal($doctor, 'email') ?>" required/>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Phone Number</label>
                        <input name="phone" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="tel" 
                               value="<?= getVal($doctor, 'phone', '') ?>"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Clinic Address</label>
                        <input name="address" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm" type="text" 
                               value="<?= getVal($doctor, 'address', '') ?>"/>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <section class="bg-white dark:bg-surface-dark p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">notifications_active</span>
                        Notifications
                    </h3>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Urgent Risk Alerts</span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <input name="notify_risk" class="sr-only peer" type="checkbox" 
                                       <?= (isset($doctor['notify_risk']) && $doctor['notify_risk'] == 1) ? 'checked' : '' ?> />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-gray-700 dark:text-gray-300">New Patient Appointments</span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <input name="notify_appoint" class="sr-only peer" type="checkbox" 
                                       <?= (isset($doctor['notify_appoint']) && $doctor['notify_appoint'] == 1) ? 'checked' : '' ?> />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Weekly Analytics Report</span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <input name="notify_analytics" class="sr-only peer" type="checkbox" 
                                       <?= (isset($doctor['notify_analytics']) && $doctor['notify_analytics'] == 1) ? 'checked' : '' ?> />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                            </div>
                        </label>
                    </div>
                </section>
                <section class="bg-white dark:bg-surface-dark p-8 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">security</span>
                        Account Security
                    </h3>
                    <div class="space-y-4">
                        <button type="button" class="w-full flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span class="material-symbols-outlined text-gray-400">lock</span>
                                Change Password
                            </div>
                            <span class="material-symbols-outlined text-gray-400">chevron_right</span>
                        </button>
                    </div>
                </section>
            </div>
            <div class="flex justify-end gap-4 pt-4 pb-12">
                <button type="reset" class="px-6 py-2.5 rounded-lg font-semibold text-gray-500 hover:text-gray-700 transition-all" onclick="resetForm()">
                    Discard Changes
                </button>
                <button type="submit" name="update_profile" class="bg-primary text-white px-8 py-2.5 rounded-lg font-semibold hover:bg-primary/90 transition-all shadow-md">
                    Update Profile
                </button>
            </div>
        </div>
    </form>
</main>
</div>

<script>
let imageSelected = false;

function previewImage(input) {
    const indicator = document.getElementById('uploadIndicator');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB. Your file is ' + (file.size / 1024 / 1024).toFixed(2) + 'MB');
            input.value = '';
            indicator.classList.remove('active');
            imageSelected = false;
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please upload only JPG, PNG, or GIF images. You uploaded: ' + file.type);
            input.value = '';
            indicator.classList.remove('active');
            imageSelected = false;
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
            indicator.classList.add('active');
            imageSelected = true;
            
            // Hide indicator after 2 seconds
            setTimeout(() => {
                indicator.classList.remove('active');
            }, 2000);
        }
        reader.readAsDataURL(file);
    } else {
        indicator.classList.remove('active');
        imageSelected = false;
    }
}

function resetForm() {
    if (imageSelected) {
        const originalSrc = document.getElementById('profilePreview').dataset.originalSrc || 
                           "<?= getProfileImage($doctor) ?>";
        document.getElementById('profilePreview').src = originalSrc;
        document.getElementById('profileImageInput').value = '';
        document.getElementById('uploadIndicator').classList.remove('active');
        imageSelected = false;
    }
}

// Store original image source on load
document.addEventListener('DOMContentLoaded', function() {
    const preview = document.getElementById('profilePreview');
    preview.dataset.originalSrc = preview.src;
});

// Warn user if they're leaving with unsaved image
window.addEventListener('beforeunload', function (e) {
    if (imageSelected) {
        e.preventDefault();
        e.returnValue = 'You have selected a new image but haven\'t saved it yet. Are you sure you want to leave?';
    }
});

// Remove warning when form is submitted
document.getElementById('profileForm').addEventListener('submit', function() {
    imageSelected = false;
});
</script>
</body>
</html>