<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_system_operator();
require_once __DIR__ . '/includes/db.php';

// always receive a clean JSON response.
if (function_exists('ob_start')) {
    ob_start();
}

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    if (function_exists('ob_get_level') && ob_get_level()) { while (ob_get_level()) { ob_end_clean(); } }
    echo json_encode(['success' => false, 'message' => 'Invalid help submission ID.']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM help_submissions WHERE id = ?');
if (!$stmt) {
    if (function_exists('ob_get_level') && ob_get_level()) { while (ob_get_level()) { ob_end_clean(); } }
    echo json_encode(['success' => false, 'message' => 'Database error preparing delete.']);
    exit();
}

$stmt->bind_param('i', $id);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    if (function_exists('ob_get_level') && ob_get_level()) { while (ob_get_level()) { ob_end_clean(); } }
    echo json_encode(['success' => false, 'message' => 'Unable to delete submission.']);
    exit();
}

if (function_exists('ob_get_level') && ob_get_level()) { while (ob_get_level()) { ob_end_clean(); } }
echo json_encode(['success' => true]);
exit();
