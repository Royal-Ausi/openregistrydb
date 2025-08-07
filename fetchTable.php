<?php
// session_start();
include 'includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$table = $data['table'] ?? '';
$sort = $data['sort'] ?? '';
$search = $data['search'] ?? '';

$allowedTables = [
  "master_register",
  "pensioners_register",
  "incoming_mail",
  "contractstaff_register",
  "semicurrent_records",
  "file_diary"
];

if (!in_array($table, $allowedTables)) {
  echo "div class= 'alert alert-danger'>Invalid table selected.</div>";
  exit();
}

$sql = "SELECT * FROM $table";
$params = [];

if ($search && in_array($table, $allowedTables)) {
  // Example: search in 'full_name' and 'file_number' columns
    $query .= " WHERE full_name LIKE ? OR file_number LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add sort
if ($sort) {
  $query .= "ORDER BY `$sort` DESC";
}

$stmt = $conn->prepare($query);

if ($params) {
  $types = str_repeat('s', count($params));
  $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<div class='alert alert-warning'>No records found.</div>";
  exit;
}

  // Output table
echo "<table class='table table-bordered table-striped'>";
echo "<thead><tr>";
// Output column headers
$fields = $result->fetch_fields();
foreach ($fields as $field) {
    echo "<th>" . htmlspecialchars(ucwords(str_replace("_", " ", $field->name))) . "</th>";
}
echo "</tr></thead><tbody>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($fields as $field) {
        echo "<td>" . htmlspecialchars($row[$field->name]) . "</td>";
    }
    echo "</tr>";
}
echo "</tbody></table>";

$stmt->close();
$conn->close();
?>