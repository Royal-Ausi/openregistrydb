<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION["username"])) {
  header("Location: userlogin.php");
  exit();
}

// Define available tables manually or fetch from DB
$availableTables = [
  "master_register",
  "pensioners_register",
  "incoming_mail",
  "contractstaff_register",
  "semicurrent_records",
  "file_diary"
];
?>

<!DOCTYPE html>
<html>
<head>
  <title>View Database</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .table-container {
      margin-top: 20px;
    }
    .filter-bar {
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
<div class="container mt-5">
  <h4>📄 View Database</h4>

  <!-- Modal Trigger -->
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tableModal">Select Table to View</button>

  <!-- Modal -->
  <div class="modal fade" id="tableModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="tableForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Choose a Table</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <select name="selected_table" id="selectedTable" class="form-control" required>
            <?php foreach ($availableTables as $table): ?>
              <option value="<?= $table ?>"><?= ucwords(str_replace("_", " ", $table)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">View</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-bar mt-4" id="filterBar" style="display:none;">
    <input type="text" id="searchInput" class="form-control mb-2" placeholder="Search...">
    <select id="sortSelect" class="form-select">
      <option value="">Sort By</option>
      <option value="timestamp">Date Added</option>
      <option value="file_number">File Number</option>
      <option value="status">Status</option>
      <option value="full_name">Alphabetical Name</option>
    </select>
  </div>

  <!-- Table Container -->
  <div class="table-container" id="tableContainer"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById("tableForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const tableName = document.getElementById("selectedTable").value;

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById("tableModal"));
    modal.hide();

    // Show filters
    document.getElementById("filterBar").style.display = "block";

    // Fetch table data
    fetchTableData(tableName);
  });

  function fetchTableData(tableName, sortBy = "", searchTerm = "") {
    fetch("fetch_table.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ table: tableName, sort: sortBy, search: searchTerm })
    })
    .then(res => res.text())
    .then(html => {
      document.getElementById("tableContainer").innerHTML = html;
    });
  }

  document.getElementById("sortSelect").addEventListener("change", function() {
    const sortBy = this.value;
    const tableName = document.getElementById("selectedTable").value;
    const searchTerm = document.getElementById("searchInput").value;
    fetchTableData(tableName, sortBy, searchTerm);
  });

  document.getElementById("searchInput").addEventListener("input", function() {
    const searchTerm = this.value;
    const sortBy = document.getElementById("sortSelect").value;
    const tableName = document.getElementById("selectedTable").value;
    fetchTableData(tableName, sortBy, searchTerm);
  });
</script>
</body>
</html>