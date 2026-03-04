<?php
$host = "localhost";
$user = "root";      // Your MySQL username
$pass = "";          // Your MySQL password
$db   = "brain";     // Your database name

// --- 1. Establish the Database Connection ---
$conn = new mysqli($host, $user, $pass, $db);

// Check for a connection error
if ($conn->connect_error) {
    // If the connection fails, stop the script and display a clear error message.
    die("Database connection failed: " . $conn->connect_error);
}

// --- 2. SQL to Create 'signup' Table ---
// This ensures the user table exists.
$sql_signup = "
CREATE TABLE IF NOT EXISTS signup (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql_signup)) {
    die("Error creating table (signup): " . $conn->error);
}

// --- 3. SQL to Create 'prediction_history' Table ---
// This ensures the table for storing predictions exists.
$sql_history = "
CREATE TABLE IF NOT EXISTS prediction_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    age INT,
    gender VARCHAR(50),
    avg_glucose_level FLOAT,
    bmi FLOAT,
    hypertension INT,
    heart_disease INT,
    smoking_status VARCHAR(100),
    ever_married VARCHAR(50),
    work_type VARCHAR(100),
    residence_type VARCHAR(50),
    risk_level VARCHAR(100),
    probability FLOAT,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES signup(id)
)";

if (!$conn->query($sql_history)) {
    die("Error creating table (prediction_history): " . $conn->error);
}
?>