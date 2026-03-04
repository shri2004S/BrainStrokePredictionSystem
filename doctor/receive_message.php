<?php
$kafkaPath = "C:\\xampp\\htdocs\\kafka_2.13-3.0.1";

$command = "$kafkaPath\\bin\\windows\\kafka-console-consumer.bat --topic patient_doctor_chat --bootstrap-server localhost:9092 --max-messages 1";

$output = shell_exec($command);

echo json_encode(["message" => trim($output)]);
?>
