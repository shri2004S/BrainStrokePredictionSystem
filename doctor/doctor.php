<?php
// Start the session to access user data
session_start();
include 'db_conn.php'; // Include your database connection

// 1. SESSION CHECK
// Redirect to the login page if the 'doctor_name' session variable isn't set.
if (!isset($_SESSION['doctor_name'])) {
    header("Location: login.php");
    exit();
}

// Get the doctor's name from the session for display or use later.
$doctorName = $_SESSION['doctor_name'];

// 2. PRE-FILL PATIENT NAME
// Check if a 'patient_name' was passed in the URL (e.g., from the chat page).
$prefilled_patient_name = '';
if (isset($_GET['patient_name'])) {
    // Sanitize the input to prevent XSS attacks before displaying it.
    $prefilled_patient_name = htmlspecialchars($_GET['patient_name']);
}

// 3. HANDLE FORM SUBMISSION
// This block runs only when the form is submitted using the POST method.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    
    // Retrieve all data from the form fields.
    $patientName = $_POST['patient_name'];
    $appointmentDate = $_POST['appointment_date'];
    $appointmentTime = $_POST['appointment_time'];
    $notes = $_POST['notes'];

    // DATABASE INSERT LOGIC
    try {
        $sql = "INSERT INTO appointments (patient_name, appointment_date, appointment_time, notes, doctor_name, status, request_timestamp) VALUES (?, ?, ?, ?, ?, 'Scheduled', NOW())";
        $stmt = $conn->prepare($sql);
        
        // Bind the variables to the placeholders in the SQL query.
        $stmt->bind_param("sssss", $patientName, $appointmentDate, $appointmentTime, $notes, $doctorName);
        
        // Execute the query to save the data.
        if ($stmt->execute()) {
            $successMessage = "Appointment for " . htmlspecialchars($patientName) . " scheduled successfully!";
        } else {
            $errorMessage = "Error scheduling appointment. Please try again.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// 4. FETCH TODAY'S APPOINTMENTS
$todaysAppointments = [];
try {
    $today = date('Y-m-d');
    $sql = "SELECT * FROM appointments WHERE doctor_name = ? AND appointment_date = ? ORDER BY appointment_time ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $doctorName, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $todaysAppointments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $fetchError = "Error fetching appointments: " . $e->getMessage();
}

// 5. FETCH UPCOMING APPOINTMENTS (Next 7 days)
$upcomingAppointments = [];
try {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $sql = "SELECT * FROM appointments WHERE doctor_name = ? AND appointment_date BETWEEN ? AND ? ORDER BY appointment_date ASC, appointment_time ASC LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $doctorName, $tomorrow, $nextWeek);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $upcomingAppointments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $upcomingError = "Error fetching upcoming appointments: " . $e->getMessage();
}

// 6. FETCH ALL APPOINTMENTS (FOR STATS)
$totalAppointments = 0;
$completedAppointments = 0;
$pendingAppointments = 0;
try {
    // Get total appointments count
    $sql_total = "SELECT COUNT(*) as total FROM appointments WHERE doctor_name = ?";
    $stmt_total = $conn->prepare($sql_total);
    $stmt_total->bind_param("s", $doctorName);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $totalAppointments = $result_total->fetch_assoc()['total'];
    $stmt_total->close();

    // Get completed appointments count
    $sql_completed = "SELECT COUNT(*) as completed FROM appointments WHERE doctor_name = ? AND status = 'Completed'";
    $stmt_completed = $conn->prepare($sql_completed);
    $stmt_completed->bind_param("s", $doctorName);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    $completedAppointments = $result_completed->fetch_assoc()['completed'];
    $stmt_completed->close();

    // Get pending appointments count
    $sql_pending = "SELECT COUNT(*) as pending FROM appointments WHERE doctor_name = ? AND status = 'Scheduled'";
    $stmt_pending = $conn->prepare($sql_pending);
    $stmt_pending->bind_param("s", $doctorName);
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    $pendingAppointments = $result_pending->fetch_assoc()['pending'];
    $stmt_pending->close();
} catch (Exception $e) {
    $statsError = "Error fetching statistics: " . $e->getMessage();
}

// 7. FETCH RECENT APPOINTMENTS (Last 5)
$recentAppointments = [];
try {
    $sql_recent = "SELECT * FROM appointments WHERE doctor_name = ? ORDER BY request_timestamp DESC LIMIT 5";
    $stmt_recent = $conn->prepare($sql_recent);
    $stmt_recent->bind_param("s", $doctorName);
    $stmt_recent->execute();
    $result_recent = $stmt_recent->get_result();
    
    while ($row = $result_recent->fetch_assoc()) {
        $recentAppointments[] = $row;
    }
    $stmt_recent->close();
} catch (Exception $e) {
    $recentError = "Error fetching recent appointments: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Appointments Dashboard - Doctor Dashboard</title>
    <style>
        /* All your CSS styles go here. Enhanced with additional styles. */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%); color: #2d3748; min-height: 100vh; display: flex; }
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-right: 1px solid rgba(0, 0, 0, 0.1); padding: 2rem 0; box-shadow: 2px 0 20px rgba(0, 0, 0, 0.05); position: fixed; height: 100vh; overflow-y: auto; }
        .logo-section { padding: 0 2rem 2rem; text-align: center; }
        .logo-text { font-size: 1.5rem; font-weight: 700; color: #2E7D32; letter-spacing: 0.5px; }
        .nav-menu { padding: 0; }
        .nav-item { display: flex; align-items: center; padding: 1rem 2rem; color: #4a5568; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; gap: 1rem; font-weight: 500; }
        .nav-item:hover, .nav-item.active { background: rgba(72, 187, 120, 0.1); color: #38a169; border-left-color: #48bb78; transform: translateX(5px); }
        .nav-item.active { font-weight: 600; }
        .main-content { flex: 1; margin-left: 280px; padding: 20px 30px; overflow-y: auto; }
        .header { background: white; padding: 25px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .welcome-title { font-size: 28px; font-weight: 700; color: #2E7D32; margin-bottom: 8px; }
        .welcome-subtitle { color: #666; font-size: 16px; }
        
        /* Stats Cards */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 25px; 
        }
        .stat-card { 
            background: linear-gradient(135deg, #4CAF50, #45a049); 
            color: white; 
            padding: 20px; 
            border-radius: 15px; 
            text-align: center; 
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); 
            transition: transform 0.3s ease; 
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card:nth-child(2) { background: linear-gradient(135deg, #4CAF50, #45a049); box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
        .stat-card:nth-child(3) { background: linear-gradient(135deg, #4CAF50, #45a049); box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
        .stat-card:nth-child(4) { background: linear-gradient(135deg, #4CAF50, #45a049); box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
        .stat-number { font-size: 2.5rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; }
        
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 18px; font-weight: 600; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #444; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease; }
        .form-group input:focus, .form-group textarea:focus { border-color: #4CAF50; outline: none; box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1); }
        .btn { padding: 12px 25px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 15px; }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-primary:hover { background: #45a049; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
        .success-message { padding: 15px; background-color: #e8f5e9; color: #2e7d32; border-radius: 8px; margin-bottom: 20px; text-align: center; border-left: 4px solid #4CAF50; }
        .error-message { padding: 15px; background-color: #ffebee; color: #c62828; border-radius: 8px; margin-bottom: 20px; text-align: center; border-left: 4px solid #f44336; }
        
        /* Appointment List Styles */
        .appointment-item { 
            padding: 15px 0; 
            border-bottom: 1px solid #f0f0f0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            transition: background-color 0.3s ease;
        }
        .appointment-item:hover { background-color: rgba(76, 175, 80, 0.05); margin: 0 -15px; padding: 15px; border-radius: 8px; }
        .appointment-item:last-child { border-bottom: none; }
        .appointment-info h4 { color: #333; margin-bottom: 5px; font-size: 16px; }
        .appointment-time { color: #4CAF50; font-weight: 600; font-size: 14px; }
        .appointment-date { color: #666; font-size: 13px; margin-top: 2px; }
        .appointment-notes { color: #666; font-size: 14px; margin-top: 5px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .status-badge { 
            padding: 4px 12px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        .status-scheduled { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e3f2fd; color: #1976d2; }
        .no-appointments { 
            text-align: center; 
            color: #666; 
            font-style: italic; 
            padding: 20px 0; 
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        /* Enhanced grid layout */
        .appointments-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 25px; 
            margin-bottom: 25px; 
        }
        
        /* Full width sections */
        .full-width-section {
            margin-bottom: 25px;
        }
        
        /* Recent appointments styling */
        .recent-appointments-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        /* Notification badge for nav items */
        .notification-badge {
            background: #ff4757;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .appointments-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .appointments-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .sidebar {
                transform: translateX(-100%);
            }
        }
        
        /* Enhanced card with hover effects */
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="logo-section">
            <div class="">
                <img src="shri.png" alt="ANUDVEGA Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 15px;">
            </div>
            <div class="logo-text">Doctor Dashboard</div>
        </div>
        
        <div class="nav-menu">
            <a href="doctor.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a>
            <a href="doctor_chat.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span>Messages</span>
            </a>
            <a href="Appointment.php" class="nav-item active">
                <span class="nav-icon">📝</span>
                <span>Appointment</span>
            </a>
                        <a href="profile.php" class="nav-item">
                <span class="nav-icon">👤</span>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="nav-item">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <div class="main-content">
        <!-- Enhanced Header with Doctor Welcome -->
        <header class="header">
            <h1 class="welcome-title">Welcome, Dr. <?php echo htmlspecialchars($doctorName); ?></h1>
            <p class="welcome-subtitle">Complete appointments dashboard - View, schedule, and manage all your patient appointments in one place.</p>
        </header>

        <!-- Display Messages -->
        <?php
        if (isset($successMessage)) {
            echo '<div class="success-message">' . $successMessage . '</div>';
        }
        if (isset($errorMessage)) {
            echo '<div class="error-message">' . $errorMessage . '</div>';
        }
        if (isset($fetchError)) {
            echo '<div class="error-message">' . $fetchError . '</div>';
        }
        if (isset($statsError)) {
            echo '<div class="error-message">' . $statsError . '</div>';
        }
        ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalAppointments; ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completedAppointments; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendingAppointments; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($todaysAppointments); ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
        </div>

        <!-- Main Appointments Grid -->
        <div class="appointments-grid">
            <!-- Today's Appointments -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Today's Appointments</h3>
                    <span class="status-badge status-scheduled"><?php echo count($todaysAppointments); ?> Total</span>
                </div>
                <div>
                    <?php if (empty($todaysAppointments)): ?>
                        <div class="no-appointments">No appointments scheduled for today.</div>
                    <?php else: ?>
                        <?php foreach ($todaysAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <h4><?php echo htmlspecialchars($appointment['patient_name']); ?></h4>
                                    <div class="appointment-time">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="appointment-notes">
                                            <?php echo htmlspecialchars($appointment['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upcoming Appointments</h3>
                    <span class="status-badge status-pending"><?php echo count($upcomingAppointments); ?> Upcoming</span>
                </div>
                <div>
                    <?php if (empty($upcomingAppointments)): ?>
                        <div class="no-appointments">No upcoming appointments in the next week.</div>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <h4><?php echo htmlspecialchars($appointment['patient_name']); ?></h4>
                                    <div class="appointment-time">
                                        <?php echo date('M j, g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?>
                                    </div>
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="appointment-notes">
                                            <?php echo htmlspecialchars($appointment['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Schedule New Appointment -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Schedule New Appointment</h3>
                </div>
                <form action="Appointment.php" method="POST">
                    <div class="form-group">
                        <label for="patient_name">Patient Name</label>
                        <input type="text" id="patient_name" name="patient_name" value="<?php echo $prefilled_patient_name; ?>" placeholder="Enter patient's full name" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment_date">Date</label>
                        <input type="date" id="appointment_date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment_time">Time</label>
                        <input type="time" id="appointment_time" name="appointment_time" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" placeholder="e.g., Annual checkup, follow-up..." rows="3"></textarea>
                    </div>
                    <button type="submit" name="add_appointment" class="btn btn-primary">Add Appointment</button>
                </form>
            </div>
        </div>

        <!-- Recent Appointments Section -->
        <div class="full-width-section">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Appointments</h3>
                    <span class="status-badge status-scheduled"><?php echo count($recentAppointments); ?> Recent</span>
                </div>
                <div class="recent-appointments-grid">
                    <?php if (empty($recentAppointments)): ?>
                        <div class="no-appointments">No recent appointments found.</div>
                    <?php else: ?>
                        <?php foreach ($recentAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <h4><?php echo htmlspecialchars($appointment['patient_name']); ?></h4>
                                    <div class="appointment-time">
                                        <?php echo date('M j, Y - g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?>
                                    </div>
                                    <div class="appointment-date">
                                        Scheduled: <?php echo date('M j, Y g:i A', strtotime($appointment['request_timestamp'])); ?>
                                    </div>
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="appointment-notes">
                                            <?php echo htmlspecialchars($appointment['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some JavaScript for enhanced interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus on patient name field
            const patientNameField = document.getElementById('patient_name');
            if (patientNameField && !patientNameField.value) {
                patientNameField.focus();
            }

            // Add hover effects to appointment items
            const appointmentItems = document.querySelectorAll('.appointment-item');
            appointmentItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(76, 175, 80, 0.05)';
                });
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                });
            });

            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const patientName = document.getElementById('patient_name').value.trim();
                const appointmentDate = document.getElementById('appointment_date').value;
                const appointmentTime = document.getElementById('appointment_time').value;

                if (!patientName || !appointmentDate || !appointmentTime) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });

            // Auto-hide success/error messages after 5 seconds
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>