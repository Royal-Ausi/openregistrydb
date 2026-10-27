<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_admin_tools_access();
include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_status_keywords($conn);

$username = $_SESSION["username"];

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kw_action'])) {
    header('Content-Type: application/json');
    $action  = $_POST['kw_action'];
    $keyword = trim((string)($_POST['keyword'] ?? ''));
    $kwId    = intval($_POST['kw_id'] ?? 0);

    if ($action === 'add') {
        if ($keyword === '')           { echo json_encode(['success'=>false,'error'=>'Keyword cannot be empty.']); exit; }
        if (mb_strlen($keyword) > 50)  { echo json_encode(['success'=>false,'error'=>'Max 50 characters.']); exit; }

        // Check duplicate first to give a clear error message
        $chk = $conn->prepare("SELECT id FROM status_keywords WHERE keyword = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $keyword);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($existing) {
                echo json_encode(['success'=>false,'error'=>"\"$keyword\" already exists."]);
                exit;
            }
        }

        $stmt = $conn->prepare("INSERT INTO status_keywords (keyword) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $keyword);
            $ok = $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();
            if ($ok && $newId > 0) {
                echo json_encode(['success'=>true,'id'=>$newId,'keyword'=>htmlspecialchars($keyword)]);
            } else {
                echo json_encode(['success'=>false,'error'=>$conn->error ?: 'Could not insert keyword.']);
            }
        } else {
            echo json_encode(['success'=>false,'error'=>'DB error: '.$conn->error]);
        }
        exit;
    }

    if ($action === 'edit') {
        if ($kwId <= 0)                { echo json_encode(['success'=>false,'error'=>'Invalid ID.']); exit; }
        if ($keyword === '')           { echo json_encode(['success'=>false,'error'=>'Keyword cannot be empty.']); exit; }
        if (mb_strlen($keyword) > 50)  { echo json_encode(['success'=>false,'error'=>'Max 50 characters.']); exit; }
        $stmt = $conn->prepare("UPDATE status_keywords SET keyword = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $keyword, $kwId);
            if ($stmt->execute()) {
                echo json_encode(['success'=>true,'keyword'=>htmlspecialchars($keyword)]);
            } else {
                $err = (stripos($conn->error,'unique') !== false || stripos($conn->error,'duplicate') !== false) ? "\"$keyword\" already exists." : $conn->error;
                echo json_encode(['success'=>false,'error'=>$err]);
            }
            $stmt->close();
        } else { echo json_encode(['success'=>false,'error'=>'DB error.']); }
        exit;
    }

    if ($action === 'delete') {
        if ($kwId <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid ID.']); exit; }
        $usageCount = 0;
        foreach (['master_register','contractstaff_register','subject_index'] as $tbl) {
            $r = $conn->query("SELECT COUNT(*) AS c FROM `$tbl` WHERE status_id = $kwId");
            if ($r) $usageCount += (int)$r->fetch_assoc()['c'];
        }
        if ($usageCount > 0) { echo json_encode(['success'=>false,'error'=>"Cannot delete: $usageCount record(s) still use this status."]); exit; }
        $stmt = $conn->prepare("DELETE FROM status_keywords WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $kwId);
            echo json_encode($stmt->execute() ? ['success'=>true] : ['success'=>false,'error'=>$conn->error]);
            $stmt->close();
        } else { echo json_encode(['success'=>false,'error'=>'DB error.']); }
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action.']); exit;
}

// ── Page data ─────────────────────────────────────────────────
$tables = [
    "master_register"        => "Master Register",
    "pensioners_register"    => "Pensioners Register",
    "contractstaff_register" => "Contract Staff Register",
    "semicurrent_records"    => "Semi-Current Records",
    "file_diary"             => "File Diary",
    "subject_index"          => "Subject Index",
];

function smColExists($conn, $tbl, $col) {
    $r = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
    return $r && $r->num_rows > 0;
}

// Keywords
$allKeywords = [];
$kwResult = $conn->query("SELECT id, keyword, created_at FROM status_keywords ORDER BY keyword ASC");
if ($kwResult) { while ($r=$kwResult->fetch_assoc()) $allKeywords[] = $r; }

// Status View
$currentTable = array_key_exists($_GET['table']??'', $tables) ? $_GET['table'] : 'master_register';
$statusFilter = $_GET['status'] ?? '';
$pkMap = ['master_register'=>'id','pensioners_register'=>'id','contractstaff_register'=>'serial_number',
          'semicurrent_records'=>'serial_number','file_diary'=>'serial_number','subject_index'=>'id'];
$currentPk = $pkMap[$currentTable] ?? 'id';
$hasSid = smColExists($conn, $currentTable, 'status_id');
$hasStr = smColExists($conn, $currentTable, 'status');

if ($hasSid) {
    $sql = "SELECT t.*, COALESCE(sk.keyword,'') AS status_display FROM `$currentTable` t LEFT JOIN status_keywords sk ON sk.id=t.status_id";
} else { $sql = "SELECT * FROM `$currentTable` t"; }

$params=[]; $where=[];
if ($statusFilter!=='') {
    if ($hasSid) { $where[]="sk.keyword=?"; $params[]=$statusFilter; }
    elseif ($hasStr) { $where[]="t.status=?"; $params[]=$statusFilter; }
}
if ($where) $sql.=" WHERE ".implode(' AND ',$where);
$sql.=" ORDER BY t.$currentPk DESC LIMIT 100";

$resultRows=[]; $resultCount=0;
$stmt=$conn->prepare($sql);
if ($stmt) {
    if ($params) { $types=str_repeat('s',count($params)); $stmt->bind_param($types,...$params); }
    if ($stmt->execute()) {
        $res=$stmt->get_result();
        while ($row=$res->fetch_assoc()) $resultRows[]=$row;
        $resultCount=count($resultRows);
    }
    $stmt->close();
}

// Analytics
$totalPer=[]; $distPer=[];
foreach ($tables as $tk=>$tn) {
    $r=$conn->query("SELECT COUNT(*) AS c FROM `$tk`");
    $totalPer[$tn]=$r?(int)$r->fetch_assoc()['c']:0;
    $distPer[$tn]=[];
    $hid=smColExists($conn,$tk,'status_id'); $hst=smColExists($conn,$tk,'status');
    if ($hid) $q="SELECT sk.keyword AS status,COUNT(*) AS count FROM `$tk` t LEFT JOIN status_keywords sk ON sk.id=t.status_id GROUP BY sk.keyword";
    elseif ($hst) $q="SELECT status,COUNT(*) AS count FROM `$tk` GROUP BY status";
    else $q=null;
    if ($q) { $sr=$conn->query($q); if($sr){while($sr2=$sr->fetch_assoc()) $distPer[$tn][$sr2['status']??'']=$sr2['count'];} }
}

$filterOpts = array_column($allKeywords,'keyword') ?: ["Active","Semi-active","Inactive","Transferred","Closed","Archived","Deceased","Resigned","Didnt Report"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Status Manager | Open Registry</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{--primary:#E08D3C;--primary-dark:#c97a2d;--primary-text:#f5f5f5;--border:#ddd;}
*{box-sizing:border-box;}
body{margin:0;font-family:'Segoe UI',sans-serif;background:#fff;padding-top:60px;}
.navbar{background:#E08D3C;color:#f5f5f5;display:flex;align-items:center;justify-content:space-between;
  position:fixed;top:0;width:100%;z-index:1000;padding:8px 20px;box-shadow:0 2px 4px rgba(0,0,0,.1);}
.navbar,.navbar *{color:#f5f5f5;}
.navbar a:hover,.navbar button:hover{color:#f5f5f5!important;background:transparent!important;text-decoration:none;}
.navbar-logo{max-height:30px;border-radius:8px;}
.navbar-brand{font-weight:700;font-size:18px;margin:0;}
.btn-outline-light{border-color:rgba(255,255,255,.5);color:#f5f5f5;}
.btn-outline-light:hover{background:transparent!important;border-color:rgba(255,255,255,.5)!important;color:#f5f5f5!important;}
.btn-primary{background:var(--primary)!important;border-color:var(--primary)!important;color:#f5f5f5!important;}
.btn-primary:hover{background:var(--primary-dark)!important;border-color:var(--primary-dark)!important;}
.nav-tabs{border-bottom:2px solid var(--primary);margin-bottom:20px;}
.nav-tabs .nav-link{color:#333;border:none;border-bottom:3px solid transparent;padding:11px 18px;font-weight:500;}
.nav-tabs .nav-link:hover{border-bottom-color:var(--primary);color:var(--primary);}
.nav-tabs .nav-link.active{color:var(--primary);border-bottom-color:var(--primary);font-weight:600;background:transparent;}
.card{background:#F7E5D9;border:1px solid var(--border);border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.08);}
.card-header{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border-radius:8px 8px 0 0;padding:14px 18px;}
.stats-card{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border:none;}
.filter-section{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;padding:18px;border-radius:8px;margin-bottom:18px;}
.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding:12px 16px;background:#F7E5D9;border-radius:8px;}
.table tbody tr:hover{background:rgba(224,141,60,.08);}
.status-active{background:#d4edda;}.status-semi{background:#fff3cd;}.status-transferred{background:#d1ecf1;}
.status-closed{background:#f8d7da;}.status-archived{background:#e2e3e5;}.status-deceased{background:#e8e8e8;}
.kw-add-card{background:#FFFFFF;border:1px solid var(--border);border-radius:3px;padding:8px;margin-bottom:10px;box-shadow:0 2px 6px rgba(0,0,0,.08);}
.kw-add-card h6{color:#000;font-weight:700;margin-bottom:12px;}
.kw-table th{background:var(--primary);color:#fff;}
.kw-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:.82rem;font-weight:600;background:var(--primary);color:#f5f5f5;}
.inline-edit-input{border:1px solid var(--primary);border-radius:4px;padding:3px 8px;font-size:.88rem;width:160px;}
.chart-container{background:#fff;padding:18px;border-radius:8px;margin-bottom:18px;box-shadow:0 2px 6px rgba(0,0,0,.08);}
</style>
</head>
<body>
<nav class="navbar">
  <div class="d-flex align-items-center">
    <img src="includes/images/mlhud jpeg 6.jpg" alt="Logo" class="navbar-logo me-3">
    <span class="navbar-brand">Admin Status Manager</span>
  </div>
  <div class="d-flex gap-2">
    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">Home</a>
    <button class="btn btn-outline-light btn-sm" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
  </div>
</nav>

<div class="container mt-3">
  <ul class="nav nav-tabs" id="smTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-view"    type="button"><i class="#"></i>Status View</button></li>
    <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-keywords" type="button"><i class="#"></i>Keyword List</button></li>
    <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-analytics"type="button"><i class="#"></i>Analytics</button></li>
    <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-import"  type="button"><i class="#"></i>Import Data</button></li>
  </ul>

  <div class="tab-content">

    <!-- ── STATUS VIEW ── -->
    <div class="tab-pane fade show active" id="tab-view" role="tabpanel">
      <div class="toolbar">
        <h5 class="mb-0">Filter Records by Status</h5>
        <button class="btn btn-success btn-sm" onclick="exportFiltered()"><i class="fas fa-download me-1"></i>Export Filtered</button>
      </div>
      <div class="filter-section">
        <form method="GET" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Select Register</label>
            <select name="table" class="form-select" onchange="this.form.submit()">
              <?php foreach($tables as $tk=>$tn): ?><option value="<?=$tk?>"<?=$currentTable===$tk?' selected':''?>><?=$tn?></option><?php endforeach;?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Filter by Status</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
              <option value="">All Statuses</option>
              <?php foreach($filterOpts as $s): ?><option value="<?=htmlspecialchars($s)?>"<?=$statusFilter===$s?' selected':''?>><?=htmlspecialchars($s)?></option><?php endforeach;?>
            </select>
          </div>
        </form>
      </div>
      <!-- <div class="row mb-3 g-3">
        <div class="col-12 col-sm-4"><div class="card stats-card"><div class="card-body text-center"><h6>Filtered Records</h6><h4><?=$resultCount?></h4></div></div></div>
        <div class="col-12 col-sm-4"><div class="card stats-card"><div class="card-body text-center"><h6>Register</h6><p class="mb-0"><?=$tables[$currentTable]?></p></div></div></div>
        <div class="col-12 col-sm-4"><div class="card stats-card"><div class="card-body text-center"><h6>Status</h6><p class="mb-0"><?=$statusFilter?:'All'?></p></div></div></div>
      </div> -->
      <div class="card shadow">
        <div class="card-header"><h5 class="mb-0"><?=$tables[$currentTable]?> Records <span class="badge ms-2" style="background:rgba(255,255,255,.3)"><?=$resultCount?></span></h5></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
              <thead style="background:var(--primary);color:#fff;"><tr><th>ID</th><th>File Ref</th><th>Name / Designation</th><th>Status</th><th>Created By</th><th>Date</th></tr></thead>
              <tbody>
              <?php if($resultCount>0): foreach($resultRows as $row):
                $sd=$row['status_display']??$row['status']??'';
                $sc=strtolower(str_replace([' ','-'],['','-'],$sd)); ?>
                <tr class="status-<?=$sc?>">
                  <td><?=$row['id']??$row['serial_number']??'N/A'?></td>
                  <td><?=htmlspecialchars($row['file_number']??$row['file_ref_number']??$row['file_reference_number']??'N/A')?></td>
                  <td><?=htmlspecialchars($row['full_name']??$row['name_of_officer']??$row['file_title']??$row['current_file_title']??'N/A')?></td>
                  <td><span class="badge" style="background:#;color:rgba(7, 0, 0, 0.65);"><?=htmlspecialchars($sd?:'N/A')?></span></td>
                  <td><?=htmlspecialchars($row['created_by']??'N/A')?></td>
                  <td><?=!empty($row['timestamp'])?date('d/m/Y',strtotime($row['timestamp'])):'N/A'?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No records found</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── KEYWORD LIST ── -->
    <div class="tab-pane fade" id="tab-keywords" role="tabpanel">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="kw-add-card">
            <h6><i class="#"></i>Add New Keyword</h6>
            <div id="kw-add-alert"></div>
            <div class="input-group">
              <input type="text" id="newKeywordInput" class="form-control" placeholder="e.g ACTIVE" maxlength="50">
              <button type="button" class="btn btn-primary" onclick="addKeyword()"><i class="fas fa-plus"></i> Add</button>
            </div>
            <div class="form-text mt-1">Max 50 chars. No duplicates.</div>
          </div>
        </div>
        <div class="col-12 col-md-8">
          <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><i class="#"></i>Status Keywords</h5>
              <span class="badge bg-light text-dark" id="kw-count"><?=count($allKeywords)?> keywords</span>
            </div>
            <div class="card-body p-0">
              <div id="kw-table-alert" class="p-3" style="display:none;"></div>
              <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 kw-table">
                  <thead><tr><th>NO</th>
                  <th>Keyword</th>
                  <th>Added On</th>
                  <!-- <th>Actions</th> -->
                </tr></thead>
                  <tbody id="kwBody">
                  <?php if(empty($allKeywords)): ?>
                    <tr id="kw-empty"><td colspan="4" class="text-center text-muted py-3">No keywords yet</td></tr>
                  <?php else: foreach($allKeywords as $i=>$kw): ?>
                    <tr id="kw-row-<?=$kw['id']?>">
                      <td><?=$i+1?></td>
                      <td class="kw-cell" id="kw-cell-<?=$kw['id']?>"><span class="#"><?=htmlspecialchars($kw['keyword'])?></span></td>
                      <td><?=htmlspecialchars(substr($kw['created_at']??'',0,10))?></td>
                      <!-- <td>
                        <button type="button" class="btn btn-sm btn-warning me-1" title="Edit keyword" onclick="editKw(<?=$kw['id']?>,<?=json_encode($kw['keyword'])?>)"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-danger" title="Delete keyword" onclick="deleteKw(<?=$kw['id']?>,<?=json_encode($kw['keyword'])?>)"><i class="fas fa-trash"></i></button>
                      </td> -->
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ANALYTICS ── -->
    <div class="tab-pane fade" id="tab-analytics" role="tabpanel">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <div class="chart-container"><h6 class="mb-3">Total Entries per Register</h6><canvas id="barChart"></canvas></div>
        </div>
        <div class="col-12 col-md-6">
          <div class="chart-container">
            <h6 class="mb-2">Status Distribution</h6>
            <select id="pieRegister" class="form-select mb-3" onchange="updatePie()">
              <?php foreach($tables as $tk=>$tn): ?><option value="<?=$tk?>"><?=$tn?></option><?php endforeach;?>
            </select>
            <canvas id="pieChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- ── IMPORT DATA ── -->
    <div class="tab-pane fade" id="tab-import" role="tabpanel">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
          <div class="kw-add-card">
            <h6><i class="#"></i>Upload Data into Register</h6>
            <p style="font-size:13px;color:#555;margin-bottom:14px;">
              Upload a CSV file to import records. Column headings are matched automatically.
              Required columns must be present. For File Diary, Date Opened is optional and blank Security Grading defaults to OPEN.
            </p>
            <form id="quickImportForm" method="post" action="import_data.php" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label fw-semibold">Select Register</label>
              <select id="importRegister" name="register" class="form-select" onchange="showImportFields()" required>
                <option value="">Choose file </option>
                <option value="master_register">Master Register</option>
                <option value="pensioners_register">Pensioners Register</option>
                <option value="contractstaff_register">Contract Staff Register</option>
                <option value="semicurrent_records">Semi-Current Records</option>
                <option value="file_diary">File Diary</option>
                <option value="subject_index">Subject Index</option>
              </select>
            </div>
            <div id="importFields" class="mb-3" style="display:none;">
              <div class="alert" style="background:#F7E5D9;border:1px solid rgba(224,141,60,.3);font-size:12px;padding:10px 14px;">
                <strong>Required columns:</strong> <span id="importReqCols"></span>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Upload CSV file</label>
              <input type="file" id="importFile" name="data_file" class="form-control" accept=".csv,.xls,.xlsx" required>
              <div class="form-text">First row must contain column headers</div>
            </div>
            <button type="submit" class="btn btn-primary" id="importBtn">
              <i class="fas fa-upload me-1"></i> Upload 
            </button>
            </form>
            <div id="importProgress" class="mt-3" style="display:none;">
              <div class="progress"><div id="importBar" class="progress-bar" style="width:0%;background:var(--primary)"></div></div>
              <p class="text-muted mt-1" style="font-size:12px;" id="importStatus">Uploading…</p>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Constants injected from PHP 
const currentTable  = <?= json_encode($currentTable) ?>;
const currentFilter = <?= json_encode($statusFilter) ?>;
const totalPerReg   = <?= json_encode($totalPer) ?>;
const distPerReg    = <?= json_encode($distPer) ?>;
const tableNames    = <?= json_encode(array_values($tables)) ?>;
const tableKeys     = <?= json_encode(array_keys($tables)) ?>;

// Tab deep-link (?tab=keywords etc) 
document.addEventListener('DOMContentLoaded', function () {
  const p = new URLSearchParams(window.location.search);
  const tabMap = { 'view':'tab-view','keywords':'tab-keywords','analytics':'tab-analytics','import':'tab-import' };
  const target = tabMap[p.get('tab')] ?? null;
  if (target) {
    const btn = document.querySelector(`[data-bs-target="#${target}"]`);
    if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
  }
  // Enter key on add keyword input
  const inp = document.getElementById('newKeywordInput');
  if (inp) inp.addEventListener('keydown', e => { if (e.key==='Enter'){e.preventDefault();addKeyword();} });
  initCharts();
});

// Export filtered 
function exportFiltered() {
  const f = document.createElement('form');
  f.method = 'POST'; f.action = 'exportData.php';
  [['action','export'],['table',currentTable],['format','csv'],['limit','all'],['status_filter',currentFilter]]
    .forEach(([n,v]) => { const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; f.appendChild(i); });
  document.body.appendChild(f); f.submit(); document.body.removeChild(f);
}

// Alert helper
function showAlert(elId, msg, type, autohide=true) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.innerHTML = `<div class="alert alert-${type} alert-dismissible py-2 px-3 mb-2">
    ${esc(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
  el.style.display = 'block';
  if (autohide) setTimeout(() => { el.style.display='none'; el.innerHTML=''; }, 4000);
}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

// Add keyword
function addKeyword() {
  const inp = document.getElementById('newKeywordInput');
  const kw  = inp.value.trim();
  if (!kw)       { showAlert('kw-add-alert','Please enter a keyword.','warning'); return; }
  if (kw.length>50) { showAlert('kw-add-alert','Max 50 characters.','warning'); return; }
  fetch('admin_status_manager.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`kw_action=add&keyword=${encodeURIComponent(kw)}`
  }).then(r=>r.json()).then(d => {
    if (d.success) {
      inp.value='';
      appendKwRow(d.id, kw);
      showAlert('kw-add-alert',`"${esc(kw)}" added.`,'success');
    } else { showAlert('kw-add-alert', d.error||'Failed.','danger'); }
  }).catch(()=>showAlert('kw-add-alert','Network error.','danger'));
}

function appendKwRow(id, kw) {
  const empty = document.getElementById('kw-empty');
  if (empty) empty.remove();
  const cnt = document.getElementById('kwBody').querySelectorAll('tr').length;
  const tr = document.createElement('tr');
  tr.id = `kw-row-${id}`;
  tr.innerHTML = `
    <td>${cnt+1}</td>
    <td class="kw-cell" id="kw-cell-${id}"><span class="kw-badge">${esc(kw)}</span></td>
    <td>${new Date().toISOString().slice(0,10)}</td>
    <td>
      <button type="button" class="btn btn-sm btn-warning me-1" title="Edit keyword" onclick="editKw(${id},${JSON.stringify(kw)})"><i class="fas fa-edit"></i></button>
      <button type="button" class="btn btn-sm btn-danger" title="Delete keyword" onclick="deleteKw(${id},${JSON.stringify(kw)})"><i class="fas fa-trash"></i></button>
    </td>`;
  document.getElementById('kwBody').appendChild(tr);
  updateKeywordCount(1);
}

// Edit keyword 
function editKw(id, current) {
  const cell = document.getElementById(`kw-cell-${id}`);
  if (!cell) return;
  cell.innerHTML = `
    <input type="text" class="inline-edit-input" id="kw-edit-${id}" value="${esc(current)}" maxlength="50">
    <button type="button" class="btn btn-sm btn-success ms-1" title="Save keyword" onclick="saveKw(${id},${JSON.stringify(current)})"><i class="fas fa-check"></i></button>
    <button type="button" class="btn btn-sm btn-secondary ms-1" title="Cancel edit" onclick="cancelKw(${id},${JSON.stringify(current)})"><i class="fas fa-times"></i></button>`;
  const input = document.getElementById(`kw-edit-${id}`);
  if (input) {
    input.focus();
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); saveKw(id, current); }
      if (e.key === 'Escape') { e.preventDefault(); cancelKw(id, current); }
    });
  }
}

function saveKw(id, orig) {
  const inp = document.getElementById(`kw-edit-${id}`);
  const kw  = inp ? inp.value.trim() : '';
  if (!kw) { showAlert('kw-table-alert','Keyword cannot be empty.','warning'); return; }
  fetch('admin_status_manager.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`kw_action=edit&kw_id=${id}&keyword=${encodeURIComponent(kw)}`
  }).then(r=>r.json()).then(d => {
    if (d.success) {
      document.getElementById(`kw-cell-${id}`).innerHTML = `<span class="kw-badge">${esc(d.keyword)}</span>`;
      showAlert('kw-table-alert',`Updated to "${esc(d.keyword)}".`,'success');
    } else { showAlert('kw-table-alert',d.error||'Failed.','danger'); cancelKw(id,orig); }
  }).catch(()=>{ showAlert('kw-table-alert','Network error.','danger'); cancelKw(id,orig); });
}

function cancelKw(id, kw) {
  const cell = document.getElementById(`kw-cell-${id}`);
  if (cell) cell.innerHTML = `<span class="kw-badge">${esc(kw)}</span>`;
}

// ── Keyword: DELETE 
function deleteKw(id, kw) {
  if (!confirm(`Delete keyword "${kw}"?\n\nThis will fail if any records still use it.`)) return;
  fetch('admin_status_manager.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`kw_action=delete&kw_id=${id}`
  }).then(r=>r.json()).then(d => {
    if (d.success) {
      const row = document.getElementById(`kw-row-${id}`);
      if (row) row.remove();
      updateKeywordCount(-1);
      renumberKeywordRows();
      showAlert('kw-table-alert',`"${esc(kw)}" deleted.`,'success');
      if (!document.getElementById('kwBody').querySelector('tr')) {
        document.getElementById('kwBody').innerHTML='<tr id="kw-empty"><td colspan="4" class="text-center text-muted py-3">No keywords yet.</td></tr>';
      }
    } else { showAlert('kw-table-alert',d.error||'Failed.','danger'); }
  }).catch(()=>showAlert('kw-table-alert','Network error.','danger'));
}

function updateKeywordCount(delta) {
  const badge = document.getElementById('kw-count');
  if (!badge) return;
  const current = parseInt(badge.textContent, 10) || 0;
  const next = Math.max(0, current + delta);
  badge.textContent = `${next} keyword${next===1 ? '' : 's'}`;
}

function renumberKeywordRows() {
  document.querySelectorAll('#kwBody tr').forEach((row, index) => {
    const cell = row.querySelector('td:first-child');
    if (cell) cell.textContent = index + 1;
  });
}

// ── Charts 
let barChartInst=null, pieChartInst=null;

function initCharts() {
  // Bar chart
  const bctx = document.getElementById('barChart');
  if (!bctx) return;
  barChartInst = new Chart(bctx, {
    type:'bar',
    data:{ labels:Object.keys(totalPerReg), datasets:[{label:'Total Entries',data:Object.values(totalPerReg),backgroundColor:'#E08D3C',borderColor:'#c97a2d',borderWidth:1}] },
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
  });
  updatePie();
}

function updatePie() {
  const sel   = document.getElementById('pieRegister');
  if (!sel) return;
  const tk    = sel.value;
  const idx   = tableKeys.indexOf(tk);
  const tn    = tableNames[idx] ?? '';
  const data  = distPerReg[tn] ?? {};
  const labels= Object.keys(data);
  const vals  = Object.values(data);
  const colors= labels.map(s=>{
    const l=s.toLowerCase();
    if(l.includes('active')&&!l.includes('semi')) return '#d4edda';
    if(l.includes('semi'))     return '#fff3cd';
    if(l.includes('transfer')) return '#d1ecf1';
    if(l.includes('close'))    return '#f8d7da';
    if(l.includes('archive'))  return '#e2e3e5';
    if(l.includes('deceas'))   return '#e8e8e8';
    return '#E08D3C';
  });
  const pctx = document.getElementById('pieChart');
  if (!pctx) return;
  if (pieChartInst) pieChartInst.destroy();
  pieChartInst = new Chart(pctx, {
    type:'pie',
    data:{labels, datasets:[{data:vals, backgroundColor:colors, borderColor:'#fff', borderWidth:2}]},
    options:{responsive:true, plugins:{legend:{position:'bottom',labels:{padding:14,font:{size:12}}},
      tooltip:{callbacks:{label:ctx=>{const t=ctx.dataset.data.reduce((a,b)=>a+b,0);return `${ctx.label}: ${ctx.parsed} (${((ctx.parsed/t)*100).toFixed(1)}%)`;} }} }}
  });
}

// ── Import 
const importRequired = {
  master_register:        ['File Number','Status'],
  pensioners_register:    ['File Number','Full Name','Designation'],
  contractstaff_register: ['File Number','Name of Officer','Title','Status'],
  semicurrent_records:    ['File Reference Number','File Title','Action Category'],
  file_diary:             ['File Ref Number','File Title','Security Grading'],
  subject_index:          ['Current File Title','Current Index','Status'],
};

function showImportFields() {
  const reg = document.getElementById('importRegister').value;
  const div = document.getElementById('importFields');
  const sp  = document.getElementById('importReqCols');
  if (reg && importRequired[reg]) {
    sp.textContent = importRequired[reg].join(', ');
    div.style.display = '';
  } else { div.style.display = 'none'; }
}

</script>
</body>
</html>
