<?php
// config/conn.php
// Use 127.0.0.1 to avoid Windows socket issues with 'localhost'
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "exam_alloc";
$port = 3306; // change to 3307 if you reconfigured XAMPP

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db, $port);

header('Content-Type: application/json');

if ($conn->connect_error) {
    echo json_encode([
        "status" => false,
        "data"   => "DB Connection failed: " . $conn->connect_error
    ]);
    exit;
}
$conn->set_charset("utf8mb4");
