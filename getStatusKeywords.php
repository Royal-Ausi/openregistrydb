<?php
/**
 * Returns all status_keywords as JSON array for use in form dropdowns.
 * Requires an authenticated session.
 */
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) && empty($_SESSION['system_operator'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_status_keywords($conn);

$result = $conn->query("SELECT id, keyword FROM status_keywords ORDER BY keyword ASC");
$keywords = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $keywords[] = ['id' => (int)$row['id'], 'keyword' => $row['keyword']];
    }
}

echo json_encode(['success' => true, 'keywords' => $keywords]);
$conn->close();
