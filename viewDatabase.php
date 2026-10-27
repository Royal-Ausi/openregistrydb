<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
if (!isset($_SESSION['username']) && !openregistry_is_system_operator()) {
  header('Location: ' . openregistry_uri('index.php'));
  exit();
}
include 'includes/db.php';

$viewDbHomeUrl  = openregistry_dashboard_href();
$canExportViewer = openregistry_can_export();
$isAdmin = openregistry_is_admin() || openregistry_is_system_operator();

$availableTables = [
  "master_register"        => "Master Register",
  "pensioners_register"    => "Pensioners Register",
  "contractstaff_register" => "Contract Staff Register",
  "semicurrent_records"    => "Semi-Current Records",
  "file_diary"             => "File Diary",
  "subject_index"          => "Subject Index"
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Database | Open Registry</title>
  <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #E08D3C;
      --primary-dark: #ffaf5f;
      --primary-light: #FCF1E3;
      --primary-text: #f5f5f5;
    }

    body {
      background: var(--primary-light);
      font-family: 'Segoe UI', Tahoma, sans-serif;
      margin: 0;
      padding-top: 106px;
    }

    .navbar {
      background: var(--primary);
      color: var(--primary-text);
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      padding: 8px 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, .15);
    }

    .navbar-logo {
      max-height: 36px;
      border-radius: 8px;
    }

    .navbar-brand {
      color: var(--primary-text);
      font-weight: 700;
      font-size: 17px;
    }

    .navbar *,
    .navbar a,
    .navbar button,
    .navbar span {
      color: var(--primary-text);
      cursor: pointer;
    }

    .navbar a:hover,
    .navbar button:hover {
      color: var(--primary-text);
      background: transparent;
      text-decoration: none;
    }

    .navbar .btn-outline-light {
      border-color: rgba(255, 255, 255, .5);
      color: var(--primary-text);
    }

    .navbar .btn-outline-light:hover {
      background: transparent !important;
      border-color: rgba(255, 255, 255, .5) !important;
      color: var(--primary-text) !important;
    }

    .toolbar {
      position: fixed;
      top: 56px;
      left: 0;
      right: 0;
      background: var(--primary);
      color: var(--primary-text);
      padding: 4px 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
      z-index: 999;
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
    }

    .toolbar label {
      font-size: 13px;
      font-weight: 600;
      color: var(--primary-text);
      margin: 0;
    }

    .toolbar select,
    .toolbar input[type=text] {
      background: #fff;
      border: none;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 13px;
      color: #333;
      min-width: 130px;
    }

    .toolbar .btn-toolbar-act {
      background: rgba(255, 255, 255, .2);
      border: 1px solid rgba(255, 255, 255, .45);
      color: #f5f5f5;
      font-size: 13px;
      padding: 6px 14px;
      border-radius: 6px;
      cursor: pointer;
    }

    .toolbar .btn-toolbar-act:hover {
      color: #f5f5f5;
    }

    .toolbar .btn-export-act {
      background: rgba(255, 255, 255, .15);
      border: 1px solid rgba(255, 255, 255, .4);
      color: #f5f5f5;
      font-size: 13px;
      padding: 6px 14px;
      border-radius: 6px;
      cursor: pointer;
    }

    .toolbar .btn-export-act:hover {
      color: #f5f5f5;
    }

    #recordCount {
      font-size: 13px;
      color: rgba(255, 255, 255, .85);
    }

    .main-content {
      padding: 4px;
    }

    .data-table {
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
      border: 1px solid #e9d8c1;
    }

    .data-table thead th {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 12px 10px;
      font-weight: 700;
      font-size: 13px;
      white-space: nowrap;
      text-transform: none;

    }

    .data-table thead th.sortable {
      cursor: pointer;
      user-select: none;
    }

    .data-table thead th.sortable:hover {
      filter: brightness(1.05);
    }

    .data-table tbody td {
      border: 1px solid #f0e4d3;
      padding: 9px 10px;
      font-size: 13px;
      vertical-align: middle;
      min-height: 42px;
    }

    .data-table tbody tr:hover td {
      background: #fdf7ef;
    }

    .data-table tbody tr:nth-child(even) td {
      background: #fcfaf7;
    }

    .data-table th:first-child,
    .data-table td:first-child {
      width: 48px;
      min-width: 48px;
    }

    .data-table th:nth-child(3),
    .data-table td:nth-child(3),
    .data-table th:nth-child(4),
    .data-table td:nth-child(4) {
      min-width: 220px;
    }

    .row-cb {
      width: 15px;
      height: 15px;
      accent-color: var(--primary);
      cursor: pointer;
    }

    /* .status-badge {
      padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
      display: inline-block;
    }
    .status-active      { background: #d4edda; color: #155724; }
    .status-semi-active { background: #fff3cd; color: #856404; }
    .status-inactive    { background: #f8d7da; color: #721c24; }
    .status-transferred { background: #d1ecf1; color: #0c5460; }
    .status-closed      { background: #f8d7da; color: #721c24; }
    .status-archived    { background: #e2e3e5; color: #383d41; }
    .status-deceased    { background: #e8e8e8; color: #444; }
    .loading, .empty-state { text-align: center; padding: 60px; color: #888; } */
    /* export modal */
    .export-scope-card {
      border: 2px solid #ddd;
      border-radius: 10px;
      padding: 16px;
      cursor: pointer;
      transition: border-color .15s, background .15s;
    }

    .export-scope-card:hover,
    .export-scope-card.selected {
      border-color: var(--primary);
      background: #fff8f0;
    }

    .export-scope-card h6 {
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 4px;
    }

    .select-all-cb {
      accent-color: var(--primary);
      cursor: pointer;
    }
  </style>
