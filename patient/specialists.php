<?php
session_start();
require_once 'db_conn.php'; 

// --- FETCH USER & DOCTORS (Existing Logic) ---
$patient_name = "Guest User";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql_user = "SELECT full_name FROM patient_signup WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user && $result_user->num_rows > 0) {
        $row_user = $result_user->fetch_assoc();
        $patient_name = $row_user['full_name'];
    }
    $stmt_user->close();
}

$sql_doctors = "SELECT id, full_name, COALESCE(specialization, 'General Physician') as specialization, COALESCE(experience, 0) as experience FROM doctors ORDER BY full_name ASC";
$result_doctors = mysqli_query($conn, $sql_doctors);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Specialists | NeuroNest</title>

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

<style>
    /* Custom Scrollbar for Main Content */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
</style>
</head>

<body class="bg-background-light dark:bg-background-dark min-h-screen transition-colors duration-200">

<div class="flex h-screen overflow-hidden">
    <!-- Updated Sidebar from First Code -->
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
                <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="specialists.php">
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
        <div class="max-w-6xl mx-auto">
            
            <header class="bg-white dark:bg-surface-dark rounded-[20px] p-8 shadow-sm border border-gray-100 dark:border-gray-800 mb-10 flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-extrabold text-[var(--primary)] mb-2">Select Brain Stroke Specialist</h1>
                    <p class="text-slate-500 dark:text-slate-400">Choose a specialist to chat or book an appointment.</p>
                </div>
            </header>

            <div class="flex gap-4 mb-8">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-4 flex items-center text-gray-400">
                        <span class="material-symbols-outlined">search</span>
                    </span>
                    <input type="text" id="searchInput" onkeyup="filterDoctors()" class="w-full pl-12 pr-4 py-3 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Search by name or expertise...">
                </div>
                <button class="px-6 py-3 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-700 rounded-xl font-semibold text-slate-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined">tune</span> Filters
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php 
                if ($result_doctors && mysqli_num_rows($result_doctors) > 0) {
                    while ($row = mysqli_fetch_assoc($result_doctors)) {
                        $doc_name = !empty($row['full_name']) ? trim($row['full_name']) : "Unknown";
                        $initial = strtoupper(substr($doc_name, 0, 1));
                        $spec = $row['specialization'];
                        
                        // Mock UI Data
                        $rating = "4." . rand(5, 9);
                        $is_busy = (rand(0, 3) == 0);
                        $status = $is_busy ? "Busy" : "Available";
                        $status_color = $is_busy ? "text-red-500" : "text-primary";
                        $dot_color = $is_busy ? "bg-red-500" : "bg-primary";
                ?>
                <div class="doctor-card bg-white dark:bg-surface-dark rounded-[24px] p-6 flex flex-col items-center text-center shadow-sm hover:shadow-lg transition-all border border-transparent hover:border-green-50 dark:hover:border-green-900" data-name="<?php echo strtolower($doc_name); ?>" data-spec="<?php echo strtolower($spec); ?>">
                    <div class="w-20 h-20 rounded-full bg-primary flex items-center justify-center text-white text-2xl font-bold mb-4 shadow-md ring-4 ring-green-50">
                        <?php echo $initial; ?>
                    </div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 mb-1">Dr. <?php echo htmlspecialchars($doc_name); ?></h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6"><?php echo htmlspecialchars($spec); ?></p>
                    
                    <div class="w-full space-y-2 mb-6 text-sm">
                        <div class="flex justify-between"><span class="text-slate-400">Experience</span><span class="font-bold text-slate-700 dark:text-slate-300"><?php echo $row['experience']; ?> Years</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Rating</span><span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1"><span class="material-symbols-outlined text-amber-400 text-[16px]">star</span> <?php echo $rating; ?></span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Status</span><span class="font-bold <?php echo $status_color; ?> flex items-center gap-1.5"><span class="w-2 h-2 rounded-full <?php echo $dot_color; ?>"></span> <?php echo $status; ?></span></div>
                    </div>

                    <div class="w-full space-y-3 mt-auto">
                        <!-- ✅ FIXED: Changed from chat1.php?id= to chat.php?doctor_id= -->
                        <a href="chat.php?doctor_id=<?php echo $row['id']; ?>" class="block w-full py-2.5 bg-primary hover:bg-[var(--primary)] text-white rounded-xl font-semibold transition-colors flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">chat</span> Chat Now</a>
                        
                        <!-- ✅ FIXED: Changed from book.php?id= to book.php?doctor_id= for consistency -->
                        <a href="book.php?doctor_id=<?php echo $row['id']; ?>" class="block w-full py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-semibold transition-colors flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">calendar_month</span> Book</a>
                    </div>
                </div>
                <?php 
                    } 
                } else {
                    echo '<div class="col-span-full py-20 text-center text-slate-400">No specialists found.</div>';
                }
                ?>
            </div>
            
            <div class="mt-12 flex justify-center">
                <button class="px-8 py-3 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-700 text-primary font-bold rounded-full hover:bg-green-50 dark:hover:bg-green-900/20 shadow-sm transition-all text-sm">
                    Load More Specialists
                </button>
            </div>

        </div>
    </main>
</div>

<script>
function filterDoctors() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.doctor-card');
    cards.forEach(card => {
        const text = (card.dataset.name + " " + card.dataset.spec).toLowerCase();
        card.style.display = text.includes(input) ? "" : "none";
    });
}
</script>
</body>
</html>