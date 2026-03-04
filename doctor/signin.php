<?php
session_start();
// Ensure this file uses MySQLi connection
require_once 'db_conn.php'; 

$message = "";

// Check if form is submitted
if (isset($_POST['register'])) {
    // 1. Get data from form
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $license = $_POST['license_number'];

    // 2. Simple Validation
    if (empty($full_name) || empty($email) || empty($pass)) {
        $message = "<div class='text-red-500 text-sm mb-4'>Please fill in all required fields.</div>";
    } else {
        // 3. Hash the password
        $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

        // 4. Insert into Database (MySQLi Syntax)
        $sql = "INSERT INTO doctors (full_name, email, password, license_number) VALUES (?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            // "ssss" indicates that the 4 parameters are all Strings
            $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $license);
            
            if ($stmt->execute()) {
                $message = "<div class='text-green-600 text-sm mb-4 font-bold'>Doctor registered successfully! <a href='login.php' class='underline'>Login here</a></div>";
            } else {
                $message = "<div class='text-red-500 text-sm mb-4'>Execute Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
             $message = "<div class='text-red-500 text-sm mb-4'>Prepare Error: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Doctor Registration | NeuroNest</title>
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
    
    <div class="relative hidden lg:flex lg:w-1/2 xl:w-3/5 bg-cover bg-center items-center justify-center overflow-hidden" style="background-image: url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');">
        <div class="absolute inset-0 neural-gradient opacity-90"></div>
        <div class="relative z-10 px-12 xl:px-24">
            <h1 class="text-white text-5xl xl:text-7xl font-extrabold leading-[1.1] tracking-tight mb-6">
                Join the <br/><span class="text-primary/40 text-transparent bg-clip-text bg-gradient-to-r from-primary to-teal-200">Network.</span>
            </h1>
            <p class="text-white/80 text-lg xl:text-xl max-w-lg font-light leading-relaxed">
                Create your professional account to access NeuroNest's predictive healthcare tools.
            </p>
        </div>
    </div>

    <div class="flex-1 flex items-center justify-center p-6 sm:p-12 md:p-20 bg-background-light dark:bg-background-dark">
        <div class="w-full max-w-[440px]">
            <div class="mb-10 text-left">
                <h3 class="text-[#131515] dark:text-white text-3xl font-bold tracking-tight mb-2">Doctor Registration</h3>
                <p class="text-[#6f7b7b] dark:text-gray-400 text-base">Enter your details to create an account.</p>
            </div>

            <?php echo $message; ?>

            <form class="space-y-5" method="POST" action="">
                
                <div class="space-y-2">
                    <label class="text-[#131515] dark:text-white text-sm font-semibold leading-normal ml-1">Full Name</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors">person</span>
                        <input type="text" name="full_name" required class="flex w-full rounded-xl text-[#131515] border border-[#dfe2e2] dark:border-gray-700 bg-white dark:bg-gray-800 h-14 pl-12 pr-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="Dr. John Doe">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[#131515] dark:text-white text-sm font-semibold leading-normal ml-1">Medical Email Address</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors">mail</span>
                        <input type="email" name="email" required class="flex w-full rounded-xl text-[#131515] border border-[#dfe2e2] dark:border-gray-700 bg-white dark:bg-gray-800 h-14 pl-12 pr-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="name@hospital.com">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[#131515] dark:text-white text-sm font-semibold leading-normal ml-1">Password</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors">lock</span>
                        <input type="password" name="password" required class="flex w-full rounded-xl text-[#131515] border border-[#dfe2e2] dark:border-gray-700 bg-white dark:bg-gray-800 h-14 pl-12 pr-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="••••••••">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[#131515] dark:text-white text-sm font-semibold leading-normal ml-1">Medical License Number</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors">verified_user</span>
                        <input type="text" name="license_number" class="flex w-full rounded-xl text-[#131515] border border-[#dfe2e2] dark:border-gray-700 bg-white dark:bg-gray-800 h-14 pl-12 pr-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="e.g. MD-9923842">
                    </div>
                </div>

                <button type="submit" name="register" class="w-full flex h-14 items-center justify-center rounded-xl bg-primary text-white text-base font-bold tracking-wide hover:bg-[#3d6666] transition-colors shadow-lg shadow-primary/20">
                    Register Doctor
                </button>
            </form>

            <div class="mt-10 text-center">
                <p class="text-[#6f7b7b] dark:text-gray-400 text-sm">Already registered?</p>
                <a class="inline-block mt-2 text-primary font-bold hover:underline text-sm" href="login.php">Sign In Here</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>