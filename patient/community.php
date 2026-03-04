<?php
session_start();

// Include database connection
require_once 'db_conn.php';

// --- FIX: Check for 'user_id' (from Login) OR 'patient_id' (legacy) ---
if (isset($_SESSION['user_id'])) {
    $patient_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['patient_id'])) {
    $patient_id = $_SESSION['patient_id'];
} else {
    // If neither exists, redirect to login
    header("Location: Login.php");
    exit();
}

$patient_name = $_SESSION['full_name'] ?? 'User';

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $content = trim($_POST['content']);
    $post_type = $_POST['post_type'] ?? 'GENERAL';
    $mood = $_POST['mood'] ?? NULL;
    
    if (!empty($content)) {
        // Ensure table has 'patient_id' column, otherwise change query to use 'user_id'
        $stmt = $conn->prepare("INSERT INTO community_posts (patient_id, patient_name, content, post_type, mood) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $patient_id, $patient_name, $content, $post_type, $mood);
        
        if ($stmt->execute()) {
            header("Location: community.php");
            exit();
        }
        $stmt->close();
    }
}

// Handle like functionality
if (isset($_POST['toggle_like'])) {
    $post_id = $_POST['post_id'];
    
    // Check if already liked
    $check_stmt = $conn->prepare("SELECT id FROM community_likes WHERE post_id = ? AND patient_id = ?");
    $check_stmt->bind_param("ii", $post_id, $patient_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Unlike
        $delete_stmt = $conn->prepare("DELETE FROM community_likes WHERE post_id = ? AND patient_id = ?");
        $delete_stmt->bind_param("ii", $post_id, $patient_id);
        $delete_stmt->execute();
        
        // Decrement likes count
        $update_stmt = $conn->prepare("UPDATE community_posts SET likes_count = likes_count - 1 WHERE id = ?");
        $update_stmt->bind_param("i", $post_id);
        $update_stmt->execute();
    } else {
        // Like
        $insert_stmt = $conn->prepare("INSERT INTO community_likes (post_id, patient_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $post_id, $patient_id);
        $insert_stmt->execute();
        
        // Increment likes count
        $update_stmt = $conn->prepare("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?");
        $update_stmt->bind_param("i", $post_id);
        $update_stmt->execute();
    }
    
    header("Location: community.php");
    exit();
}

// Fetch all posts
$posts_query = "SELECT cp.*, 
                (SELECT COUNT(*) FROM community_likes WHERE post_id = cp.id AND patient_id = ?) as user_liked
                FROM community_posts cp 
                ORDER BY cp.created_at DESC";
$posts_stmt = $conn->prepare($posts_query);
$posts_stmt->bind_param("i", $patient_id);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();

// Function to get time ago
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return $days == 1 ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}

