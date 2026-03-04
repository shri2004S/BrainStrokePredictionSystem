<?php
include 'db_conn.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ✅ Fetch user data from DB
$sql = "SELECT firstname, lastname, email, phonenumber, password FROM signup WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "User not found!";
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANUDVEGA - Mental Health Journey</title>
<style>
* {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            color: #2d3748;
            min-height: 100vh;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(0, 0, 0, 0.1);
            padding: 2rem 0;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.05);
        }
        
        .nav-menu {
            padding: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 2rem;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            gap: 1rem;
            font-weight: 500;
        }

        .nav-item:hover {
            background: rgba(72, 187, 120, 0.1);
            color: #38a169;
            border-left-color: #48bb78;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(72, 187, 120, 0.15);
            color: #38a169;
            border-left-color: #48bb78;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem 0;
            }
        }

 .profile-card {
            background:#fff; border-radius:20px; padding:2rem; max-width:700px; margin:auto;
            box-shadow:0 10px 40px rgba(0,0,0,0.08);
        }
        .profile-header { display:flex; align-items:center; gap:1.5rem; margin-bottom:2rem; }
        .profile-avatar {
            width:100px; height:100px; border-radius:50%; background:#48bb78;
            display:flex; align-items:center; justify-content:center; font-size:2rem; color:white;
        }
        .profile-name { font-size:1.8rem; font-weight:700; color:#2d3748; }
        .profile-email { color:#718096; }
        .info-row { display:flex; justify-content:space-between; padding:0.8rem 0; border-bottom:1px solid #edf2f7; }
        .info-label { font-weight:600; color:#4a5568; }
        .info-value { color:#2d3748; }
        .edit-btn {
            margin-top:2rem; padding:0.8rem 1.5rem; background:linear-gradient(135deg,#48bb78,#38a169);
            color:white; border:none; border-radius:10px; cursor:pointer; font-weight:600;
        }
        .edit-btn:hover { background:linear-gradient(135deg,#38a169,#2f855a); }






</style>
</head>
<body>
    <div class="dashboard">
        <nav class="sidebar">
            <div class="logo-section">
                <div class="">
                    <img src="gemin.png" alt="ANUDVEGA Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 15px;">
                </div>
                
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon">🏠</span>
                    <span>Home</span>
                </a>
                <a href="chat.php" class="nav-item">
                    <span class="nav-icon">💬</span>
                    <span>Chat</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon">📝</span>
                    <span>Notes</span>
                </a>
                <a href="recommediation.php" class="nav-item">
                    <span class="nav-icon">⚙️</span>
                    <span>Recommendations</span>
                </a>
                <a href="Prediction.php" class="nav-item">
                    <span class="nav-icon">🤖</span>
                    <span>Prediction</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span>Community</span>
                </a>
                <a href="#" class="nav-item" style="position: relative;">
                    <span class="nav-icon">🔔</span>
                    <span>Notification</span>
                    <span class="notification-badge">3</span>
                </a>
                <a href="Emergnecy.php" class="nav-item">
                    <span class="nav-icon">🚨</span>
                    <span>Emergency</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon">📋</span>
                    <span>Activity Log</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon">👤</span>
                    <span>Profile</span>
                </a>
            </div>
        </nav>
        <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['firstname'],0,1)); ?>
            </div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($user['firstname']." ".$user['lastname']); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
        </div>

        <div class="profile-info">
            <div class="info-row">
                <div class="info-label">Phone</div>
                <div class="info-value"><?php echo htmlspecialchars($user['phonenumber']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Password</div>
                <div class="info-value"><?php echo htmlspecialchars($user['password']); ?></div>
            </div>
        </div>

        <button class="edit-btn">✏️ Edit Profile</button>
    </div>

    </body>

    </html>

       


  
    

   