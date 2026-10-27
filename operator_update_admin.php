<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_system_operator();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$username = trim((string)($_POST['username'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID.']);
    exit();
}

if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username is required.']);
    exit();
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Valid email is required.']);
    exit();
}

$allowedStatuses = ['active', 'pending', 'inactive'];
if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

$adminWhere = openregistry_admin_where_sql();
$sql = "UPDATE users SET username = ?, email = ?, status = ?, role = 'admin', user_type = 'admin' WHERE id = ? AND ({$adminWhere})";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error preparing update.']);
    exit();
}

$stmt->bind_param('sssi', $username, $email, $status, $id);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    echo json_encode(['success' => false, 'message' => 'Unable to update admin account.']);
    exit();
}

echo json_encode(['success' => true]);
exit();
