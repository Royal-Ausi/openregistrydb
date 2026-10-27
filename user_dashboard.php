<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
require_once __DIR__ . '/includes/session_jwt.php';

if (!isset($_SESSION["username"])) {
    header("Location: " . openregistry_uri('index.php'));
    exit();
}

$resolvedRole = openregistry_resolve_user_role_from_session();
if ($resolvedRole === 'admin') {
    header('Location: ' . openregistry_uri('admin_dashboard.php'));
    exit();
}
if ($resolvedRole === 'system_operator') {
    header('Location: ' . openregistry_uri('system_admin_dashboard.php'));
    exit();
}

or_enforce_session();

include 'includes/db.php';

$username = $_SESSION["username"];
$userRole = $resolvedRole;
$modules  = $_SESSION["assigned_modules"] ?? [];

$allModules = [
    "master_register"        => "Master Register",
    "pensioners_register"    => "Pensioners Register",
    "contractstaff_register" => "Contract Staff Register",
    "semicurrent_records"    => "Semi-Current Records",
    "file_diary"             => "File Diary",
    "subject_index"          => "Subject Index",
];

if (empty($modules)) {
    $modules = array_keys($allModules);
}

$editableModules = ($userRole === 'admin')
    ? $allModules
    : array_intersect_key($allModules, array_flip($modules));

// Stat cards
$totalEntries = 0;
$todayEntries = 0;
$userEntries  = 0;

foreach ($modules as $table) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$tableCheck || $tableCheck->num_rows === 0) continue;

    $r = $conn->query("SELECT COUNT(*) AS c FROM $table");
    if ($r) $totalEntries += (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) AS c FROM $table WHERE DATE(timestamp) = CURRENT_DATE");
    if ($r) $todayEntries += (int)$r->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM $table WHERE created_by = ?");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $userEntries += (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
    }
}

