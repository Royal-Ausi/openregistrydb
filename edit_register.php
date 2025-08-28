<?php
session_start();
include 'includes/db.php';

$table = $_GET["table"] ?? "";
$rows = intval($_GET["rows"] ?? 0);
$username = $_SESSION["username"] ?? '';

$allowedTables = [
  "master_register" => ["file_number", "full_name", "designation", "date_opened", "date_closed", "status"],
  "incoming_mail" => ["mail_no", "subject", "sender", "date_received", "action_taken"],
  "contractstaff_register" => ["file_number", "name_of_officer", "title", "status", "created_by"],
  "pensioners_register" => ["file_number", "full_name", "designation", "date_opened", "date_closed", "created_by"],
  "semicurrent_records" => ["	file_reference_number", "file_title", "date_opened", "date_closed", "retention_period",
                              "action_category", "action_date", "location_no", "temp_box_no", "box_no", "created_by"],
 "file_diary" => ["file_ref_number", "file_title", "date_opened", "security_grading"]                           
 
];


if (!array_key_exists($table, $allowedTables) || $rows < 1) {
  echo "Invalid table or row count.";
  exit();
}
$columns = $allowedTables[$table];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit <?= ucwords(str_replace("_", " ", $table)) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
body { background-color: #F7E5D9; }
    .card.bg-success {
      background-color: #b57526ff !important;
      color: #fff !important;
    }
    .btn-primary, .btn-primary:focus {
      background-color: #b57526ff !important;
      border-color: #b57526ff !important;
    }
    .table thead th {
      background-color: #b57526ff;
      color: #fff;
    }
    .form-control:focus {
      border-color: #b57526ff;
      box-shadow: 0 0 0 0.2rem #b5752622;
    }
  </style>
</head>
<body>
<div class="container mt-5" style="max-width:1100px;">
  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card bg-success shadow">
        <div class="card-body">
          <h6 class="card-title">Saved Rows</h6>
          <p class="card-text fs-4" id="savedCount">0</p>
          <div id="saveMessage"></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <label for="createdBy" class="form-label mt-2"><strong>Created By</strong></label>
      <input type="text" id="createdBy" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
    </div>
    <div class="col-md-4 d-flex align-items-end">
      <button id="saveButton" class="btn btn-primary w-100 py-2">Save (Ctrl + S)</button>
    </div>
  </div>
  <!-- <div id="saveMessage"></div> -->
  <table class="table table-bordered shadow-sm">
    <thead>
      <tr>
        <?php foreach ($columns as $col): ?>
          <th><?= ucwords(str_replace("_", " ", $col)) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php for ($i = 0; $i < $rows; $i++): ?>
        <tr>
          <?php foreach ($columns as $index => $col): ?>
            <td>
              <input type="<?= strpos($col, 'date') !== false ? 'date' : 'text' ?>"
                     class="form-control"
                     data-row="<?= $i ?>"
                     data-col="<?= $index ?>">
            </td>
          <?php endforeach; ?>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>
<script>
  document.getElementById("saveButton").addEventListener("click", saveAllRows);
  document.addEventListener("keydown", function(e) {
    if (e.ctrlKey && e.key === "s") {
      e.preventDefault();
      saveAllRows();
    }
  });

  function saveAllRows() {
    const createdBy = document.getElementById("createdBy").value;
    const rows = [];
    const rowCount = document.querySelectorAll("input[data-row]").length / <?= count($columns) ?>;
    for (let i = 0; i < rowCount; i++) {
      const inputs = document.querySelectorAll(`input[data-row='${i}']`);
      const rowData = Array.from(inputs).map(input => input.value.trim());
      if (rowData.some(val => val === "")) {
        document.getElementById("saveMessage").innerHTML = `<div class="alert alert-danger">Row ${i + 1} has empty fields.</div>`;
        return;
      }
      rows.push(rowData);
    }
    fetch("bulkSave.php", {
      method: "POST",
  headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        table: "<?= $table ?>",
        rows: rows,
        created_by: createdBy
      })
    })
    .then(res => res.json())
    .then(response => {
    console.log("Save response:", response); // <-- Add this line
    if (response.success) {
    updateSavedCount();
    document.getElementById("saveMessage").innerHTML = `<div class="alert alert-success"> ${response.saved} rows saved.</div>`;
  } else {
    document.getElementById("saveMessage").innerHTML = `<div class="alert alert-danger"> Save failed: ${response.errors ? response.errors.join(", ") : "Unknown error"}</div>`;
  }
})
    .catch(err => {
      document.getElementById("saveMessage").innerHTML = `<div class="alert alert-danger"> Save failed.</div>`;
    });
  }

  function updateSavedCount() {
    fetch("countSaved.php?table=<?= $table ?>&created_by=<?= $username ?>")
      .then(res => res.json())
      .then(data => {
        document.getElementById("savedCount").textContent = data.count;
      });
  }
  window.onload = updateSavedCount;
</script>
</body>
</html>