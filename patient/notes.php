<?php
session_start();
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
$sql_name = "SELECT full_name FROM patient_signup WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$result_name = $stmt_name->get_result();

$patient_name = "Guest User";
if ($row_name = $result_name->fetch_assoc()) {
    $patient_name = $row_name['full_name'];
}
$stmt_name->close();

// Fetch existing notes
$sql = "SELECT *, DATE_FORMAT(created_at, '%b %d, %Y') as formatted_date 
        FROM health_notes WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notes = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Health Notes - NeuroNest</title>
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
                    "brand-mint": "#E8F5E9",
                    "background-light": "#F8FAFC",
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
    .note-card-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .sidebar-active {
        @apply bg-[var(--primary-light)] text-[var(--primary)] border-l-4 border-[var(--primary)];
    }
    .modal-backdrop {
        backdrop-filter: blur(4px);
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .modal-content {
        animation: slideUp 0.3s ease-out;
    }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen transition-colors duration-200">
<div class="flex h-screen overflow-hidden">
    <!-- Sidebar with Full Navigation -->
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
            <a class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-xl transition-colors" href="notes.php">
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
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-[var(--bg-light)] rounded-xl transition-colors" href="Emergency.php">
                <span class="material-icons-outlined">report_problem</span>
                <span class="font-medium text-red-500">Emergency</span>
            </a>
        </nav>

        <!-- ✅ UPDATED: Sidebar profile now shows dynamic user name & avatar -->
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

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Header -->
        <header class="h-16 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between px-8 bg-white dark:bg-surface-dark sticky top-0 z-10">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">Personal Health Notes</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative w-64">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-sm">search</span>
                    <input class="w-full bg-gray-100 dark:bg-gray-800 border-none rounded-xl pl-10 text-sm py-2 focus:ring-1 focus:ring-primary/30" placeholder="Search notes..." type="text"/>
                </div>
                <button id="newNoteBtn" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl hover:bg-opacity-90 transition-all font-semibold shadow-sm text-sm">
                    <span class="material-symbols-outlined text-sm">add</span> New Note
                </button>
                <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 mx-2"></div>
                <button class="p-2 text-gray-400 hover:text-primary transition-colors" onclick="document.documentElement.classList.toggle('dark')">
                    <span class="material-symbols-outlined">dark_mode</span>
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="p-8 max-w-7xl mx-auto w-full">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Recent Records</h2>
                    <p class="text-sm text-gray-500">Keep track of your symptoms, medications, and logs.</p>
                </div>
                <div class="flex items-center gap-2 bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-800 rounded-lg p-1">
                    <button class="p-1.5 bg-brand-mint text-primary rounded-md"><span class="material-symbols-outlined text-xl">grid_view</span></button>
                    <button class="p-1.5 text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-md"><span class="material-symbols-outlined text-xl">list</span></button>
                </div>
            </div>

            <!-- Notes Grid -->
            <div id="notesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($notes as $note): 
                    $iconColors = [
                        'red' => 'bg-red-50 dark:bg-red-900/20 text-red-500',
                        'blue' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-500',
                        'amber' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-500',
                        'purple' => 'bg-purple-50 dark:bg-purple-900/20 text-purple-500',
                        'green' => 'bg-green-50 dark:bg-green-900/20 text-green-500',
                    ];
                    $colorClass = $iconColors[$note['icon_color']] ?? $iconColors['blue'];
                ?>
                <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-gray-100 dark:border-gray-800 transition-all note-card-hover">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 <?= $colorClass ?> rounded-lg">
                            <span class="material-symbols-outlined"><?= htmlspecialchars($note['icon_type']) ?></span>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?= strtoupper($note['formatted_date']) ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2"><?= htmlspecialchars($note['title']) ?></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-3 leading-relaxed">
                        <?= htmlspecialchars($note['content']) ?>
                    </p>
                    <div class="mt-6 pt-4 border-t border-gray-50 dark:border-gray-800 flex justify-between items-center">
                        <span class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-gray-600 dark:text-gray-400"><?= htmlspecialchars($note['category']) ?></span>
                        <button class="text-primary text-sm font-semibold hover:underline">View Details</button>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Add New Note Card -->
                <button id="addNoteCard" class="border-2 border-dashed border-gray-200 dark:border-gray-800 p-6 rounded-2xl flex flex-col items-center justify-center gap-4 text-gray-400 hover:border-primary hover:text-primary transition-all group">
                    <span class="material-symbols-outlined text-4xl group-hover:scale-110 transition-transform">add_circle</span>
                    <span class="font-bold">Add New Health Note</span>
                </button>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div id="noteModal" class="fixed inset-0 bg-black/50 modal-backdrop hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-surface-dark rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto modal-content">
        <div class="sticky top-0 bg-white dark:bg-surface-dark border-b border-gray-200 dark:border-gray-800 p-6 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Add New Health Note</h2>
            <button id="closeModal" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                <span class="material-symbols-outlined text-gray-500">close</span>
            </button>
        </div>

        <form id="noteForm" class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Title *</label>
                <input type="text" name="title" required class="w-full px-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary/30 dark:bg-gray-800 dark:text-white" placeholder="e.g., Blood Pressure Log"/>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Category</label>
                <input type="text" name="category" class="w-full px-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary/30 dark:bg-gray-800 dark:text-white" placeholder="e.g., Important, Consultation"/>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Icon</label>
                <div class="grid grid-cols-5 gap-3">
                    <button type="button" class="icon-btn p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary transition-colors" data-icon="favorite" data-color="red">
                        <span class="material-symbols-outlined text-red-500">favorite</span>
                    </button>
                    <button type="button" class="icon-btn p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary transition-colors" data-icon="description" data-color="blue">
                        <span class="material-symbols-outlined text-blue-500">description</span>
                    </button>
                    <button type="button" class="icon-btn p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary transition-colors" data-icon="medication" data-color="amber">
                        <span class="material-symbols-outlined text-amber-500">medication</span>
                    </button>
                    <button type="button" class="icon-btn p-4 border-2 border-primary rounded-xl" data-icon="psychology" data-color="purple">
                        <span class="material-symbols-outlined text-purple-500">psychology</span>
                    </button>
                    <button type="button" class="icon-btn p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary transition-colors" data-icon="fitness_center" data-color="green">
                        <span class="material-symbols-outlined text-green-500">fitness_center</span>
                    </button>
                </div>
                <input type="hidden" name="icon_type" value="psychology"/>
                <input type="hidden" name="icon_color" value="purple"/>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Content *</label>
                <textarea name="content" required rows="6" class="w-full px-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary/30 dark:bg-gray-800 dark:text-white resize-none" placeholder="Describe your health note in detail..."></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary text-white px-6 py-3 rounded-xl font-semibold hover:bg-opacity-90 transition-all">
                    Save Note
                </button>
                <button type="button" id="cancelBtn" class="px-6 py-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 transition-all">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('noteModal');
const newNoteBtn = document.getElementById('newNoteBtn');
const addNoteCard = document.getElementById('addNoteCard');
const closeModal = document.getElementById('closeModal');
const cancelBtn = document.getElementById('cancelBtn');
const noteForm = document.getElementById('noteForm');
const iconBtns = document.querySelectorAll('.icon-btn');

// Open modal
function openModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close modal
function closeModalFn() {
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    noteForm.reset();
}

newNoteBtn.addEventListener('click', openModal);
addNoteCard.addEventListener('click', openModal);
closeModal.addEventListener('click', closeModalFn);
cancelBtn.addEventListener('click', closeModalFn);

// Close on backdrop click
modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModalFn();
});

// Icon selection
iconBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        iconBtns.forEach(b => b.classList.remove('border-primary'));
        iconBtns.forEach(b => b.classList.add('border-gray-200', 'dark:border-gray-700'));
        btn.classList.add('border-primary');
        btn.classList.remove('border-gray-200', 'dark:border-gray-700');
        
        noteForm.icon_type.value = btn.dataset.icon;
        noteForm.icon_color.value = btn.dataset.color;
    });
});

// Form submission
noteForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(noteForm);
    
    try {
        const response = await fetch('add_note.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Health note added successfully!');
            closeModalFn();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        console.error(error);
    }
});
</script>

</body>
</html>