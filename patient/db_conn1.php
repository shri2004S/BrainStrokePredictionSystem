<?php
// Database configuration
$host = "localhost";   // Database host
$user = "root";        // Database username
$pass = "";            // Database password
$db   = "brain";    // Database name

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Uncomment to test connection
// echo "Connected successfully";
?>
