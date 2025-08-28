<?php
session_start();
include 'includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$table = $data["table"] ?? "";
$rows = $data["rows"] ?? [];
$created_by = $_SESSION["username"] ?? '';

$allowedTables = [
  "master_register" => ["file_number", "full_name", "designation", "date_opened", "date_closed", "status"],
  "incoming_mail" => ["mail_no", "subject", "sender", "date_received", "action_taken"]
];

if (!array_key_exists($table, $allowedTables)) {
  echo json_encode(["success" => false, "errors" => ["Invalid table"]]);
  exit();
}

$columns = $allowedTables[$table];
$saved = 0;
$errors = [];

foreach ($rows as $row) {
  if (count($row) !== count($columns)) {
    $errors[] = "Column count mismatch";
    continue;
  }
  if ($table === "master_register" && ($row[0] === "" || $row[5] === "")) {
    $errors[] = "Required fields missing";
    continue;
  }
  $placeholders = implode(",", array_fill(0, count($columns), "?"));
  $colNames = implode(",", $columns);
  $sql = "INSERT INTO $table ($colNames, created_by) VALUES ($placeholders, ?)";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    // Create parameters array for bind_param
    $params = array_merge($row, [$created_by]);
    $types = str_repeat("s", count($params));
    
    // Use call_user_func_array to properly bind parameters
    $bindParams = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], makeValuesReferenced($bindParams));
    
    if ($stmt->execute()) {
      $saved++;
    } else {
      $errors[] = $stmt->error;
    }
    $stmt->close();
  } else {
    $errors[] = $conn->error;
  }
}

// Helper function to make array values referenced for bind_param
function makeValuesReferenced($arr) {
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}

echo json_encode(["success" => $saved > 0, "saved" => $saved, "errors" => $errors]);
?>