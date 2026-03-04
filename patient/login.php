<?php
// Start session immediately
session_start();

// Include database connection
include 'db_conn.php';

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $raw_password = $_POST['password'];

    if(!empty($email) && !empty($raw_password)){
        
        $stmt = $conn->prepare("SELECT id, full_name, password FROM patient_signup WHERE email = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify Password
                if (password_verify($raw_password, $user['password']) || $raw_password === $user['password']) {
                    
                    // ✅ SET SESSION VARIABLES
                    $_SESSION['email'] = $email;          
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // ✅ NEW: ADD ROLE FOR CHAT SYSTEM
                    $_SESSION['role'] = 'patient';
                    
                    // ✅ Force session write before redirect
                    session_write_close(); 
                    
                    header("Location: dashboard.php"); 
                    exit();
                } else {
                    $error_msg = "Invalid password.";
                }
            } else {
                $error_msg = "No account found with this email.";
            }
            $stmt->close();
        }
    } else {
        $error_msg = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Patient Login - NeuroNest</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2d9f75",
                        "background-light": "#f8fafc",
                        "background-dark": "#0f172a",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body {
                @apply font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100;
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="flex w-full min-h-screen">
        <div class="hidden lg:flex flex-col w-1/2 bg-white dark:bg-slate-900 justify-center p-20 relative overflow-hidden">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary/5 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-primary/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 flex flex-col gap-12">
                <div class="flex items-center gap-3 text-primary">
                    <div class="size-11 flex items-center justify-center bg-primary text-white rounded-xl shadow-lg shadow-primary/20">
                        <span class="material-symbols-outlined text-2xl">neurology</span>
                    </div>
                    <h2 class="text-2xl font-extrabold tracking-tight">NeuroNest</h2>
                </div>
                
                <div class="rounded-[2.5rem] overflow-hidden aspect-[4/3] w-full bg-slate-50 dark:bg-slate-800 shadow-2xl shadow-primary/10 border border-slate-100 dark:border-slate-800">
                    <div class="w-full h-full bg-cover bg-center mix-blend-multiply dark:mix-blend-normal opacity-90" 
                         style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuAx_hWhcKkf7X2OW8RdqJPclsXPfe_ooxNjxhrOp5-DyMoAuqaAeYWsAVBWFjoIEBIsZ4JAnEabwfM3Zs0iAcUKUjEItcpYnY1HqI3nDVacXNtTGudTRqmbL99j9OIlfMb9qpYpisrdBZGnD-dDNONxqjFr8mztE-git8XmB578DVZjzRgzAvD_bwwlWbjDT1tWt0osoXTfb4zRB5QFxI6aNFpVgoZikVZOl-WsQudkhpiJ3Zo37Ur7yrdfXDgPVbXBEmolLH-rMq8");'>
                    </div>
                </div>
                
                <div class="flex flex-col gap-6">
                    <h1 class="text-5xl font-extrabold leading-tight tracking-tight text-slate-900 dark:text-white">
                        Empowering your <br/>
                        <span class="relative">
                            <span class="relative z-10">neurological</span>
                            <span class="absolute bottom-2 left-0 w-full h-3 bg-primary/20 rounded-full -z-0"></span>
                        </span> health
                    </h1>
                    <p class="text-xl text-slate-600 dark:text-slate-400 max-w-lg leading-relaxed">
                        Join our community for advanced stroke prediction monitoring and personalized mental health support.
                    </p>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-background-light dark:bg-background-dark">
            <div class="max-w-[480px] w-full bg-white dark:bg-slate-900 p-8 sm:p-14 rounded-3xl shadow-[0_32px_64px_-12px_rgba(45,159,117,0.12)] border border-slate-100 dark:border-slate-800">
                
                <div class="mb-12 text-center">
                    <div class="lg:hidden flex justify-center mb-8">
                        <div class="flex items-center gap-2 text-primary">
                            <span class="material-symbols-outlined text-3xl">neurology</span>
                            <span class="text-xl font-bold">NeuroNest</span>
                        </div>
                    </div>
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-3">Welcome Back</h2>
                    <p class="text-slate-500 dark:text-slate-400">Sign in to access your health dashboard and insights.</p>
                </div>

                <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 animate-pulse">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                        <span class="material-symbols-outlined text-xl">error</span>
                        <span class="text-sm font-semibold"><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <form class="flex flex-col gap-7" method="POST" action="">

                    <div class="flex flex-col gap-2.5">
                        <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">
                            Email Address
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                <span class="material-symbols-outlined text-xl">mail</span>
                            </div>
                            <input
                                name="email"
                                type="email"
                                required
                                placeholder="name@example.com"
                                class="form-input block w-full pl-12 pr-4 py-4 rounded-2xl text-slate-900 dark:text-white border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        <div class="flex justify-between items-center px-1">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300">
                                Password
                            </label>
                            <a href="forgot_password.php" class="text-xs font-bold text-primary hover:underline underline-offset-2">
                                Forgot?
                            </a>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                <span class="material-symbols-outlined text-xl">lock</span>
                            </div>
                            <input
                                name="password"
                                type="password"
                                required
                                placeholder="Enter your password"
                                class="form-input block w-full pl-12 pr-4 py-4 rounded-2xl text-slate-900 dark:text-white border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400"
                            />
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 rounded-2xl py-4 bg-primary text-white text-base font-bold shadow-xl shadow-primary/25 hover:bg-primary/90 hover:-translate-y-0.5 transition-all active:scale-[0.98]">
                        <span>Sign In</span>
                        <span class="material-symbols-outlined text-lg">arrow_forward</span>
                    </button>

                </form>

                <div class="mt-12 text-center">
                    <p class="text-slate-500 dark:text-slate-400 font-medium">
                        Don't have an account? 
                        <a class="text-primary font-bold hover:underline underline-offset-4 ml-1" href="signup.php">Sign Up</a>
                    </p>
                </div>
                
                <div class="mt-14 pt-8 border-t border-slate-100 dark:border-slate-800 flex items-center justify-center gap-8">
                    <button class="flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-primary transition-colors uppercase tracking-wider">
                        <span class="material-symbols-outlined text-base">help</span>
                        Support
                    </button>
                    <button class="flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-primary transition-colors uppercase tracking-wider">
                        <span class="material-symbols-outlined text-base">verified_user</span>
                        Security
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>