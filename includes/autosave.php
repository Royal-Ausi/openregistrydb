<?php
session_start();
include 'db.php';

if (!isset($_SESSION["username"])) {
  echo "❌ Unauthorized";
  exit();
}

$username = $_SESSION["username"];
$data = json_decode(file_get_contents("php://input"), true);

$table = $data["table"] ?? '';
$row   = $data["row"] ?? [];

if (!$table || !is_array($row)) {
  echo "❌ Invalid data";
  exit();
}

// Define allowed tables and their column mappings
$allowedTables = [
  "master_register" => ["file_number", "full_name", "designation", "date_opened", "status"],
  "incoming_mail"   => ["mail_number", "date_received", "date_written", "subject", "author", "delegated_to", "attachment"],
  "contractstaff_register" => ["file_number", "name_of_officer", "title", "status"],
];

if (!array_key_exists($table, $allowedTables)) {
  echo "Table not allowed";
  exit();
}

$columns = $allowedTables[$table];
if (count($row) !== count($columns)) {
  echo "❌ Column count mismatch";
  exit();
}

$placeholders = implode(",", array_fill(0, count($columns), "?"));
$colNames = implode(",", $columns);

$sql = "INSERT INTO $table ($colNames, created_by) VALUES ($placeholders, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
  echo "❌ SQL Error: " . $conn->error;
  exit();
}

$stmt->bind_param(str_repeat("s", count($columns) + 1), ...$row, $username);

if ($stmt->execute()) {
  echo "✅ Row saved to $table";
} else {
  echo "❌ Error: " . $stmt->error;
}

$stmt->close();
?>