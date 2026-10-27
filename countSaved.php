<?php
include 'includes/db.php';
$table = $_GET["table"] ?? "";
$created_by = $_GET["created_by"] ?? "";
$count = 0;
if ($table && $created_by) {
  $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE created_by = ?");
  $stmt->bind_param("s", $created_by);
  $stmt->execute();
  $stmt->bind_result($count);
  $stmt->fetch();
  $stmt->close();
}
echo json_encode(["count" => $count]);
?>