</head>

<body>

  <nav class="navbar">
    <div class="d-flex align-items-center">
      <img src="includes/images/mlhud jpeg 6.jpg" alt="Logo" class="navbar-logo me-3">
      <span class="navbar-brand">View Database</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= htmlspecialchars($viewDbHomeUrl) ?>" class="btn btn-outline-light btn-sm">Home</a>
      <button class="btn btn-outline-light btn-sm" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Back
      </button>
    </div>
  </nav>

  <div class="toolbar">
    <div class="d-flex align-items-center gap-2">
      <label>Table:</label>
      <select id="tableSelect" onchange="loadTable()">
        <option value="">Select a table…</option>
        <?php foreach ($availableTables as $key => $name): ?>
          <option value="<?= $key ?>"><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="d-flex align-items-center gap-2">
      <label>Search:</label>
      <input type="text" id="searchInput" placeholder="Search…" oninput="filterTable()">
    </div>
    <div class="d-flex align-items-center gap-2">
      <label>Sort:</label>
      <select id="sortSelect" onchange="sortTable()">
        <option value="date_asc">Default</option>
        <option value="date_desc">Date (Newest)</option>
        <!-- <option value="date_asc">Date (Oldest)</option> -->
        <option value="name_asc">Name (A-Z)</option>
        <option value="name_desc">Name / Title (Z-A)</option>
        <option value="file_number_asc">File Number (A-Z)</option>
        <option value="status_asc">Status (A-Z)</option>
        <option value="status_desc">Status (Z-A)</option>
      </select>
    </div>
    <button class="btn-toolbar-act" onclick="refreshTable()">
      <i class="fas fa-sync"></i> Refresh
    </button>
    <?php if ($canExportViewer): ?>
      <button class="btn-export-act" id="exportBtn" onclick="openExportModal()">
        <i class="fas fa-download"></i> Export
      </button>
    <?php endif; ?>
    <span id="recordCount" class="ms-auto">No table selected</span>
  </div>

  <div class="main-content">
    <div class="container-fluid">
      <div id="tableContainer">
        <div class="empty-state">
          <i class="fas fa-database fa-3x mb-3" style="color:#ddd;"></i>
          <h5>No Table Selected</h5>
          <p>Select a table from the toolbar above to view its data.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- export modal -->
  <?php if ($canExportViewer): ?>
    <div class="modal fade" id="exportModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header" style="background:var(--primary);color:#fff;">
            <h5 class="modal-title"><i class="#"></i>Export</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3">Choose what to export from <strong id="exportTableName">—</strong>:</p>
            <div class="row g-3" id="exportScopeCards">
              <div class="col-md-4">
                <div class="export-scope-card selected" id="scope-all" onclick="selectScope('all')">
                  <h6><i class="#"></i> Download All</h6>
                  <p class="text-muted mb-0" style="font-size:12px;">Download every record in the table.</p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="export-scope-card" id="scope-filtered" onclick="selectScope('filtered')">
                  <h6><i class="f#"></i> Download Filtered / Sorted</h6>
                  <p class="text-muted mb-0" style="font-size:12px;">Download current search / sort results.</p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="export-scope-card" id="scope-selected" onclick="selectScope('selected')">
                  <h6><i class="#"></i> Download Selected Rows</h6>
                  <p class="text-muted mb-0" style="font-size:12px;">Downloads only checked rows.</p>
                </div>
              </div>
            </div>
            <div id="exportSelectionNote" class="mt-3 alert alert-info py-2" style="display:none;font-size:12px;">
              <i class="fas fa-info-circle me-1"></i>
              <span id="exportSelectionNoteText"></span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm" style="background:var(--primary);color:#fff;" onclick="doExport()">
              <i class="fas fa-download me-1"></i> Download
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let currentTable = '';
    let currentData = [];
    let filteredData = [];
    let exportScope = 'all';
    let currentColumns = [];

    const urlParams = new URLSearchParams(window.location.search);
    const tableParam = urlParams.get('table');
    if (tableParam) {
      document.getElementById('tableSelect').value = tableParam;
      loadTable();
    }

    function loadTable() {
      const sel = document.getElementById('tableSelect');
      const t = sel.value;
      if (!t) {
        showEmptyState();
        return;
      }
      currentTable = t;
      fetchTableData(t, '', '', 2000);
    }

    function fetchTableData(tableName, sort, search, limit) {
      showLoading();
      updateRecordCount('Loading…');
      fetch("fetchTable.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            table: tableName,
            sort: sort || '',
            search: search || '',
            limit: limit || 2000
          })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            currentData = data.data;
            currentColumns = data.columns || [];
            filteredData = [...currentData];
            displayTable(filteredData, currentColumns);
            updateRecordCount(data.totalRecords);
          } else {
            showError(data.error || 'Failed to fetch data');
            updateRecordCount('Error');
          }
        })
        .catch(() => {
          showError('Network error');
          updateRecordCount('Error');
        });
    }

    function displayTable(data, columns) {
      const container = document.getElementById('tableContainer');
      if (!data || data.length === 0) {
        container.innerHTML = `<div class="empty-state"><i class="fas fa-inbox fa-2x mb-2" style="color:#ddd;"></i><p>No records found</p></div>`;
        return;
      }

      const tcls = currentTable === 'semicurrent_records' ? 'table-semicurrent' : '';
      let html = `
    <div class="table-responsive">
      <table class="table table-hover data-table ${tcls}" id="mainTable">
        <thead><tr>
          <th><input type="checkbox" class="select-all-cb" id="selectAllCb" onchange="toggleSelectAll(this)" title="Select all"></th>`;

      columns.forEach(col => {
        const display = col.replace(/_/g, '  ').replace(/\b\w/g, l => l.toUpperCase());
        html += `<th class="sortable" onclick="clickSort('${col}')">${display}</th>`;
      });
      html += `</tr></thead><tbody>`;

      data.forEach((row, idx) => {
        const rowJson = JSON.stringify(row).replace(/'/g, "&#39;");
        html += `<tr data-idx="${idx}"><td><input type="checkbox" class="row-cb" value="${idx}"></td>`;
        columns.forEach(col => {
          let val = row[col] ?? '';
          if (col === 'status') {
            const cls = String(val).toLowerCase().replace(/\s+/g, '-');
            val = `<span class="status-badge status-${cls}">${escHtml(String(val))}</span>`;
          } else if (col.includes('date') && val) {
            const d = new Date(val);
            if (!isNaN(d.getTime())) val = d.toLocaleDateString('en-GB');
            else val = escHtml(String(val));
          } else {
            val = escHtml(String(val));
          }
          html += `<td data-col="${col}">${val}</td>`;
        });
        html += `</tr>`;
      });

      html += `</tbody></table></div>`;
      container.innerHTML = html;
    }

    function clickSort(col) {
      const sel = document.getElementById('sortSelect');
      const map = {
        full_name: 'name_asc',
        name_of_officer: 'name_asc',
        file_title: 'name_asc',
        current_file_title: 'name_asc',
        previous_file_title: 'name_asc',
        status: 'status_asc',
        timestamp: 'date_desc',
        file_number: 'file_number_asc',
        file_ref_number: 'file_number_asc',
        file_reference_number: 'file_number_asc',
        current_index: 'file_number_asc'
      };
      if (map[col]) {
        sel.value = map[col];
        sortTable();
      }
    }

    function filterTable() {
      const search = document.getElementById('searchInput').value.toLowerCase();
      filteredData = currentData.filter(row =>
        !search || Object.values(row).some(v => String(v).toLowerCase().includes(search))
      );
      displayTable(filteredData, currentColumns);
      updateRecordCount(filteredData.length);
    }

    function sortTable() {
      const v = document.getElementById('sortSelect').value;
      if (!v) {
        filteredData = [...currentData];
        displayTable(filteredData, currentColumns);
        return;
      }
      // Re-fetch with server-side sort for accuracy
      const search = document.getElementById('searchInput').value;
      fetchTableData(currentTable, v, search, 2000);
    }

    function refreshTable() {
      if (currentTable) loadTable();
    }

    function toggleSelectAll(cb) {
      document.querySelectorAll('.row-cb').forEach(c => c.checked = cb.checked);
    }

    function getSelectedRows() {
      const checked = Array.from(document.querySelectorAll('.row-cb:checked'));
      return checked.map(cb => filteredData[parseInt(cb.value)]).filter(Boolean);
    }

    function openExportModal() {
      if (!currentTable) {
        alert('Please select a table first.');
        return;
      }
      document.getElementById('exportTableName').textContent =
        document.getElementById('tableSelect').options[document.getElementById('tableSelect').selectedIndex].text;
      selectScope('all');
      const modal = new bootstrap.Modal(document.getElementById('exportModal'));
      modal.show();
    }

    function selectScope(scope) {
      exportScope = scope;
      ['all', 'filtered', 'selected'].forEach(s => {
        document.getElementById('scope-' + s).classList.toggle('selected', s === scope);
      });
      const note = document.getElementById('exportSelectionNote');
      const txt = document.getElementById('exportSelectionNoteText');
      if (scope === 'selected') {
        const cnt = document.querySelectorAll('.row-cb:checked').length;
        txt.textContent = `${cnt} row(s) currently checked. Check rows in the table before exporting.`;
        note.style.display = '';
      } else if (scope === 'filtered') {
        txt.textContent = `${filteredData.length} record(s) in the current filtered/sorted view will be exported.`;
        note.style.display = '';
      } else {
        note.style.display = 'none';
      }
    }

    function doExport() {
      let dataToExport = [];
      if (exportScope === 'all') {
        dataToExport = currentData;
      } else if (exportScope === 'filtered') {
        dataToExport = filteredData;
      } else {
        dataToExport = getSelectedRows();
        if (dataToExport.length === 0) {
          alert('No rows selected. Check rows in the table first.');
          return;
        }
      }

      if (dataToExport.length === 0) {
        alert('No data to export.');
        return;
      }

      // build csv in browser
      const cols = currentColumns;
      const bom = '\uFEFF';
      const header = cols.map(c => '"' + c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '"').join(',');
      const rows = dataToExport.map(row =>
        cols.map(c => '"' + String(row[c] ?? '').replace(/"/g, '""') + '"').join(',')
      );
      const csv = bom + header + '\n' + rows.join('\n');
      const blob = new Blob([csv], {
        type: 'text/csv;charset=utf-8;'
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = currentTable + '_' + exportScope + '_' + new Date().toISOString().slice(0, 10) + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
    }

    function updateRecordCount(count) {
      const el = document.getElementById('recordCount');
      el.textContent = typeof count === 'number' ? count + ' records' : count;
    }

    function showEmptyState() {
      document.getElementById('tableContainer').innerHTML = `
    <div class="empty-state"><i class="fas fa-database fa-3x mb-3" style="color:#ddd;"></i>
    <h5>No Table Selected</h5><p>Select a table from the toolbar above.</p></div>`;
      updateRecordCount('No table selected');
    }

    function showLoading() {
      document.getElementById('tableContainer').innerHTML = `
    <div class="loading"><div class="spinner-border text-warning" role="status"></div>
    <p class="mt-2">Loading…</p></div>`;
    }

    function showError(msg) {
      document.getElementById('tableContainer').innerHTML = `
    <div class="alert alert-danger m-3"><i class="fas fa-exclamation-triangle me-1"></i>${msg}</div>`;
    }

    function escHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
  </script>
</body>

</html>