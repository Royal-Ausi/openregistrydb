<!-- create_user.php -->
<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_admin_tools_access();
include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

$message = "";
$messageType = "info";

function hasColumn(PgConnection $conn, string $column): bool {
    return openregistry_column_exists($conn, 'users', $column);
}

$hasUserType = hasColumn($conn, 'user_type');

// Handle change password POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $uid     = intval($_POST['user_id'] ?? 0);
    $newPass = trim($_POST['new_password'] ?? '');
    $adminPw = $_POST['admin_password'] ?? '';

    // Verify the admin's own password before allowing change
    $adminOk = false;
    $adminUsername = $_SESSION['username'] ?? '';
    if ($adminUsername !== '') {
        $chkStmt = $conn->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
        if ($chkStmt) {
            $chkStmt->bind_param('s', $adminUsername);
            $chkStmt->execute();
            $chkRow = $chkStmt->get_result()->fetch_assoc();
            $chkStmt->close();
            if ($chkRow && password_verify($adminPw, $chkRow['password'])) {
                $adminOk = true;
            }
        }
    }

    if (!$adminOk) {
        $message = "Your password is incorrect. Change not applied.";
        $messageType = "danger";
    } elseif ($uid <= 0 || strlen($newPass) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "danger";
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            $message = "Password updated successfully.";
            $messageType = "success";
        }
    }
}

// Handle create user POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['action'])) {
    $username      = trim($_POST["username"] ?? '');
    $email         = trim($_POST["email"] ?? '');
    $password      = $_POST["password"] ?? '';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $modules = [];
    if (isset($_POST["modules"])) {
        $modules = $_POST["modules"];
        if (count($modules) > 3) {
            $message = "Maximum 3 modules can be assigned to a user.";
            $messageType = "danger";
        } else {
            $module_string = implode(",", $modules);
            $role = 'user';
            if ($hasUserType) {
                $user_type = 'user';
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, assigned_module, role, user_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $email, $password_hash, $module_string, $role, $user_type);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, assigned_module, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $password_hash, $module_string, $role);
            }
            if ($stmt->execute()) {
                $message = "User \"$username\" created successfully.";
                $messageType = "success";
            } else {
                $message = "Error: " . $stmt->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
    } else {
        $message = "Please select at least one module.";
        $messageType = "danger";
    }
}

