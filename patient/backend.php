<?php
session_start();
include 'db_conn.php'; // Includes your MySQLi database connection

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["predict_stroke"])) {
    
    // 1. Verify user is logged in
    if (!isset($_SESSION['email'])) {
        $_SESSION['error_message'] = "User not logged in. Please log in first.";
        header("Location: Prediction.php");
        exit();
    }

    // --- 2. Get user_id using correct MySQLi syntax ---
    $user_id = null;
    $stmt_user = $conn->prepare("SELECT id FROM signup WHERE email = ?");
    $stmt_user->bind_param("s", $_SESSION['email']); // Bind the email parameter
    $stmt_user->execute();                           // Execute the query
    $result_user = $stmt_user->get_result();         // Get the result
    $user = $result_user->fetch_assoc();             // Fetch the user data
    $stmt_user->close();

    if (!$user) {
        $_SESSION['error_message'] = "User not found in the database.";
        header("Location: Prediction.php");
        exit();
    }
    $user_id = $user['id'];

    // --- 3. Collect and sanitize form input ---
    $age             = (int) $_POST["age"];
    $gender          = htmlspecialchars($_POST["gender"]);
    $avg_glucose     = (float) $_POST["avg_glucose_level"];
    $bmi             = (float) $_POST["bmi"];
    $hypertension    = (int) $_POST["hypertension"];
    $heart_disease   = (int) $_POST["heart_disease"];
    $smoking_status  = htmlspecialchars($_POST["smoking_status"]);
    $ever_married    = htmlspecialchars($_POST["ever_married"]);
    $work_type       = htmlspecialchars($_POST["work_type"]);
    $residence_type  = htmlspecialchars($_POST["residence_type"]);

    // --- 4. Prepare data and call the Flask API ---
    $url = "http://127.0.0.1:5000/api/predict";
    $data = [
        "age" => $age, "gender" => $gender, "avg_glucose_level" => $avg_glucose,
        "bmi" => $bmi, "hypertension" => $hypertension, "heart_disease" => $heart_disease,
        "smoking_status" => $smoking_status, "ever_married" => $ever_married,
        "work_type" => $work_type,
        "residence_type" => $residence_type // Correct lowercase key
    ];
    
    $options = ["http" => [
        "header"  => "Content-type: application/json",
        "method"  => "POST",
        "content" => json_encode($data),
    ]];
    $context = stream_context_create($options);
    $result  = @file_get_contents($url, false, $context);

    // --- 5. Process API response ---
    if ($result === FALSE) {
        $_SESSION['error_message'] = "Error calling prediction API. Please ensure the backend Python service is running.";
    } else {
        $prediction = json_decode($result, true);
        
        // Improved error handling
        if (isset($prediction['error'])) {
            $_SESSION['error_message'] = "API Error: " . htmlspecialchars($prediction['error']);
        } elseif (isset($prediction['risk_level'])) {
            // Success: Insert prediction into the database
            $sql = "INSERT INTO prediction_history (user_id, age, gender, avg_glucose_level, bmi, hypertension, heart_disease, smoking_status, ever_married, work_type, residence_type, risk_level, probability, recommendations) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql);
            
            // Bind all 14 parameters with their types (i=integer, s=string, d=double)
            $stmt_insert->bind_param("issddiisssssds",
                $user_id, $age, $gender, $avg_glucose, $bmi, $hypertension, $heart_disease,
                $smoking_status, $ever_married, $work_type, $residence_type,
                $prediction['risk_level'], $prediction['probability'], $prediction['recommendations']
            );
            
            $stmt_insert->execute(); // Execute the insert query
            $stmt_insert->close();
            
            $_SESSION['prediction_result'] = $prediction;
        } else {
            $_SESSION['error_message'] = "Invalid API response received.";
        }
    }

    // --- 6. Redirect back to the frontend ---
    header("Location: Prediction.php");
    exit();
}
?>