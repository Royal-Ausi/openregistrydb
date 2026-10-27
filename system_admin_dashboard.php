<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_system_operator();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

$moduleLabels = [
    'master_register' => 'Master Register',
    'pensioners_register' => 'Pensioners Register',
    'contractstaff_register' => 'Contract Staff',
    'semicurrent_records' => 'Semi-Current',
    'file_diary' => 'File Diary',
    'subject_index' => 'Subject Index',
];

$helpRows = [];
$moduleCounts = [];
if ($stmt = $conn->prepare('SELECT id, email, question, submitted_at FROM help_submissions ORDER BY submitted_at DESC LIMIT 200')) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $helpRows[] = $row;
    }
    $stmt->close();
}

$adminWhere = openregistry_admin_where_sql();
$portalWhere = openregistry_portal_user_where_sql();

$userRows = [];
$userSql = "SELECT id, username, email, assigned_module, role, user_type, can_import, can_export FROM users WHERE {$portalWhere} ORDER BY username ASC";
if ($result = $conn->query($userSql)) {
    while ($row = $result->fetch_assoc()) {
        $userRows[] = $row;
        $assigned = trim((string)($row['assigned_module'] ?? ''));
        if ($assigned !== '') {
            $parts = array_filter(array_map('trim', explode(',', $assigned)));
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (!isset($moduleCounts[$part])) {
                    $moduleCounts[$part] = 0;
                }
                $moduleCounts[$part]++;
            }
        }
    }
    $result->free();
}

$adminRows = [];
$pendingAdmins = [];
$adminSql = "SELECT id, username, email, created_at, status FROM users WHERE {$adminWhere} ORDER BY username ASC";
if ($result = $conn->query($adminSql)) {
    while ($row = $result->fetch_assoc()) {
        $adminRows[] = $row;
    }
    $result->free();
}

$pendingSql = "SELECT id, username, email, created_at FROM users WHERE {$adminWhere} AND status = 'pending' ORDER BY username ASC";
if ($result = $conn->query($pendingSql)) {
    while ($row = $result->fetch_assoc()) {
        $pendingAdmins[] = $row;
    }
    $result->free();
}

