<?php
session_start();
include 'includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$table = $data['table'] ?? '';
$sort = $data['sort'] ?? '';
$search = $data['search'] ?? '';
$limit = intval($data['limit'] ?? 50);

$allowedTables = [
  "master_register",
  "pensioners_register",
  "incoming_mail",
  "contractstaff_register",
  "semicurrent_records",
  "file_diary"
];

if (!in_array($table, $allowedTables)) {
  echo "<div class='alert alert-danger'>Invalid table selected.</div>";
  exit();
}

$sql = "SELECT * FROM $table";
$params = [];
$whereConditions = [];

if ($search && in_array($table, $allowedTables)) {
  // Dynamic search based on table structure
  switch ($table) {
    case "master_register":
      $whereConditions[] = "(full_name LIKE ? OR file_number LIKE ? OR designation LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
      break;
    case "incoming_mail":
      $whereConditions[] = "(subject LIKE ? OR sender LIKE ? OR mail_no LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
      break;
    case "pensioners_register":
      $whereConditions[] = "(full_name LIKE ? OR pension_number LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      break;
    case "contractstaff_register":
      $whereConditions[] = "(full_name LIKE ? OR contract_number LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      break;
    case "semicurrent_records":
      $whereConditions[] = "(file_number LIKE ? OR full_name LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      break;
    case "file_diary":
      $whereConditions[] = "(file_number LIKE ? OR subject LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      break;
    default:
      $whereConditions[] = "1=1"; // No search for unknown tables
  }
}

// Build WHERE clause
if (!empty($whereConditions)) {
  $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

// Add sort
if ($sort) {
  $sql .= " ORDER BY `$sort` DESC";
}

// Add limit
if ($limit > 0) {
  $sql .= " LIMIT $limit";
}

$stmt = $conn->prepare($sql);

if ($stmt) {
  if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
  }
  
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    echo "<div class='alert alert-warning'>No records found.</div>";
    $stmt->close();
    exit;
  }

  // Output table
  echo "<div class='table-responsive'>";
  echo "<table class='table table-bordered table-striped table-hover'>";
  echo "<thead class='table-dark'><tr>";
  
  // Output column headers
  $fields = $result->fetch_fields();
  foreach ($fields as $field) {
    echo "<th>" . htmlspecialchars(ucwords(str_replace("_", " ", $field->name))) . "</th>";
  }
  echo "</tr></thead><tbody>";
  
  while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($fields as $field) {
      $value = $row[$field->name];
      // Format dates if they look like dates
      if (strpos($field->name, 'date') !== false && $value) {
        $value = date('d/m/Y', strtotime($value));
      }
      echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
  }
  echo "</tbody></table>";
  echo "</div>";
  
  // Add record count
  echo "<div class='mt-3 text-muted'>Total records: " . $result->num_rows . "</div>";
  
  $stmt->close();
} else {
  echo "<div class='alert alert-danger'>Error preparing query: " . $conn->error . "</div>";
}

$conn->close();
?>