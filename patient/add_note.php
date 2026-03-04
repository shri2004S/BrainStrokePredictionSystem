<?php
session_start();
require_once 'db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $user_id = $_SESSION['user_id'] ?? 1; // Replace with actual session user_id
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $icon_type = $_POST['icon_type'] ?? 'description';
    $icon_color = $_POST['icon_color'] ?? 'blue';
    
    // Validation
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        exit;
    }
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Content is required']);
        exit;
    }
    
    try {
        // Prepare SQL statement
        $sql = "INSERT INTO health_notes (user_id, title, category, content, icon_type, icon_color) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $user_id, $title, $category, $content, $icon_type, $icon_color);
        
        if ($stmt->execute()) {
            $note_id = $conn->insert_id;
            
            // Get the newly created note
            $get_note_sql = "SELECT *, DATE_FORMAT(created_at, '%b %d, %Y') as formatted_date 
                            FROM health_notes WHERE id = ?";
            $get_stmt = $conn->prepare($get_note_sql);
            $get_stmt->bind_param("i", $note_id);
            $get_stmt->execute();
            $result = $get_stmt->get_result();
            $note = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Health note added successfully!',
                'note' => $note
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add note']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>