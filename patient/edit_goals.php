<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$patient_id = $_SESSION['patient_id'];

// Handle GET request - Fetch goals
if($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT * FROM goals_tracker WHERE user_id = ? ORDER BY id";
    $stmt = mysqli_prepare($conn, $query);
    
    if($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $goals = [];
        while($row = mysqli_fetch_assoc($result)) {
            $goals[] = $row;
        }
        
        echo json_encode(['success' => true, 'goals' => $goals]);
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

// Handle POST request - Update goals
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if(!isset($input['goals']) || !is_array($input['goals'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $success = true;
    $updated = 0;
    
    foreach($input['goals'] as $goal) {
        if(!isset($goal['id']) || !isset($goal['target_value'])) {
            continue;
        }
        
        $goal_id = intval($goal['id']);
        $target_value = floatval($goal['target_value']);
        
        // Verify the goal belongs to this user
        $check_query = "SELECT id FROM goals_tracker WHERE id = ? AND user_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        
        if($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "ii", $goal_id, $patient_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if(mysqli_fetch_assoc($check_result)) {
                // Update the goal
                $update_query = "UPDATE goals_tracker SET target_value = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                
                if($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "dii", $target_value, $goal_id, $patient_id);
                    if(mysqli_stmt_execute($update_stmt)) {
                        $updated++;
                    } else {
                        $success = false;
                    }
                    mysqli_stmt_close($update_stmt);
                }
            }
            mysqli_stmt_close($check_stmt);
        }
    }
    
    if($success && $updated > 0) {
        echo json_encode(['success' => true, 'message' => 'Goals updated successfully', 'updated' => $updated]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update goals']);
    }
}

mysqli_close($conn);
?>