// Fetch only portal users (exclude admin + system_operator)
$portalWhere = openregistry_portal_user_where_sql();
$userResult = $conn->query("SELECT id, username, email, assigned_module, role, created_at FROM users WHERE {$portalWhere} ORDER BY username ASC");
$portalUsers = [];
if ($userResult) {
    while ($u = $userResult->fetch_assoc()) $portalUsers[] = $u;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create User | Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #E08D3C;
      --primary-dark: #e08d3c;
      --primary-light: #FCF1E3;
    }
    body {
      background: var(--primary-light);
      font-family: 'Segoe UI', Tahoma, sans-serif;
      margin: 0;
      padding-top: 70px;
    }
    .navbar {
      background: var(--primary);
      color: #f5f5f5;
      display: flex; align-items: center; justify-content: space-between;
      position: fixed; top: 0; width: 100%; z-index: 1000;
      padding: 8px 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,.12);
    }
    .navbar-logo { max-height: 38px; border-radius: 8px; }
    .navbar-brand { color: #f5f5f5; font-weight: 700; font-size: 17px; margin: 0; }
    .navbar *, .navbar a, .navbar button, .navbar span { color: #f5f5f5; cursor: pointer; }
    .navbar a:hover, .navbar button:hover { color: #f5f5f5 !important; background: transparent !important; text-decoration: none; }
    .navbar .btn-outline-light { border-color: rgba(255,255,255,.5); color: #f5f5f5; }
    .navbar .btn-outline-light:hover { background: transparent !important; border-color: rgba(255,255,255,.5) !important; color: #f5f5f5 !important; }
    .form-container {
      background: #fff;
      border: 1px solid rgba(224,141,60,.25);
      border-radius: 12px;
      padding: 28px;
      max-width: 760px;
      margin: 0 auto 24px;
      box-shadow: 0 4px 16px rgba(0,0,0,.07);
    }
    .form-container h4 { color: #000000; font-weight: 700; margin-bottom: 20px; }
    .btn-primary-or {
      background: var(--primary); border: none; color: #fff;
      font-weight: 600; border-radius: 8px; padding: 9px 24px;
      transition: background .15s;
    }
    .btn-primary-or:hover { background: var(--primary-dark); color: #fff; }
    /* user table */
    .user-table-wrap {
      background: #fff; border: 1px solid rgba(224,141,60,.2);
      border-radius: 12px; padding: 0; max-width: 900px;
      margin: 0 auto 40px; overflow: hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,.06);
    }
    .user-table-wrap .tbl-head {
      background: var(--primary); color: #fff;
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 20px;
    }
    .user-table-wrap .tbl-head h5 { margin: 0; font-size: 15px; font-weight: 700; }
    .user-table-wrap table thead th { background: var(--primary); color: #fff; border: none; font-size: 13px; padding: 10px 12px; white-space: nowrap; }
    .user-table-wrap table tbody td { font-size: 13px; padding: 9px 12px; vertical-align: middle; }
    .user-table-wrap table tbody tr:hover td { background: #fdf3ea; }
    .module-pill { display: inline-block; background: #fff5eb; color: #7a4210; border: 1px solid rgba(224,141,60,.3); border-radius: 20px; padding: 2px 8px; font-size: 11px; margin: 1px; }
    /* change password modal */
    .pw-modal-overlay { display: none; position: fixed; inset: 0; z-index: 1100; background: rgba(0,0,0,.45); align-items: center; justify-content: center; }
    .pw-modal-overlay.active { display: flex; }
    .pw-modal { background: #fff; border-radius: 14px; width: min(440px,96%); overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
    .pw-modal-head { background: var(--primary); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; }
    .pw-modal-body { padding: 22px; }
    .pw-modal-foot { padding: 14px 22px; text-align: right; border-top: 1px solid #eee; }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="d-flex align-items-center gap-2">
    <img src="includes/images/mlhud jpeg 6.jpg" alt="Logo" class="navbar-logo">
    <span class="navbar-brand">Create Users</span>
  </div>
  <div class="d-flex gap-2">
    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">Home</a>
    <button class="btn btn-outline-light btn-sm" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
  </div>
</nav>

<div class="container" style="max-width:900px;">

  <?php if ($message): ?>
  <div class="alert alert-<?= $messageType ?> alert-dismissible mt-3" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Create user form -->
  <div class="form-container mt-3">
    <h4><i class="#"></i>Add New User</h4>
    <form method="POST" action="">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Username</label>
          <input type="text" name="username" class="form-control" required autocomplete="off">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="createPasswordInput" class="form-control" required minlength="6">
            <button type="button" class="btn btn-outline-secondary" onclick="toggleCreatePassword()">
              <i class="fas fa-eye" id="createPwIcon"></i>
            </button>
          </div>
          <div class="form-text">Minimum 6 characters</div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Assign Modules </label>
          <div class="row row-cols-2 row-cols-md-3 g-2 p-3 bg-light rounded border">
            <?php
            $moduleOptions = [
              'master_register'        => 'Master Register',
              'pensioners_register'    => 'Pensioners Register',
              'contractstaff_register' => 'Contract Staff Register',
              'semicurrent_records'    => 'Semi-Current Records',
              'file_diary'             => 'File Diary',
              'subject_index'          => 'Subject Index',
            ];
            foreach ($moduleOptions as $val => $label): ?>
            <div class="col">
              <div class="form-check">
                <input class="form-check-input module-checkbox" type="checkbox" name="modules[]" value="<?= $val ?>" id="mod_<?= $val ?>">
                <label class="form-check-label" for="mod_<?= $val ?>" style="font-size:13px;"><?= $label ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="form-text">Users can only edit their assigned modules. Assign upto 3 only</div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn-primary-or"><i class="fas fa-plus me-1"></i>Create User</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Portal Users Table -->
  <div class="user-table-wrap">
    <div class="tbl-head">
      <h5><i class="#"></i>Portal Users (<?= count($portalUsers) ?>)</h5>
      <button class="btn btn-outline-light btn-sm" onclick="toggleUsersTable()">
        <i class="fas fa-eye me-1"></i><span id="toggleLabel">Show</span>
      </button>
    </div>
    <div id="usersTableDiv" style="display:none;">
      <?php if (empty($portalUsers)): ?>
        <p class="text-muted p-4">No portal users found.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Assigned Modules</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($portalUsers as $i => $u): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge" style="background:var(--primary);color:#fff;"><?= htmlspecialchars(strtoupper($u['role'] ?? 'user')) ?></span></td>
              <td>
                <?php
                $mods = array_filter(array_map('trim', explode(',', $u['assigned_module'] ?? '')));
                foreach ($mods as $m) {
                  echo '<span class="module-pill">' . htmlspecialchars($moduleOptions[$m] ?? $m) . '</span>';
                }
                if (empty($mods)) echo '<span class="text-muted small">None</span>';
                ?>
              </td>
              <td style="white-space:nowrap;"><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
              <td style="white-space:nowrap;">
                <button class="btn btn-sm btn-outline-warning" style="font-size:11px;"
                  onclick="openChangePw(<?= (int)$u['id'] ?>, <?= json_encode($u['username']) ?>)">
                  <i class="fas fa-key me-1"></i>Change Password
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="pw-modal-overlay" id="pwModalOverlay" onclick="if(event.target===this)closePwModal()">
  <div class="pw-modal">
    <div class="pw-modal-head">
      <span><i class="fas fa-key me-2"></i>Change Password</span>
      <button type="button" class="btn-close btn-close-white" onclick="closePwModal()"></button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="user_id" id="pwUserId">
      <div class="pw-modal-body">
        <p class="text-muted mb-3" style="font-size:13px;">
          Setting new password for: <strong id="pwUsername"></strong>
        </p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Your Admin Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" name="admin_password" id="adminPwInput" class="form-control" required placeholder="Enter your own password to confirm">
            <button type="button" class="btn btn-outline-secondary" onclick="toggleAdminPw()">
              <i class="fas fa-eye" id="adminPwIcon"></i>
            </button>
          </div>
          <div class="form-text text-danger">Required to authorise the change.</div>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold">New Password for User</label>
          <div class="input-group">
            <input type="password" name="new_password" id="pwInput" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
            <button type="button" class="btn btn-outline-secondary" onclick="togglePwInput()">
              <i class="fas fa-eye" id="pwEyeIcon"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="pw-modal-foot">
        <button type="button" class="btn btn-secondary btn-sm me-2" onclick="closePwModal()">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary-or">Save Password</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleUsersTable() {
  const div = document.getElementById('usersTableDiv');
  const lbl = document.getElementById('toggleLabel');
  const visible = div.style.display !== 'none';
  div.style.display = visible ? 'none' : 'block';
  lbl.textContent = visible ? 'Show' : 'Hide';
}

function openChangePw(id, username) {
  document.getElementById('pwUserId').value = id;
  document.getElementById('pwUsername').textContent = username;
  document.getElementById('pwInput').value = '';
  document.getElementById('pwModalOverlay').classList.add('active');
}

function closePwModal() {
  document.getElementById('pwModalOverlay').classList.remove('active');
}

function toggleCreatePassword() {
  const inp  = document.getElementById('createPasswordInput');
  const icon = document.getElementById('createPwIcon');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('fa-eye');
  icon.classList.toggle('fa-eye-slash');
}

function togglePwInput() {
  const inp  = document.getElementById('pwInput');
  const icon = document.getElementById('pwEyeIcon');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('fa-eye');
  icon.classList.toggle('fa-eye-slash');
}

// Module checkbox — max 3
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.module-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
      const checked = document.querySelectorAll('.module-checkbox:checked');
      if (checked.length > 3) {
        this.checked = false;
        alert('You can assign a maximum of 3 modules per user.');
      }
    });
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closePwModal(); });
});
</script>
</body>
</html>
