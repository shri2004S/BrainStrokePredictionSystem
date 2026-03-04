<?php
// Database Configuration
$servername = "localhost";
$username = "root";       // Default XAMPP/WAMP username
$password = "";           // Default XAMPP/WAMP password (usually empty)
$dbname = "brain"; // <--- CHANGE THIS to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get values from the form
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $raw_password = $_POST['password'];

    // Simple validation
    if(!empty($full_name) && !empty($email) && !empty($phone) && !empty($raw_password)){
        
        // Use Prepared Statements to prevent SQL Injection (Security)
        $stmt = $conn->prepare("INSERT INTO patient_signup (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssss", $full_name, $email, $phone, $raw_password);
            
            // Execute the query
            if ($stmt->execute()) {
                echo "<div style='color: green; text-align: center; margin-top: 20px; font-family: sans-serif;'>";
                echo "<h2>Success!</h2>";
                echo "<p>Patient account created successfully.</p>";
                echo "</div>";
            } else {
                echo "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $conn->error;
        }

    } else {
        echo "All fields are required.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>NeuroNest | Patient Signup</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#2d9f75",
            "background-light": "#e7f3ef",
            "background-dark": "#17191c",
          },
          fontFamily: {
            "display": ["Plus Jakarta Sans", "sans-serif"]
          },
          borderRadius: {"DEFAULT": "0.5rem", "lg": "1rem", "xl": "1.5rem", "full": "9999px"},
        },
      },
    }
  </script>
<style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
  </style>
</head>
<body class="bg-white dark:bg-background-dark font-display overflow-x-hidden">
<div class="flex min-h-screen w-full flex-col lg:flex-row">
<div class="relative hidden lg:flex lg:w-[45%] flex-col justify-between p-12 bg-white dark:bg-background-dark border-r border-[#dde4e1] dark:border-gray-800">
<div class="flex items-center gap-3">
<div class="size-8 text-primary">
<svg fill="currentColor" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill-rule="evenodd"></path>
</svg>
</div>
<h2 class="text-[#121715] dark:text-white text-2xl font-bold tracking-tight">NeuroNest</h2>
</div>
<div class="max-w-md">
<h1 class="text-[#121715] dark:text-white text-5xl font-extrabold leading-[1.1] mb-6 tracking-tight">
          Empowering your neurological health
        </h1>
<p class="text-[#688279] dark:text-gray-400 text-lg font-medium leading-relaxed">
          Join us to monitor and improve your brain health with AI-driven insights. Your journey to wellness starts here.
        </p>
</div>
<div class="w-full">
<div class="w-full aspect-[4/3] bg-cover bg-center rounded-2xl shadow-sm" data-alt="Friendly medical professional with digital brain illustration" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuCNtzP6raD25-LmfJGuunpoMrO8655JI4FcJI2YgvR8rg4JLZG8pB4z2zasgQZ4ENacpl90_BbSafa4YM6WrLSELiXWs51L4mIhE2DM1q1HUhzz9RXg8gxaB1KjVyJIhk2K_UKvEbgPA1KPCqqDbCIruDljfQIMEaAHIrKgyQ9iArzxa-Y44wDDR_e78XMFHvEcppGP2L408tlqsx0BslyHOBkfl3Ao-e32wcWwqR61aucJAvc_YMJrdE3Vvvv4ZKNXBwbxTwL8Ymg");'>
</div>
</div>
</div>
<div class="flex-1 bg-background-light dark:bg-background-dark/50 flex flex-col items-center justify-center p-6 sm:p-12 md:p-20">
<div class="lg:hidden flex items-center gap-3 mb-8">
<div class="size-8 text-primary">
<svg fill="currentColor" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill-rule="evenodd"></path>
</svg>
</div>
<h2 class="text-[#121715] dark:text-white text-xl font-bold">NeuroNest</h2>
</div>
<div class="w-full max-w-[520px] bg-white dark:bg-background-dark rounded-xl shadow-[0px_10px_40px_rgba(0,0,0,0.06)] border border-[#dde4e1] dark:border-gray-800 p-8 sm:p-12">
<div class="mb-10 text-center">
<h2 class="text-[#121715] dark:text-white text-3xl font-bold mb-3">Join NeuroNest</h2>
<p class="text-[#688279] dark:text-gray-400 text-base">Start your journey towards better mental and neurological wellness.</p>
</div>

