<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_system_operator();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

$back = openregistry_uri('system_admin_dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit();
}

$uid = (int)($_POST['user_id'] ?? 0);
$modules = trim((string)($_POST['assigned_module'] ?? ''));
$canImport = isset($_POST['can_import']) ? 1 : 0;
$canExport = isset($_POST['can_export']) ? 1 : 0;

$allowedKeys = ['master_register', 'pensioners_register', 'contractstaff_register', 'semicurrent_records', 'file_diary', 'subject_index'];
if ($uid <= 0) {
    header('Location: ' . $back . '?err=invalid');
    exit();
}

$parts = array_filter(array_map('trim', explode(',', $modules)));
foreach ($parts as $p) {
    if (!in_array($p, $allowedKeys, true)) {
        header('Location: ' . $back . '?err=module');
        exit();
    }
}
if (count($parts) > 3) {
    header('Location: ' . $back . '?err=maxmod');
    exit();
}

$moduleString = implode(',', $parts);
$stmt = $conn->prepare('UPDATE users SET assigned_module = ?, can_import = ?, can_export = ? WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('siii', $moduleString, $canImport, $canExport, $uid);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header('Location: ' . $back . '?saved=1');
exit();
