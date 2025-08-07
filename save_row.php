<?php
session_start();
include '../includes/db.php';

$username = $_SESSION["username"];
$data = json_decode(file_get_contents("php://input"), true);
$row = $data["row"];

$file_number = $row[0];
$full_name = $row[1];
$designation = $row[2];
$date_opened = $row[3];
$status = $row[4];

$stmt = $conn->prepare("INSERT INTO master_register (file_number, full_name, designation, date_opened, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $file_number, $full_name, $designation, $date_opened, $status, $username);
$stmt->execute();
$stmt->close();

echo "Row saved successfully";
?>