<form class="space-y-6" action="signup.php" method="POST">
<div class="flex flex-col gap-2">
<label class="text-[#121715] dark:text-gray-200 text-sm font-semibold ml-1">Full Name</label>
<div class="relative flex items-center">
<input name="full_name" required class="w-full h-14 pl-4 pr-12 rounded-xl border border-[#dde4e1] dark:border-gray-700 bg-white dark:bg-gray-800 text-[#121715] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-[#688279]" placeholder="e.g. John Doe" type="text"/>
<div class="absolute right-4 text-[#688279]">
<span class="material-symbols-outlined">person</span>
</div>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-[#121715] dark:text-gray-200 text-sm font-semibold ml-1">Email Address</label>
<div class="relative flex items-center">
<input name="email" required class="w-full h-14 pl-4 pr-12 rounded-xl border border-[#dde4e1] dark:border-gray-700 bg-white dark:bg-gray-800 text-[#121715] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-[#688279]" placeholder="name@example.com" type="email"/>
<div class="absolute right-4 text-[#688279]">
<span class="material-symbols-outlined">mail</span>
</div>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-[#121715] dark:text-gray-200 text-sm font-semibold ml-1">Phone Number</label>
<div class="relative flex items-center">
<input name="phone" required class="w-full h-14 pl-4 pr-12 rounded-xl border border-[#dde4e1] dark:border-gray-700 bg-white dark:bg-gray-800 text-[#121715] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-[#688279]" placeholder="+1 (555) 000-0000" type="tel"/>
<div class="absolute right-4 text-[#688279]">
<span class="material-symbols-outlined">call</span>
</div>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-[#121715] dark:text-gray-200 text-sm font-semibold ml-1">Password</label>
<div class="relative flex items-center">
<input name="password" required class="w-full h-14 pl-4 pr-12 rounded-xl border border-[#dde4e1] dark:border-gray-700 bg-white dark:bg-gray-800 text-[#121715] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-[#688279]" placeholder="••••••••" type="password"/>
<div class="absolute right-4 text-[#688279] cursor-pointer hover:text-primary transition-colors">
<span class="material-symbols-outlined">lock</span>
</div>
</div>
</div>
<div class="flex items-start gap-3 px-1">
<input class="mt-1 size-4 rounded border-[#dde4e1] text-primary focus:ring-primary" type="checkbox" required/>
<p class="text-sm text-[#688279] leading-tight">By signing up, I agree to the <a class="text-primary font-semibold hover:underline" href="#">Terms of Service</a> and <a class="text-primary font-semibold hover:underline" href="#">Privacy Policy</a>.</p>
</div>
<button class="w-full h-14 bg-primary hover:bg-[#258a65] text-white rounded-xl font-bold text-lg transition-colors shadow-lg shadow-primary/20 mt-2" type="submit">
            Create Account
          </button>
</form>
<div class="mt-10 pt-8 border-t border-[#f1f4f3] dark:border-gray-800 text-center">
<p class="text-[#688279] dark:text-gray-400">
            Already have an account? 
            <a class="text-primary font-bold hover:underline ml-1" href="login.php">Sign In</a>
</p>
</div>
</div>
<p class="mt-8 text-xs text-[#688279] dark:text-gray-500 hidden lg:block uppercase tracking-widest font-semibold">
        Secure Encryption • HIPAA Compliant • AI-Powered Insights
      </p>
</div>
</div>
</body></html>