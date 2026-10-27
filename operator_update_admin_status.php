<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_system_operator();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

header('Content-Type: application/json');

$id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
$action = trim((string)($_POST['action'] ?? ''));
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID.']);
    exit();
}

if ($action !== 'approve' && $action !== 'reject') {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

$adminWhere = openregistry_admin_where_sql();
$affected = 0;
$success = false;

if ($action === 'approve') {
    $sql = "UPDATE users SET status = 'active', role = 'admin', user_type = 'admin' WHERE id = ? AND ({$adminWhere}) AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error preparing approval.']);
        exit();
    }
    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    $stmt->close();
} else {
    $sql = "DELETE FROM users WHERE id = ? AND ({$adminWhere}) AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error preparing rejection.']);
        exit();
    }
    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    $stmt->close();
}

if (!$success) {
    echo json_encode(['success' => false, 'message' => 'No matching pending admin found or no change applied.']);
    exit();
}

echo json_encode(['success' => true]);
exit();
