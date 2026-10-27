<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_system_operator();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

// always receive a clean JSON response.
if (function_exists('ob_start')) {
    ob_start();
}

header('Content-Type: application/json');
 

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID.']);
    exit();
}

$adminWhere = openregistry_admin_where_sql();
$sql = "DELETE FROM users WHERE id = ? AND ({$adminWhere})";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error preparing delete.']);
    exit();
}

$stmt->bind_param('i', $id);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    // clear any buffered output and return JSON
    if (function_exists('ob_get_level') && ob_get_level()) { while (ob_get_level()) { ob_end_clean(); } }
    echo json_encode(['success' => false, 'message' => 'Unable to delete admin account.']);
    exit();
}

echo json_encode(['success' => true]);
exit();