// recent activity of last 8 actions by this user across all tables
$recentActivity = [];
foreach ($modules as $table) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$tableCheck || $tableCheck->num_rows === 0) continue;

    $stmt = $conn->prepare(
        "SELECT created_by, timestamp FROM $table
         WHERE created_by = ?
         ORDER BY timestamp DESC LIMIT 3"
    );
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $recentActivity[] = [
                'user'  => $row['created_by'],
                'time'  => $row['timestamp'],
                'table' => $table,
            ];
        }
        $stmt->close();
    }
}
usort($recentActivity, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$recentActivity = array_slice($recentActivity, 0, 8);

// last login display
$lastLoginDisplay = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Open Registry</title>
  <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary:      #E08D3C;
      --primary-dark: #c97a2d;
      --primary-text: #FCF1E3;
      --sidebar-w:    250px;
    }
    *, *::before, *::after { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: #f4f5f7;
      color: #1a1a1a;
      padding-top: 56px; 
    }

    /* fixed sidebar */
    .sidebar {
      position: fixed;
      top: 56px; left: 0; bottom: 0;
      width: var(--sidebar-w);
      background: var(--primary);
      overflow-y: auto;
      z-index: 1050;
      transform: translateX(calc(-1 * var(--sidebar-w)));
      transition: transform .28s ease;
      box-shadow: 3px 0 12px rgba(0,0,0,.15);
    }
    .sidebar.open {
      transform: translateX(0);
    }
    .sidebar a {
      display: flex; align-items: center; gap: 10px;
      padding: 13px 20px;
      color: #f5f5f5;
      text-decoration: none;
      font-size: 14px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      transition: background .18s;
      cursor: pointer;
    }
    .sidebar a:hover { background: var(--primary-dark); }
    .sidebar-section-title {
      padding: 8px 20px;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: rgba(255,255,255,.55);
      font-weight: 600;
    }

    .sidebar-overlay {
      display: none;
      position: fixed; inset: 56px 0 0 0;
      background: rgba(0,0,0,.35);
      z-index: 1040;
    }
    .sidebar-overlay.show { display: block; }

    .main-content {
      padding: 24px 28px;
      min-height: calc(100vh - 56px);
      transition: margin-left .28s ease;
    }
    @media (min-width: 1024px) {
      .sidebar { transform: translateX(0); } /* always visible on large screens */
      .main-content { margin-left: var(--sidebar-w); }
      .sidebar-overlay { display: none !important; }
    }

    /* stat cards */
    .stat-card {
      background: var(--primary);
      color: #f5f5f5;
      border: none;
      border-radius: 12px;
      padding: 20px 22px;
      box-shadow: 0 4px 14px rgba(224,141,60,.25);
    }
    .stat-card h6 { opacity: .85; font-size: 13px; margin-bottom: 6px; }
    .stat-card h3 { font-weight: 800; font-size: 2rem; margin: 0; }

    /* widgets */
    .widget {
      background: #fff;
      border-radius: 12px;
      padding: 22px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
      border: 1px solid #eee;
    }
    .widget h5 {
      font-weight: 700; color: #333; margin-bottom: 14px;
      font-size: 15px;
    }
    .activity-list { list-style: none; padding: 0; margin: 0; }
    .activity-list li {
      padding: 9px 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
      color: #444;
      display: flex; align-items: center; gap: 8px;
    }
    .activity-list li:last-child { border-bottom: none; }
    .activity-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--primary); flex-shrink: 0;
    }
    .activity-empty { color: #999; font-style: italic; font-size: 13px; }

    /* modals */
    .modal-header { background: var(--primary); color: #f5f5f5; border-radius: 12px 12px 0 0; }
    .modal-header .btn-close { filter: invert(1); }
    .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
    .btn-primary { background: var(--primary) !important; border-color: var(--primary) !important; color: #f5f5f5 !important; }
    .btn-primary:hover { background: var(--primary-dark) !important; border-color: var(--primary-dark) !important; }

    /* responsive table container */
    @media (max-width: 767px) {
      .stat-card h3 { font-size: 1.6rem; }
      .main-content { padding: 14px; }
    }
  </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- sidebar -->
<div class="sidebar" id="sidebar">
  <a href="user_dashboard.php"><i class="#"></i> Dashboard</a>
  <a href="#" onclick="showEditModal(); return false;"><i class="#"></i> Edit Register</a>
  <a href="#" onclick="showViewDatabaseModal(); return false;"><i class="#"></i> View Database</a>
  <?php if (!empty($_SESSION['can_import'])): ?>
  <!-- <a href="import_data.php"><i class="#"></i> Import Data</a> -->
  <?php endif; ?>
  <!-- <div class="sidebar-section-title">Account</div> -->
  <a href="<?= openregistry_uri('signout.php') ?>"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- edit Register Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="#"></i>Edit Register</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Select Register</label>
          <select id="selectedRegister" class="form-select" required>
            <option value="">Choose a register…</option>
            <?php foreach ($editableModules as $k => $v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Action</label>
          <select id="editMode" class="form-select" required>
            <option value="new">Add new records</option>
            <option value="edit">Update existing records</option>
          </select>
        </div>
        <div class="mb-3" id="rowCountGroup">
          <label class="form-label fw-semibold">Number of Rows</label>
          <input type="number" id="rowCount" class="form-control" min="1" max="100" value="5" required>
        </div>
        <div class="mb-3" id="updateLimitGroup" style="display:none;">
          <label class="form-label fw-semibold">Records to Load</label>
          <select id="updateLimit" class="form-select">
            <option value="all">All records</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="200">200</option>
            <option value="500">500</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Start Editing</button>
      </div>
    </form>
  </div>
</div>

<!-- view database modal -->
<div class="modal fade" id="viewDatabaseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="#"></i>View Register</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <select id="selectedDatabaseTable" class="form-select">
          <option value="">Select a register…</option>
          <?php foreach ($allModules as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="viewSelectedTable()">View Table</button>
      </div>
    </div>
  </div>
</div>

<!-- main content -->
<div class="main-content">

  <!-- header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
      <h4 class="mb-1 fw-bold">Welcome, <?= htmlspecialchars($username) ?></h4>
      <p class="text-muted mb-0" style="font-size:13px;">
        Your activity overview &nbsp;
        <!-- <span class="badge" style="background:var(--primary);color:#f5f5f5;">
          <?= $userRole === 'admin' ? 'Admin' : 'User' ?>
        </span> -->
      </p>
    </div>
    
  </div>

  <!-- stats -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
      <div class="stat-card">
        <h6>Total Entries</h6>
        <h3><?= number_format($totalEntries) ?></h3>
      </div>
    </div>
    <div class="col-12 col-sm-4">
      <div class="stat-card">
        <h6>Entries Today</h6>
        <h3><?= number_format($todayEntries) ?></h3>
      </div>
    </div>
    <div class="col-12 col-sm-4">
      <div class="stat-card">
        <h6>Your Contributions</h6>
        <h3><?= number_format($userEntries) ?></h3>
      </div>
    </div>
  </div>

  <!-- widgets row -->
  <div class="row g-3">
    <!-- recent activity backend -->
    <div class="col-12 col-lg-7">
      <div class="widget">
        <h5><i class="#" style="color:var(--primary)"></i>Recent Activity</h5>
        <?php if (empty($recentActivity)): ?>
          <p class="activity-empty">No recent activity found. Start editing a register to see entries here.</p>
        <?php else: ?>
          <ul class="activity-list">
            <?php foreach ($recentActivity as $act): ?>
              <li>
                <span class="activity-dot"></span>
                <span>
                  <strong><?= htmlspecialchars($act['user']) ?></strong>
                  — <?= htmlspecialchars(ucwords(str_replace('_', ' ', $act['table']))) ?>
                  <span class="text-muted" style="font-size:11px;margin-left:6px;">
                    <?= date('d/m/Y H:i', strtotime($act['time'])) ?>
                  </span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- system info -->
    <div class="col-12 col-lg-5">
      <div class="widget">
        <h5><i class="#" style="color:var(--primary)"></i>System Info</h5>
        <ul class="activity-list">
          <li><span class="activity-dot"></span>Session active as of <?= $lastLoginDisplay ?></li>
          <li><span class="activity-dot"></span>Registers available: <?= count($allModules) ?></li>
          <li><span class="activity-dot"></span>Editable: <?= count($editableModules) ?></li>
          <li><span class="activity-dot"></span>Role: <?= $userRole === 'admin' ? 'Administrator' : 'Regular User' ?></li>
          <?php if ($userRole !== 'admin'): ?>
          <li>
            <span class="activity-dot"></span>
            Assigned:
            <?php
            $labels = array_map(fn($k) => $allModules[$k] ?? $k, array_keys($editableModules));
            echo htmlspecialchars(implode(', ', $labels));
            ?>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const editModalBs = new bootstrap.Modal(document.getElementById('editModal'));

function toggleSidebar() {
  const sb  = document.getElementById('sidebar');
  const ov  = document.getElementById('sidebarOverlay');
  const open = sb.classList.toggle('open');
  ov.classList.toggle('show', open);
}
function showEditModal()          { editModalBs.show(); }
function showViewDatabaseModal()  { new bootstrap.Modal(document.getElementById('viewDatabaseModal')).show(); }
function viewSelectedTable() {
  const t = document.getElementById('selectedDatabaseTable').value;
  if (!t) { alert('Please select a register.'); return; }
  window.location.href = `viewDatabase.php?table=${t}`;
}

// toggle row count vs update limit based on mode
document.getElementById('editMode').addEventListener('change', function() {
  const isEdit = this.value === 'edit';
  document.getElementById('rowCountGroup').style.display   = isEdit ? 'none'  : '';
  document.getElementById('updateLimitGroup').style.display = isEdit ? ''      : 'none';
});

document.getElementById('editForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const table = document.getElementById('selectedRegister').value;
  const mode  = document.getElementById('editMode').value;
  if (!table) { alert('Please select a register.'); return; }
  if (mode === 'new') {
    const rows = parseInt(document.getElementById('rowCount').value);
    if (!rows || rows < 1 || rows > 100) { alert('Enter 1–100 rows.'); return; }
    window.location.href = `edit_register.php?table=${table}&rows=${rows}&mode=new`;
  } else {
    const limit = document.getElementById('updateLimit').value;
    window.location.href = `edit_register.php?table=${table}&limit=${limit}&mode=edit`;
  }
});

(function heartbeat() {
  fetch('user_dashboard.php?or_heartbeat=1')
    .then(r => r.json())
    .then(d => { if (!d.alive) window.location.href = 'index.php?err=session'; })
    .catch(() => {});
  setTimeout(heartbeat, 4 * 60 * 1000); // every 4 min
})();
</script>
</body>
</html>