// Function to get initials
function get_initials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Function to get badge color
function get_badge_color($type) {
    switch($type) {
        case 'STORY':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'EXPERT ADVICE':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'QUESTION':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400';
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Community Support - NeuroNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#2D9F75",
                        "bg-main": "#F8FBF9",
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
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2rem",
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
            --card-white: #FFFFFF;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .sidebar-active {
            @apply bg-[var(--primary-light)] text-[var(--primary)] border-l-4 border-[var(--primary)];
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-white border-r border-slate-200 min-h-screen fixed left-0 top-0 z-30 flex flex-col">
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
                <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="community.php">
                    <span class="material-icons-outlined">group</span>
                    <span class="font-medium">Community</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="Emergency.php">
                    <span class="material-icons-outlined">report_problem</span>
                    <span class="font-medium text-red-500">Emergency</span>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-200">
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                    <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden">
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

        <main class="flex-1 overflow-y-auto bg-[#F8FAF7] dark:bg-background-dark ml-64">
            <header class="sticky top-0 z-10 bg-white/80 dark:bg-surface-dark/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-800 px-8 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">Community Support</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Connect, share, and heal together</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative hidden md:block">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                        <input class="pl-10 pr-4 py-2 bg-gray-100 dark:bg-gray-800 border-none rounded-full text-sm focus:ring-2 focus:ring-primary/20 w-64" placeholder="Search discussions..." type="text"/>
                    </div>
                    <button class="p-2 text-gray-400 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                        <span class="material-symbols-outlined">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="max-w-7xl mx-auto p-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-4">
                        <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-4 uppercase tracking-wider">Discussion Groups</h3>
                        <div class="space-y-2">
                            <button class="w-full flex items-center justify-between p-3 rounded-lg bg-primary/5 text-primary font-medium">
                                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-xl">restore</span> Stroke Survivors</span>
                                <span class="text-xs bg-primary/20 px-2 py-0.5 rounded-full">1.2k</span>
                            </button>
                            <button class="w-full flex items-center justify-between p-3 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-xl">self_improvement</span> Mental Wellness</span>
                                <span class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">850</span>
                            </button>
                            <button class="w-full flex items-center justify-between p-3 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-xl">nutrition</span> Healthy Living Tips</span>
                                <span class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">2.1k</span>
                            </button>
                            <button class="w-full flex items-center justify-between p-3 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-xl">family_restroom</span> Caregiver Support</span>
                                <span class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">640</span>
                            </button>
                        </div>
                        <button class="mt-4 w-full py-2 text-primary text-sm font-semibold hover:bg-primary/5 rounded-lg border border-dashed border-primary/30 transition-colors">
                            + Explore More
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-6 space-y-6">
                    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <form method="POST" action="">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-primary flex-shrink-0 flex items-center justify-center text-white font-bold">
                                    <?php echo get_initials($patient_name); ?>
                                </div>
                                <textarea name="content" id="postContent" class="flex-1 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 px-4 py-2 rounded-lg text-gray-700 dark:text-gray-300 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/30" rows="3" placeholder="Share your progress or ask a question..."></textarea>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-800 flex justify-between items-center">
                                <div class="flex gap-2 items-center">
                                    <select name="post_type" class="text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-1.5 text-gray-600 dark:text-gray-400">
                                        <option value="GENERAL">General</option>
                                        <option value="STORY">Story</option>
                                        <option value="QUESTION">Question</option>
                                    </select>
                                </div>
                                <button type="submit" name="create_post" class="bg-primary text-white px-6 py-2 rounded-lg font-bold text-sm shadow-md shadow-primary/20 hover:opacity-90 transition-all">
                                    Create Post
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="space-y-4">
                        <?php while ($post = $posts_result->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex gap-3">
                                    <div class="w-11 h-11 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                        <?php echo get_initials($post['patient_name']); ?>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 dark:text-white text-sm">
                                            <?php echo htmlspecialchars($post['patient_name']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-500 flex items-center gap-1">
                                            <?php echo time_ago($post['created_at']); ?> • 
                                            <span class="<?php echo get_badge_color($post['post_type']); ?> px-1.5 py-0.5 rounded text-[10px] font-bold">
                                                <?php echo htmlspecialchars($post['post_type']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <button class="text-gray-400"><span class="material-symbols-outlined">more_horiz</span></button>
                            </div>
                            <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed mb-4 whitespace-pre-line">
                                <?php echo htmlspecialchars($post['content']); ?>
                            </p>
                            <div class="flex items-center gap-6 pt-4 border-t border-gray-50 dark:border-gray-800">
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="toggle_like" class="flex items-center gap-1.5 <?php echo $post['user_liked'] ? 'text-primary' : 'text-gray-500 hover:text-primary'; ?> transition-colors">
                                        <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' <?php echo $post['user_liked'] ? '1' : '0'; ?>;">favorite</span>
                                        <span class="text-xs font-semibold"><?php echo $post['likes_count']; ?></span>
                                    </button>
                                </form>
                                <button class="flex items-center gap-1.5 text-gray-500 hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-xl">chat_bubble</span>
                                    <span class="text-xs font-semibold"><?php echo $post['comments_count']; ?> Comments</span>
                                </button>
                                <button class="flex items-center gap-1.5 text-gray-500 hover:text-primary transition-colors ml-auto">
                                    <span class="material-symbols-outlined text-xl">share</span>
                                </button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-orange-500">trending_up</span>
                            Trending Topics
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-primary font-bold uppercase tracking-tighter">Diet & Nutrition</p>
                                <a class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-primary block mt-1" href="#">Low-sodium recipe swap ideas</a>
                                <p class="text-[10px] text-gray-400 mt-1">128 active discussions</p>
                            </div>
                            <div>
                                <p class="text-xs text-primary font-bold uppercase tracking-tighter">Physical Therapy</p>
                                <a class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-primary block mt-1" href="#">Best seated exercises for home</a>
                                <p class="text-[10px] text-gray-400 mt-1">85 active discussions</p>
                            </div>
                            <div>
                                <p class="text-xs text-primary font-bold uppercase tracking-tighter">Success Stories</p>
                                <a class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-primary block mt-1" href="#">Returning to work after stroke</a>
                                <p class="text-[10px] text-gray-400 mt-1">42 active discussions</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-500">calendar_month</span>
                            Upcoming Webinars
                        </h3>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-primary/10 flex flex-col items-center justify-center text-primary flex-shrink-0">
                                    <span class="text-[10px] font-bold uppercase">Oct</span>
                                    <span class="text-lg font-bold leading-tight">15</span>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800 dark:text-white line-clamp-2">Managing Anxiety Post-Diagnosis</h4>
                                    <p class="text-[10px] text-gray-500 mt-1 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">schedule</span> 4:00 PM EST
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-primary/10 flex flex-col items-center justify-center text-primary flex-shrink-0">
                                    <span class="text-[10px] font-bold uppercase">Oct</span>
                                    <span class="text-lg font-bold leading-tight">18</span>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800 dark:text-white line-clamp-2">The Mediterranean Diet for Brain Health</h4>
                                    <p class="text-[10px] text-gray-500 mt-1 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">schedule</span> 11:00 AM EST
                                    </p>
                                </div>
                            </div>
                        </div>
                        <button class="mt-4 w-full py-2 bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 text-xs font-bold rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            View All Events
                        </button>
                    </div>

                    <div class="bg-gradient-to-br from-primary to-primary/80 p-5 rounded-xl shadow-lg shadow-primary/20 text-white relative overflow-hidden">
                        <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-7xl opacity-20 rotate-12">volunteer_activism</span>
                        <h4 class="text-sm font-bold relative z-10">Need Private Support?</h4>
                        <p class="text-[10px] mt-1 relative z-10 opacity-90">Book a 1-on-1 session with our mental health specialists.</p>
                        <button class="mt-4 bg-white text-primary px-4 py-2 rounded-lg text-xs font-bold hover:bg-opacity-90 relative z-10">
                            Book Session
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="fixed bottom-6 right-6">
        <div class="flex flex-col items-end gap-3">
            <div class="hidden max-w-xs bg-white dark:bg-surface-dark border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-2xl animate-fade-in" id="disclaimer">
                <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-relaxed">
                    NeuroNest Community is a peer support platform. Advice shared by users is not medical diagnosis. In case of emergency, contact your local emergency services immediately.
                </p>
            </div>
            <button class="bg-primary text-white w-12 h-12 rounded-full shadow-lg flex items-center justify-center hover:scale-105 transition-transform" onclick="document.getElementById('disclaimer').classList.toggle('hidden')">
                <span class="material-symbols-outlined">help</span>
            </button>
        </div>
    </div>
</body>
</html>