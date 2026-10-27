<?php
// Debug version of streamlined_save.php to see what's happening
session_start();

// Log the request
error_log("=== STREAMLINED SAVE DEBUG ===");
error_log("Session username: " . ($_SESSION["username"] ?? "NOT SET"));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? "NOT SET"));

// Get JSON input
$rawInput = file_get_contents("php://input");
error_log("Raw input: " . $rawInput);

$input = json_decode($rawInput, true);
error_log("Decoded input: " . print_r($input, true));

if (!$input || !isset($input['table']) || !isset($input['row'])) {
    error_log("Invalid data received");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    error_log("User not logged in");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

include '../includes/db.php';

if (!$conn) {
    error_log("Database connection failed");
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$table = $input['table'];
$rowData = $input['row'];
$username = $_SESSION["username"];

error_log("Table: $table");
error_log("Row data: " . print_r($rowData, true));
error_log("Username: $username");

// Define allowed tables and their columns
$allowedTables = [
    "master_register" => ["file_number", "full_name", "designation", "date_opened", "date_closed", "status"]
];

// Define required fields for each table
$requiredFields = [
    "master_register" => ["file_number", "full_name", "designation", "status"]
];

// Validate table
if (!array_key_exists($table, $allowedTables)) {
    error_log("Invalid table: $table");
    echo json_encode(['success' => false, 'error' => 'Invalid table']);
    exit();
}

$columns = $allowedTables[$table];
$tableRequiredFields = $requiredFields[$table];

// Validate required fields
$missingFields = [];
foreach ($tableRequiredFields as $requiredField) {
    $fieldIndex = array_search($requiredField, $columns);
    if ($fieldIndex === false || empty(trim($rowData[$fieldIndex]))) {
        $missingFields[] = $requiredField;
    }
}

if (!empty($missingFields)) {
    error_log("Missing fields: " . implode(", ", $missingFields));
    echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(", ", $missingFields)]);
    exit();
}

// Prepare SQL statement for INSERT
$placeholders = implode(",", array_fill(0, count($columns) + 1, "?"));
$colNames = implode(",", $columns) . ",created_by";
$sql = "INSERT INTO $table ($colNames) VALUES ($placeholders)";

error_log("SQL: $sql");

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

// Bind parameters (row data + created_by)
$params = array_merge($rowData, [$username]);
$types = str_repeat("s", count($params));

error_log("Parameters: " . print_r($params, true));

// Use call_user_func_array to properly bind parameters
$bindParams = array_merge([$types], $params);
call_user_func_array([$stmt, 'bind_param'], makeValuesReferenced($bindParams));

// Execute the statement
if ($stmt->execute()) {
    $insertId = $conn->insert_id;
    error_log("Success: Record saved with ID $insertId");
    echo json_encode([
        'success' => true, 
        'message' => 'Record saved successfully',
        'id' => $insertId
    ]);
} else {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Failed to save record: ' . $stmt->error]);
}

$stmt->close();
$conn->close();

// Helper function to make array values referenced for bind_param
function makeValuesReferenced($arr) {
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}
?>
