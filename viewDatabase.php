<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION["username"])) {
  header("Location: userlogin.php");
  exit();
}

// Define available tables with display names
$availableTables = [
  "master_register" => "Master Register",
  "pensioners_register" => "Pensioners Register", 
  "incoming_mail" => "Incoming Mail",
  "contractstaff_register" => "Contract Staff Register",
  "semicurrent_records" => "Semi-Current Records",
  "file_diary" => "File Diary"
];
?>

<!DOCTYPE html>
<html>
<head>
  <title>View Database - Open Registry DB</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body { 
      background-color: #F7E5D9; 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .container {
      max-width: 1200px;
    }
    .table-container {
      margin-top: 20px;
    }
    .filter-bar {
      margin-bottom: 15px;
      background: white;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .btn-primary {
      background-color: #b57526ff !important;
      border-color: #b57526ff !important;
    }
    .btn-primary:hover {
      background-color: #8b5a1f !important;
      border-color: #8b5a1f !important;
    }
    .table-dark {
      background-color: #b57526ff !important;
    }
    .card {
      border: none;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .modal-content {
      border-radius: 12px;
    }
    .form-control:focus, .form-select:focus {
      border-color: #b57526ff;
      box-shadow: 0 0 0 0.2rem #b5752622;
    }
    .loading {
      text-align: center;
      padding: 40px;
      color: #666;
    }
    .stats-card {
      background: linear-gradient(135deg, #b57526ff, #8b5a1f);
      color: white;
    }
  </style>
</head>
<body>
<div class="container mt-4">
  <div class="row mb-4">
    <div class="col-md-8">
      <h2><i class="fas fa-database"></i> Database Viewer</h2>
      <p class="text-muted">Select a table to view and search through records</p>
    </div>
    <div class="col-md-4 text-end">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tableModal">
        <i class="fas fa-table"></i> Select Table
      </button>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="row mb-4" id="statsCards" style="display:none;">
    <div class="col-md-3">
      <div class="card stats-card">
        <div class="card-body text-center">
          <h5><i class="fas fa-list"></i></h5>
          <h6>Total Records</h6>
          <h4 id="totalRecords">0</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stats-card">
        <div class="card-body text-center">
          <h5><i class="fas fa-search"></i></h5>
          <h6>Search Results</h6>
          <h4 id="searchResults">0</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stats-card">
        <div class="card-body text-center">
          <h5><i class="fas fa-clock"></i></h5>
          <h6>Last Updated</h6>
          <h6 id="lastUpdated">-</h6>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stats-card">
        <div class="card-body text-center">
          <h5><i class="fas fa-user"></i></h5>
          <h6>Current User</h6>
          <h6><?= htmlspecialchars($_SESSION["username"]) ?></h6>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="tableModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="tableForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-table"></i> Choose a Table</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label for="selectedTable" class="form-label">Select table to view:</label>
          <select name="selected_table" id="selectedTable" class="form-select" required>
            <option value="">Select Table</option>
            <?php foreach ($availableTables as $table => $displayName): ?>
              <option value="<?= $table ?>"><?= $displayName ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-eye"></i> View Table
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-bar" id="filterBar" style="display:none;">
    <div class="row">
      <div class="col-md-4">
        <label for="searchInput" class="form-label"><i class="fas fa-search"></i> Search</label>
        <input type="text" id="searchInput" class="form-control" placeholder="Search records...">
      </div>
      <div class="col-md-3">
        <label for="sortSelect" class="form-label"><i class="fas fa-sort"></i> Sort By</label>
        <select id="sortSelect" class="form-select">
          <option value="">Default Order</option>
          <option value="timestamp">Date Added</option>
          <option value="file_number">File Number</option>
          <option value="full_name">Name (A-Z)</option>
          <option value="status">Status</option>
          <option value="box_no">Box Number</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="limitSelect" class="form-label"><i class="fas fa-list-ol"></i> Records Per Page</label>
        <select id="limitSelect" class="form-select">
          <option value="50">50 records</option>
          <option value="100">100 records</option>
          <option value="200">200 records</option>
          <option value="500">500 records</option>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button id="refreshBtn" class="btn btn-outline-primary w-100">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
      </div>
    </div>
  </div>

  <!-- Table Container -->
  <div class="table-container" id="tableContainer">
    <div class="text-center text-muted">
      <i class="fas fa-database fa-3x mb-3"></i>
      <p>Select a table to view records</p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let currentTable = '';
  let isLoading = false;

  document.getElementById("tableForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const tableName = document.getElementById("selectedTable").value;
    
    if (!tableName) {
      alert('Please select a table');
      return;
    }

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById("tableModal"));
    modal.hide();

    // Show filters and stats
    document.getElementById("filterBar").style.display = "block";
    document.getElementById("statsCards").style.display = "block";

    // Set current table and fetch data
    currentTable = tableName;
    fetchTableData(tableName);
  });

  function fetchTableData(tableName, sortBy = "", searchTerm = "", limit = 50) {
    if (isLoading) return;
    
    isLoading = true;
    showLoading();

    fetch("fetchTable.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ 
        table: tableName, 
        sort: sortBy, 
        search: searchTerm,
        limit: limit 
      })
    })
    .then(res => res.text())
    .then(html => {
      document.getElementById("tableContainer").innerHTML = html;
      updateStats();
      updateLastUpdated();
    })
    .catch(err => {
      document.getElementById("tableContainer").innerHTML = 
        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error loading data</div>';
    })
    .finally(() => {
      isLoading = false;
    });
  }

  function showLoading() {
    document.getElementById("tableContainer").innerHTML = 
      '<div class="loading"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading data...</div>';
  }

  function updateStats() {
    const table = document.querySelector('.table tbody');
    if (table) {
      const totalRows = table.querySelectorAll('tr').length;
      document.getElementById("totalRecords").textContent = totalRows;
      document.getElementById("searchResults").textContent = totalRows;
    }
  }

  function updateLastUpdated() {
    const now = new Date();
    document.getElementById("lastUpdated").textContent = now.toLocaleTimeString();
  }

  // Event listeners for filters
  document.getElementById("sortSelect").addEventListener("change", function() {
    const sortBy = this.value;
    const searchTerm = document.getElementById("searchInput").value;
    const limit = document.getElementById("limitSelect").value;
    fetchTableData(currentTable, sortBy, searchTerm, limit);
  });

  document.getElementById("searchInput").addEventListener("input", debounce(function() {
    const searchTerm = this.value;
    const sortBy = document.getElementById("sortSelect").value;
    const limit = document.getElementById("limitSelect").value;
    fetchTableData(currentTable, sortBy, searchTerm, limit);
  }, 300));

  document.getElementById("limitSelect").addEventListener("change", function() {
    const limit = this.value;
    const searchTerm = document.getElementById("searchInput").value;
    const sortBy = document.getElementById("sortSelect").value;
    fetchTableData(currentTable, sortBy, searchTerm, limit);
  });

  document.getElementById("refreshBtn").addEventListener("click", function() {
    const searchTerm = document.getElementById("searchInput").value;
    const sortBy = document.getElementById("sortSelect").value;
    const limit = document.getElementById("limitSelect").value;
    fetchTableData(currentTable, sortBy, searchTerm, limit);
  });

  // Debounce function for search input
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'r') {
      e.preventDefault();
      document.getElementById("refreshBtn").click();
    }
    if (e.ctrlKey && e.key === 'f') {
      e.preventDefault();
      document.getElementById("searchInput").focus();
    }
  });
</script>
</body>
</html>