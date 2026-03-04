<?php
include 'db_conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstname   = trim($_POST['firstname']);
    $lastname    = trim($_POST['lastname']);
    $email       = trim($_POST['email']);
    $phonenumber = trim($_POST['phonenumber']);
    $password    = trim($_POST['password']);

    if (empty($firstname) || empty($lastname) || empty($email) || empty($phonenumber) || empty($password)) {
        echo "<script>alert('Please fill all fields'); window.location='signup.php';</script>";
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check existing email
    $check = $conn->prepare("SELECT id FROM signup WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Email already registered. Please login.'); window.location='login.php';</script>";
        exit;
    }

    // Insert data
    $stmt = $conn->prepare("INSERT INTO signup (firstname, lastname, email, phonenumber, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $firstname, $lastname, $email, $phonenumber, $hashedPassword);

    if ($stmt->execute()) {
        echo "<script>alert('Signup successful! Please login.'); window.location='login.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $check->close();
    $conn->close();
}
?>