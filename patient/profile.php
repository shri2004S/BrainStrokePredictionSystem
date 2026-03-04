<?php
session_start();
include 'db_conn.php';

// ✅ Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// --- Part 1: Fetch User Profile Data ---
$sql_user = "SELECT firstname, lastname, email, phonenumber FROM signup WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
if (!$stmt_user) {
    die("Prepare failed for user data: " . $conn->error);
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows === 0) {
    echo "User not found!";
    exit();
}
$user = $result_user->fetch_assoc();
$stmt_user->close();


// --- Part 2: Fetch Prediction History Data ---
$history = []; // Initialize an empty array for history
$sql_history = "SELECT id, created_at, risk_level, probability, recommendations FROM prediction_history WHERE user_id = ? ORDER BY created_at DESC";
$stmt_history = $conn->prepare($sql_history);
if ($stmt_history) {
    $stmt_history->bind_param("i", $user_id);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    // Fetch all history rows into the array
    while ($row = $result_history->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt_history->close();
}

// Close the connection after all data is fetched
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANUDVEGA - User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Your existing CSS from the first file goes here */
        /* ... (I've kept it the same as what you provided) ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%); color: #2d3748; min-height: 100vh; }
        .dashboard { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-right: 1px solid rgba(0, 0, 0, 0.1); padding: 2rem 0; box-shadow: 2px 0 20px rgba(0, 0, 0, 0.05); }
        .logo-section { padding: 0 2rem 2rem; text-align: center; }
        .logo-text { font-size: 1.5rem; font-weight: 700; color: #2d3748; letter-spacing: 0.5px; }
        .nav-menu { padding: 0; }
        .nav-item { display: flex; align-items: center; padding: 1rem 2rem; color: #4a5568; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; gap: 1rem; font-weight: 500; }
        .nav-item:hover { background: rgba(72, 187, 120, 0.1); color: #38a169; border-left-color: #48bb78; transform: translateX(5px); }
        .nav-item.active { background: rgba(72, 187, 120, 0.15); color: #38a169; border-left-color: #48bb78; }
        .nav-icon { font-size: 1.2rem; width: 24px; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .profile-card { background: #fff; border-radius: 20px; padding: 2rem; max-width: 900px; margin: 0 auto 2rem auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); }
        .profile-header { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: #48bb78; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; }
        .profile-name { font-size: 1.8rem; font-weight: 700; color: #2d3748; }
        .profile-email { color: #718096; }
        .info-row { display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #edf2f7; }
        .info-label { font-weight: 600; color: #4a5568; }
        .info-value { color: #2d3748; }
        .edit-btn { margin-top: 2rem; padding: 0.8rem 1.5rem; background: linear-gradient(135deg, #48bb78, #38a169); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; }
        .edit-btn:hover { background: linear-gradient(135deg, #38a169, #2f855a); }
        .history-card { background: #fff; border-radius: 20px; padding: 2rem; max-width: 900px; margin: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); }
    </style>
</head>
<body>
    <div class="dashboard">
       <nav class="sidebar">
            <div class="logo-section">
                <div class="">
                    <img src="shri.png" alt="ANUDVEGA Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 15px;">
                </div>
                <div class="logo-text">NeuroNest</div>
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
                <a href="logout.php" class="nav-item" >
                    <span class="nav-icon">🚪</span>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
        <main class="main-content">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['firstname'] . " " . $user['lastname']); ?></div>
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
                        <div class="info-value">********</div>
                    </div>
                </div>
               
                <a href="logout.php" class="edit-btn" style="text-decoration: none; display:inline-block; text-align:center;">🚪 Logout</a>
            </div>

            <div class="history-card mt-4">
                <h3>Your Prediction History</h3>
                <hr>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Risk Level</th>
                                <th>Probability</th>
                                <th>Recommendations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($history)) {
                                foreach ($history as $row) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['risk_level']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['probability']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['recommendations']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>You have no prediction history yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>