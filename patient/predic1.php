<?php
session_start();
include 'db_conn.php';

$prediction_result = null;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["predict_stroke"])) {
    $url = "http://127.0.0.1:5000/api/predict";

    $data = array(
        "age" => $_POST["age"],
        "hypertension" => $_POST["hypertension"],
        "heart_disease" => $_POST["heart_disease"],
        "avg_glucose_level" => $_POST["avg_glucose_level"],
        "bmi" => $_POST["bmi"],
        "smoking_status" => $_POST["smoking_status"],
        "gender" => $_POST["gender"],
        "ever_married" => $_POST["ever_married"],
        "work_type" => $_POST["work_type"],
        "Residence_type" => $_POST["Residence_type"]
    );

    $options = array(
        "http" => array(
            "header"  => "Content-type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data)
        )
    );

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        $error_message = "Error contacting prediction API.";
    } else {
        $result = json_decode($response, true);
        $prediction_result = $result["prediction"]; // ← your ML model output

        // ✅ Save prediction to DB with input values
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];

            $stmt = $conn->prepare("
                INSERT INTO prediction_history 
                (user_id, age, hypertension, heart_disease, avg_glucose_level, bmi, smoking_status, gender, ever_married, work_type, residence_type, result) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiiiddssssss",
                $user_id,
                $data['age'],
                $data['hypertension'],
                $data['heart_disease'],
                $data['avg_glucose_level'],
                $data['bmi'],
                $data['smoking_status'],
                $data['gender'],
                $data['ever_married'],
                $data['work_type'],
                $data['Residence_type'],
                $prediction_result
            );
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
