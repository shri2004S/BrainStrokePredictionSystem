<?php
ob_start();
session_start();

// Debug (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_conn.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $message = "<div class='text-red-500 text-sm mb-4'>Please fill in all fields.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='text-red-500 text-sm mb-4'>Invalid email address.</div>";
    } else {

        // Query using email (not user_id)
        $sql = "SELECT id, full_name, email, password, specialization FROM doctors WHERE email = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $message = "<div class='text-red-500 text-sm mb-4'>Database error: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $row['password'])) {

                    // Regenerate session ID for security
                    session_regenerate_id(true);

                    // ✅ CRITICAL: Store ALL session data required for chat system
                    $_SESSION['user_id'] = $row['id'];          // Primary identifier
                    $_SESSION['role'] = 'doctor';                // Role identifier for chat
                    $_SESSION['doctor_name'] = $row['full_name']; // Full name
                    $_SESSION['doctor_email'] = $row['email'];   // Email
                    $_SESSION['specialization'] = $row['specialization']; // Specialty
                    $_SESSION['login_time'] = time();            // Login timestamp

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();

                } else {
                    $message = "<div class='text-red-500 text-sm mb-4'>Incorrect email or password.</div>";
                }
            } else {
                // No user found with this email
                $message = "<div class='text-red-500 text-sm mb-4'>No account found with this email address.</div>";
            }

            $stmt->close();
        }
    }
}
?>


<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Doctor Login | NeuroNest</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: { "primary": "#4b7c7c", "background-light": "#ffffff", "background-dark": "#232629" },
                    fontFamily: { "display": ["Plus Jakarta Sans", "sans-serif"] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .neural-gradient { background: linear-gradient(135deg, rgba(75, 124, 124, 0.9) 0%, rgba(35, 38, 41, 0.8) 100%); }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#131515] dark:text-white min-h-screen">
<div class="flex min-h-screen w-full flex-col lg:flex-row">
    
    <!-- Left Hero Section -->
    <div class="relative hidden lg:flex lg:w-1/2 xl:w-3/5 bg-cover bg-center items-center justify-center overflow-hidden" style="background-image: url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');">
        <div class="absolute inset-0 neural-gradient opacity-90"></div>
        <div class="relative z-10 px-12 xl:px-24">
            <div class="flex items-center gap-3 mb-8">
                <div class="size-10 text-white">
                    <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
                    </svg>
                </div>
                <h2 class="text-white text-3xl font-bold tracking-tight">NeuroNest</h2>
            </div>
            <h1 class="text-white text-5xl xl:text-7xl font-extrabold leading-[1.1] tracking-tight mb-6">
                Precision <br/>Healthcare for <br/><span class="text-primary/40 text-transparent bg-clip-text bg-gradient-to-r from-primary to-teal-200">Professionals.</span>
            </h1>
            <p class="text-white/80 text-lg xl:text-xl max-w-lg font-light leading-relaxed">
                Harness the power of sophisticated neural network analysis for real-time brain stroke prediction and advanced mental health diagnostics.
            </p>
        </div>
        <div class="absolute bottom-10 left-10 text-white/20 text-xs font-mono tracking-widest uppercase">
            System Status: Active • Neural Engine v4.2
        </div>
    </div>

    <!-- Right Login Form Section -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12 md:p-20 bg-background-light dark:bg-background-dark">
        <div class="w-full max-w-[440px]">
            <!-- Mobile Logo -->
            <div class="lg:hidden flex items-center gap-2 mb-10">
                <div class="size-8 text-primary">
                    <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-primary">NeuroNest</h2>
            </div>
            
            <!-- Login Header -->
            <div class="mb-10 text-left">
                <h3 class="text-[#131515] dark:text-white text-3xl font-bold tracking-tight mb-2">Doctor Sign In</h3>
                <p class="text-[#6f7b7b] dark:text-gray-400 text-base">Access your clinical dashboard and diagnostic tools.</p>
            </div>

            <!-- Error/Success Messages -->
            <?php echo $message; ?>

            <!-- Login Form -->
            <form class="space-y-5" method="POST" action="">
                <!-- Email Field -->
                <div class="space-y-2">
                    <label class="text-[#131515] dark:text-white text-sm font-semibold leading-normal ml-1">
                        Medical Email Address
                    </label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors">mail</span>
                        <input 
                            name="email" 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                            class="flex w-full rounded-xl text-[#131515] dark:text-white border border-[#dfe2e2] dark:border-gray-700 bg-white dark:bg-gray-800 h-14 pl-12 pr-4 placeholder:text-[#6f7b7b] focus:border-primary focus:ring-1 focus:ring-primary transition-all text-base outline-none" 
                            placeholder="name@hospital.com" 
                            required 
                            type="email"
                            autocomplete="email"
                        />
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center ml-1">
                        <label class="text-[#131515] dark:text-white text-sm font-semibold leading-normal">
                            Password
                        </label>
                        <a class="text-primary text-xs font-bold hover:underline" href="forgot-password.php">Forgot?</a>
                    </div>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors">lock</span>
                        <input 
                            name="password" 
                            id="password"
                            class="flex w-full rounded-xl text-[#131515] dark:text-white border border-[#dfe2e2] dark:border-gray-700 bg-white dark:bg-gray-800 h-14 pl-12 pr-12 placeholder:text-[#6f7b7b] focus:border-primary focus:ring-1 focus:ring-primary transition-all text-base outline-none" 
                            placeholder="••••••••" 
                            required 
                            type="password"
                            autocomplete="current-password"
                        />
                        <button 
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors" 
                            type="button"
                            onclick="togglePassword()"
                        >
                            <span class="material-symbols-outlined" id="visibilityIcon">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center px-1">
                    <input 
                        class="size-4 rounded border-gray-300 text-primary focus:ring-primary" 
                        id="remember" 
                        name="remember"
                        type="checkbox"
                    />
                    <label class="ml-2 text-sm text-[#6f7b7b] dark:text-gray-400 select-none cursor-pointer" for="remember">
                        Remember this workstation
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    name="login" 
                    class="w-full flex h-14 items-center justify-center rounded-xl bg-primary text-white text-base font-bold tracking-wide hover:bg-[#3d6666] active:scale-[0.98] transition-all shadow-lg shadow-primary/20" 
                    type="submit"
                >
                    Doctor Sign In
                </button>
            </form>

            <!-- Registration Link -->
            <div class="mt-10 text-center">
                <p class="text-[#6f7b7b] dark:text-gray-400 text-sm">
                    Not registered as a medical professional?
                </p>
                <a class="inline-block mt-2 text-primary font-bold hover:underline text-sm" href="signin.php">
                    Register as a Healthcare Provider
                </a>
            </div>

            <!-- Security Badges -->
            <div class="mt-16 pt-8 border-t border-gray-100 dark:border-gray-800 flex flex-wrap justify-center gap-x-6 gap-y-2">
                <div class="flex items-center gap-1.5 grayscale opacity-50">
                    <span class="material-symbols-outlined text-[18px]">security</span>
                    <span class="text-[10px] font-bold uppercase tracking-widest">HIPAA Compliant</span>
                </div>
                <div class="flex items-center gap-1.5 grayscale opacity-50">
                    <span class="material-symbols-outlined text-[18px]">shield</span>
                    <span class="text-[10px] font-bold uppercase tracking-widest">SSL Encrypted</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const visibilityIcon = document.getElementById('visibilityIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            visibilityIcon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            visibilityIcon.textContent = 'visibility';
        }
    }
</script>
</body>
</html>