$totalUsers = count($userRows);
$totalAdmins = count($adminRows);
$pendingCount = count($pendingAdmins);
$totalHelp = count($helpRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Operator — Open Registry</title>
    <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/app-base.css" rel="stylesheet">
    <style>
        :root { --primary: #E08D3C; --primary-light: #FCF1E3; --primary-dark: #c97a2d; --sidebar: #E08D3C; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #FCF1E3; color: #1a1f2e; }
        .page-shell { display: flex; min-height: 100vh; }
        .op-sidebar { width: 250px; flex-shrink: 0; position: fixed; inset: 0 auto auto 0; background: var(--sidebar); color: var(--primary-light); padding: 26px 18px 100px; overflow-y: auto; }
        .op-sidebar .brand { display: flex; gap: 12px; padding: 0 12px 24px; border-bottom: 1px solid rgba(255,255,255,.2); }
        .op-sidebar .brand-mark { width: 44px; height: 44px; background: rgba(255,255,255,.25); border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 1.1rem; }
        .op-sidebar .brand-title { margin: 0; color: #fff; font-size: 1rem; font-weight: 700; }
        .op-sidebar .brand-subtitle { margin: 0; color: rgba(252,241,227,.8); font-size: .82rem; }
        .badge-operator { display: inline-block; margin-top: 8px; background: rgba(255,255,255,.25); color: #fff; text-transform: uppercase; font-size: .72rem; letter-spacing: .08em; padding: 6px 10px; border-radius: 999px; }
        .nav-group { padding: 0 12px; margin-top: 28px; }
        .nav-group-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; color: rgba(252,241,227,.6); margin-bottom: 10px; }
        .nav-link { display: block; padding: 11px 14px; color: var(--primary-light); text-decoration: none; border-radius: 14px; margin-bottom: 6px; transition: background .2s ease, color .2s ease; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,.2); color: #fff; }
        .nav-link.active { box-shadow: inset 4px 0 0 0 #fff; }
        .nav-link.badged { display: flex; justify-content: space-between; align-items: center; }
        .op-sidebar .sign-out { position: absolute; left: 18px; right: 18px; bottom: 24px; }
        .op-sidebar .sign-out .btn { width: 100%; border-color: rgba(255,255,255,.5); color: #fff; }
        .op-sidebar .sign-out .btn:hover { background: rgba(255,255,255,.2); }
        .op-main { margin-left: 250px; flex: 1; padding: 30px 34px; }
        .op-section { display: none; }
        .op-section.active { display: block; }
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 2rem; letter-spacing: -.02em; }
        .page-header .subtitle { color: #59616f; font-size: .95rem; }
        .section-title { font-size: 1rem; font-weight: 700; color: #1a1f2e; margin-bottom: 16px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(190px,1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border: 1px solid rgba(224,141,60,.3); border-radius: 18px; padding: 22px; box-shadow: 0 10px 24px rgba(17,22,34,.06); }
        .stat-card .stat-label { display: block; font-size: .82rem; color: #6e7584; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
        .stat-card .stat-value { font-size: 2.4rem; font-weight: 800; color: var(--primary); }
        .quick-links { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
        .panel { background: #fff; border: 1px solid rgba(224,141,60,.2); border-radius: 18px; padding: 24px; box-shadow: 0 10px 26px rgba(17,22,34,.05); }
        .module-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); gap: 16px; }
        .module-card { background: #FCF1E3; border: 1px solid rgba(224,141,60,.22); border-radius: 16px; padding: 18px; min-height: 128px; display: flex; flex-direction: column; justify-content: space-between; }
        .module-card .count { font-size: 2.4rem; font-weight: 700; color: var(--primary); }
        .module-card .label { font-size: 1rem; color: #2a3140; margin-top: 10px; }
        .user-row { background: #fff; border: 1px solid rgba(224,141,60,.25); border-radius: 16px; padding: 20px; margin-bottom: 18px; }
        .user-top { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
        .user-name { font-weight: 700; color: #1a1f2e; font-size: 1rem; }
        .badge-role { display: inline-flex; align-items: center; background: var(--primary); color: var(--primary-light); padding: 6px 12px; border-radius: 999px; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
        .pill { display: inline-flex; align-items: center; background: #fff5eb; color: #2f363f; border: 1px solid rgba(224,141,60,.22); border-radius: 999px; padding: 6px 10px; font-size: .82rem; margin-right: 8px; margin-bottom: 8px; }
        .user-row .form-row { gap: 12px; }
        .form-control-sm { min-height: 42px; }
        .section-note { color: #5f6572; font-size: .9rem; margin-top: 8px; }
        .table-card { overflow-x: auto; }
        .table th, .table td { white-space: nowrap; }
        .action-buttons .btn { margin-right: 8px; margin-bottom: 6px; }
        .action-buttons .btn:last-child { margin-right: 0; }
        .status-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 14px; font-size: .82rem; font-weight: 700; }
        .status-active { background: #28a745; color: #fff; }
        .status-pending { background: #ffc107; color: #1a1f2e; }
        .status-inactive { background: #dc3545; color: #fff; }
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 1050; background: rgba(15,20,32,.55); align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .admin-modal { width: min(560px, 100%); background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 60px rgba(17,22,34,.28); }
        .admin-modal-header { background: var(--primary); color: #fff; padding: 20px 24px; }
        .admin-modal-body { padding: 24px; }
        .admin-modal-footer { padding: 20px 24px; text-align: right; border-top: 1px solid rgba(224,141,60,.2); }
        .admin-modal .form-label { margin-bottom: .35rem; font-weight: 700; }
        .admin-modal .alert { margin-bottom: 16px; }
        /* Bootstrap btn-primary override to match theme */
        .btn-primary { background-color: var(--primary) !important; border-color: var(--primary) !important; color: var(--primary-light) !important; }
        .btn-primary:hover { background-color: var(--primary-dark) !important; border-color: var(--primary-dark) !important; }
        @media (max-width: 960px) {
            .op-main { margin-left: 0; padding: 20px; }
            .op-sidebar { position: relative; width: 100%; height: auto; }
            .page-shell { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <aside class="op-sidebar">
        <div class="brand">
            <div class="brand-mark"><i class="#"></i></div>
            <div>
                <p class="brand-title">Open Registry</p>
                <p class="brand-subtitle">System Operator</p>
                <!-- <span class="badge-operator">Operator</span> -->
            </div>
        </div>

        <div class="nav-group">
            <!-- <div class="nav-group-title">Overview</div> -->
            <a href="#" class="nav-link active" data-target="overview" onclick="showSection('overview', this); return false;">Dashboard</a>
        <!-- </div>
        <div class="nav-group"> -->
            <!-- <div class="nav-group-title">Management</div> -->
            <a href="#" class="nav-link" data-target="users" onclick="showSection('users', this); return false;">Portal Users</a>
            <a href="#" class="nav-link" data-target="admins" onclick="showSection('admins', this); return false;">Admin Accounts</a>
            <a href="#" class="nav-link badged" data-target="approvals" onclick="showSection('approvals', this); return false;">Pending Approvals <?php if ($pendingCount > 0) { ?><span class="badge bg-danger text-white"><?= $pendingCount ?></span><?php } ?></a>
            <a href="#" class="nav-link" data-target="help" onclick="showSection('help', this); return false;">Help Submissions</a>
        <!-- </div>
        <div class="nav-group"> -->
            <!-- <div class="nav-group-title">Tools</div> -->
            <a href="<?= htmlspecialchars(openregistry_uri('admin_dashboard.php')) ?>" class="nav-link">Org Admin Dashboard</a>
            <a href="<?= htmlspecialchars(openregistry_uri('create_user.php')) ?>" class="nav-link">Create User</a>
            <a href="<?= htmlspecialchars(openregistry_uri('import_data.php')) ?>" class="nav-link">Import Data</a>
            <a href="<?= htmlspecialchars(openregistry_uri('admin_status_manager.php')) ?>" class="nav-link">Status Manager</a>
            <a href="<?= htmlspecialchars(openregistry_uri('viewDatabase.php')) ?>" class="nav-link">View Database</a>
            <a href="<?= htmlspecialchars(openregistry_uri('help_center.php')) ?>" class="nav-link">Help Center</a>
        <!-- </div>
        <div class="sign-out"> -->
            <a href="<?= htmlspecialchars(openregistry_uri('system_admin_logout.php')) ?>" class="btn btn-outline-light">Sign out</a>
        </div>
    </aside>

    <main class="op-main">
        <div class="page-header">
            <div>
                <h1>System Operator Dashboard</h1>
                <p class="subtitle">Manage users, admin accounts, help requests, and pending operator approvals.</p>
            </div>
        </div>

        <div id="section-overview" class="op-section active">
            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-label">Total Portal Users</span>
                    <span class="stat-value"><?= $totalUsers ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total Admin Accounts</span>
                    <span class="stat-value"><?= $totalAdmins ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Pending Approvals</span>
                    <span class="stat-value"><?= $pendingCount ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Help Submissions</span>
                    <span class="stat-value"><?= $totalHelp ?></span>
                </div>
            </div>

            <!-- <div class="quick-links">

                 <a href="<?= htmlspecialchars(openregistry_uri('create_user.php')) ?>" class="btn btn-primary">Create user</a>
                <a href="<?= htmlspecialchars(openregistry_uri('import_data.php')) ?>" class="btn btn-primary">Import data</a>
                <a href="<?= htmlspecialchars(openregistry_uri('admin_status_manager.php')) ?>" class="btn btn-primary">Status manager</a>
                <a href="<?= htmlspecialchars(openregistry_uri('viewDatabase.php')) ?>" class="btn btn-primary">View database</a>
                <a href="<?= htmlspecialchars(openregistry_uri('admin_dashboard.php')) ?>" class="btn btn-primary">Org admin dashboard</a>
            </div> -->

            <div class="panel">
                <div class="section-title">Module assignment counts</div>
                <div class="module-grid">
                    <?php foreach ($moduleLabels as $key => $label): ?>
                        <div class="module-card">
                            <div class="count"><?= $moduleCounts[$key] ?? 0 ?></div>
                            <div class="label"><?= htmlspecialchars($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="section-users" class="op-section">
            <div class="panel mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                    <div>
                        <h2 class="h5 mb-1">Portal Users</h2>
                        <p class="section-note">Total portal users: <?= $totalUsers ?></p>
                    </div>
                    <a href="<?= htmlspecialchars(openregistry_uri('create_user.php')) ?>" class="btn btn-primary btn-sm">Add user</a>
                </div>

                <?php if (empty($userRows)): ?>
                    <p class="text-muted">No portal users found.</p>
                <?php else: ?>
                    <?php foreach ($userRows as $user): ?>
                        <div id="user-row-<?= (int)$user['id'] ?>" class="user-row">
                            <div class="user-top">
                                <div class="user-name"><?= htmlspecialchars($user['username'] ?? '') ?></div>
                                <span class="badge-role"><?= htmlspecialchars(strtoupper((string)($user['role'] ?? ''))) ?></span>
                            </div>
                            <div class="mb-3"><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?></div>
                            <div class="mb-3">
                                <?php
                                $assignedModules = array_filter(array_map('trim', explode(',', (string)($user['assigned_module'] ?? ''))));
                                if (empty($assignedModules)) {
                                    echo '<span class="text-muted small">No assigned modules</span>';
                                } else {
                                    foreach ($assignedModules as $moduleKey) {
                                        $label = $moduleLabels[$moduleKey] ?? $moduleKey;
                                        echo '<span class="pill">' . htmlspecialchars($label) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                            <form method="post" action="<?= htmlspecialchars(openregistry_uri('system_admin_update_user.php')) ?>">
                                <div class="row row-cols-1 row-cols-lg-3 gx-3 gy-3 align-items-end">
                                    <div class="col">
                                        <label class="form-label">Assigned Modules</label>
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                        <input type="text" name="assigned_module" class="form-control form-control-sm" value="<?= htmlspecialchars($user['assigned_module'] ?? '') ?>" placeholder="e.g. master_register,file_diary">
                                    </div>
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="import_<?= (int)$user['id'] ?>" name="can_import" value="1" <?= !empty($user['can_import']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="import_<?= (int)$user['id'] ?>">Import</label>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="export_<?= (int)$user['id'] ?>" name="can_export" value="1" <?= !empty($user['can_export']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="export_<?= (int)$user['id'] ?>">Export</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex gap-2 flex-wrap">
                                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="confirmDeleteUser(<?= (int)$user['id'] ?>, <?= json_encode($user['username'] ?? '') ?>)">Delete</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <p class="section-note">Valid module keys: <?= htmlspecialchars(implode(', ', array_keys($moduleLabels))) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-admins" class="op-section">
            <div class="panel mb-4">
                <div class="section-title">Admin Accounts</div>
                <?php if (empty($adminRows)): ?>
                    <p class="text-muted">No admin accounts available.</p>
                <?php else: ?>
                    <div class="table-card">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminRows as $admin): ?>
                                    <?php
                                        $statusValue = strtolower((string)($admin['status'] ?? 'inactive'));
                                        $statusClass = 'status-inactive';
                                        $statusLabel = 'Inactive';
                                        if ($statusValue === 'active') {
                                            $statusClass = 'status-active';
                                            $statusLabel = 'Active';
                                        } elseif ($statusValue === 'pending') {
                                            $statusClass = 'status-pending';
                                            $statusLabel = 'Pending';
                                        }
                                    ?>
                                    <tr id="admin-row-<?= (int)$admin['id'] ?>">
                                        <td><?= (int)$admin['id'] ?></td>
                                        <td class="admin-username"><?= htmlspecialchars($admin['username'] ?? '') ?></td>
                                        <td class="admin-email"><?= htmlspecialchars($admin['email'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($admin['created_at'] ?? '') ?></td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                        <td class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-admin-btn" data-id="<?= (int)$admin['id'] ?>" data-username="<?= htmlspecialchars($admin['username'] ?? '') ?>" data-email="<?= htmlspecialchars($admin['email'] ?? '') ?>" data-status="<?= htmlspecialchars($statusValue) ?>">Edit</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteAdmin(<?= (int)$admin['id'] ?>, <?= json_encode($admin['username'] ?? '') ?>)">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-approvals" class="op-section">
            <div class="panel mb-4">
                <div class="section-title">Pending Approvals</div>
                <?php if (empty($pendingAdmins)): ?>
                    <div class="alert alert-info">No pending approvals.</div>
                <?php else: ?>
                    <div class="table-card">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Applied on</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingAdmins as $pending): ?>
                                    <tr id="approval-row-<?= (int)$pending['id'] ?>">
                                        <td><?= (int)$pending['id'] ?></td>
                                        <td><?= htmlspecialchars($pending['username'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($pending['email'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($pending['created_at'] ?? '') ?></td>
                                        <td class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-success" onclick="approveAdmin(<?= (int)$pending['id'] ?>)">Approve</button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="rejectAdmin(<?= (int)$pending['id'] ?>)">Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-help" class="op-section">
            <div class="panel mb-4">
                <div class="section-title">Help Submissions</div>
                <?php if (empty($helpRows)): ?>
                    <div class="alert alert-info">No help submissions yet.</div>
                <?php else: ?>
                    <div class="table-card">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th>Email</th>
                                    <th>Question</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($helpRows as $help): ?>
                                    <tr id="help-row-<?= (int)$help['id'] ?>">
                                        <td><?= htmlspecialchars($help['submitted_at'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($help['email'] ?? '') ?></td>
                                        <td><?= nl2br(htmlspecialchars($help['question'] ?? '')) ?></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteHelpRow(<?= (int)$help['id'] ?>)">Delete</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div id="admin-modal-overlay" class="modal-overlay" onclick="if (event.target === this) closeAdminModal();">
    <div class="admin-modal">
        <div class="admin-modal-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">Edit Admin</h5>
                <p class="mb-0 small">Update account details for the selected admin.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="closeAdminModal()"></button>
        </div>
        <form id="admin-edit-form" class="admin-modal-body" onsubmit="return submitAdminEdit(event);">
            <div id="admin-modal-error" class="alert alert-danger d-none"></div>
            <input type="hidden" name="id" id="admin-id" value="">
            <div class="mb-3">
                <label class="form-label" for="admin-username">Username</label>
                <input type="text" class="form-control" id="admin-username" name="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="admin-email">Email</label>
                <input type="email" class="form-control" id="admin-email" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="admin-status">Status</label>
                <select class="form-select" id="admin-status" name="status" required>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm me-2" onclick="closeAdminModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var operatorApiBase = <?= json_encode(rtrim(openregistry_uri(''), '/')) ?>;

    function operatorUrl(path) {
        return operatorApiBase + '/' + String(path).replace(/^\//, '');
    }

    function showSection(id, el) {
        document.querySelectorAll('.op-section').forEach(function(section) {
            section.classList.remove('active');
        });
        document.querySelectorAll('.op-sidebar .nav-link').forEach(function(link) {
            link.classList.remove('active');
        });
        var target = document.getElementById('section-' + id);
        if (target) {
            target.classList.add('active');
        }
        if (el) {
            el.classList.add('active');
        } else {
            var nav = document.querySelector('.op-sidebar .nav-link[data-target="' + id + '"]');
            if (nav) {
                nav.classList.add('active');
            }
        }
    }

    function openAdminModal(id, username, email, status) {
        document.getElementById('admin-id').value = id;
        document.getElementById('admin-username').value = username;
        document.getElementById('admin-email').value = email;
        document.getElementById('admin-status').value = status || 'active';
        document.getElementById('admin-modal-error').classList.add('d-none');
        document.getElementById('admin-modal-overlay').classList.add('active');
    }

    function closeAdminModal() {
        document.getElementById('admin-modal-overlay').classList.remove('active');
    }

    function submitAdminEdit(event) {
        event.preventDefault();
        var form = event.target;
        var data = new FormData(form);
        fetch(operatorUrl('operator_update_admin.php'), {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        }).then(function(response) {
            return response.json();
        }).then(function(result) {
            var errorBox = document.getElementById('admin-modal-error');
            if (!result.success) {
                errorBox.textContent = result.message || 'Unable to save changes.';
                errorBox.classList.remove('d-none');
                return;
            }
            var adminId = data.get('id');
            var row = document.getElementById('admin-row-' + adminId);
            if (row) {
                row.querySelector('.admin-username').textContent = data.get('username');
                row.querySelector('.admin-email').textContent = data.get('email');
                var statusCell = row.querySelector('.status-badge');
                var newStatus = data.get('status');
                statusCell.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                statusCell.className = 'status-badge ' + (newStatus === 'active' ? 'status-active' : (newStatus === 'pending' ? 'status-pending' : 'status-inactive'));
                var editBtn = row.querySelector('.edit-admin-btn');
                if (editBtn) {
                    editBtn.dataset.status = newStatus;
                    editBtn.dataset.username = data.get('username');
                    editBtn.dataset.email = data.get('email');
                }
            }
            setTimeout(closeAdminModal, 1000);
        }).catch(function() {
            var errorBox = document.getElementById('admin-modal-error');
            errorBox.textContent = 'Network error while saving admin.';
            errorBox.classList.remove('d-none');
        });
        return false;
    }

    function confirmDeleteAdmin(id, name) {
        // Delegate to centralized delete handler
        if (!confirm('Delete admin ' + name + ' (ID ' + id + ')?')) return;
        operatorDelete('admin', id, 'admin-row-');
    }

    function confirmDeleteUser(id, name) {
        // Delegate to centralized delete handler
        if (!confirm('Delete portal user ' + name + ' (ID ' + id + ')?')) return;
        operatorDelete('user', id, 'user-row-');
    }

    function updatePendingBadge(delta) {
        var badge = document.querySelector('.nav-link.badged .badge');
        if (!badge) return;
        var count = parseInt(badge.textContent, 10) || 0;
        count = Math.max(0, count + delta);
        badge.textContent = count;
    }

    function approveAdmin(id) {
        if (!confirm('Approve this admin request?')) {
            return;
        }
        var data = new URLSearchParams();
        data.append('admin_id', id);
        data.append('action', 'approve');
        fetch(operatorUrl('operator_update_admin_status.php'), {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.text().then(function(text) {
                try { return JSON.parse(text); } catch (e) { return { success: false, message: text || 'Invalid server response.' }; }
            });
        })
        .then(function(result) {
            if (result.success) {
                var row = document.getElementById('approval-row-' + id);
                if (row) row.remove();
                updatePendingBadge(-1);
            } else {
                alert(result.message || 'Unable to approve admin.');
            }
        }).catch(function() { alert('Network error while approving admin.'); });
    }

    function rejectAdmin(id) {
        if (!confirm('Reject this pending admin request?')) {
            return;
        }
        var data = new URLSearchParams();
        data.append('admin_id', id);
        data.append('action', 'reject');
        fetch(operatorUrl('operator_update_admin_status.php'), {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.text().then(function(text) {
                try { return JSON.parse(text); } catch (e) { return { success: false, message: text || 'Invalid server response.' }; }
            });
        })
        .then(function(result) {
            if (result.success) {
                var row = document.getElementById('approval-row-' + id);
                if (row) row.remove();
                updatePendingBadge(-1);
            } else {
                alert(result.message || 'Unable to reject admin.');
            }
        }).catch(function() { alert('Network error while rejecting admin.'); });
    }

    function deleteHelpRow(id) {
        if (!confirm('Delete help submission (ID ' + id + ')?')) return;
        operatorDelete('help', id, 'help-row-');
    }

    // Centralized delete handler for system operator actions.
    function operatorDelete(type, id, rowPrefix) {
        var endpoints = {
            'admin': 'operator_delete_admin.php',
            'user': 'operator_delete_user.php',
            'help': 'operator_delete_help.php'
        };
        var ep = endpoints[type];
        if (!ep) { alert('Unknown delete type.'); return; }
        fetch(operatorUrl(ep + '?id=' + encodeURIComponent(id)), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.text().then(function(text) {
                if (response.status === 403) {
                    alert('Access denied. Please log in as system operator.');
                    return { success: false };
                }
                try { return JSON.parse(text); } catch (e) { return { success: false, message: text || 'Invalid server response.' }; }
            });
        })
        .then(function(result) {
            if (result && result.success) {
                var row = document.getElementById(rowPrefix + id);
                if (row) row.remove();
                // reload only for admin/user to ensure view consistency
                if (type === 'admin' || type === 'user') window.location.reload();
            } else {
                alert((result && result.message) || 'Unable to delete.');
            }
        }).catch(function() { alert('Network error while deleting.'); });
    }

    document.addEventListener('DOMContentLoaded', function() {
        showSection('overview');
        document.querySelectorAll('.edit-admin-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                openAdminModal(this.dataset.id, this.dataset.username, this.dataset.email, this.dataset.status);
            });
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAdminModal();
            }
        });
    });
</script>
</body>
</html>
