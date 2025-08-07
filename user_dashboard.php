<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION["username"])) {
    header("Location: userlogin.php");
    exit();
}

$username = $_SESSION["username"];
$module = $_SESSION["module"];

// Example fetch (replace later with actual query)
$totalEdits = 17; // Replace with real count via SQL
?>

<!DOCTYPE html>
<html lang="en"> 
<head>
  <meta charset="UTF-8">
  <title>User Dashboard - Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      background-color: #f4f4f4;
      font-family: Arial, sans-serif;
    }

    .navbar {
      background-color: #b57526ff;
      color: white;
      height: 56px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
    }

    .navbar .menu-toggle {
      position: absolute;
      left: 20px;
      font-size: 24px;
      cursor: pointer;
      color: white;
    }

    .sidebar {
      height: 100vh;
      width: 220px;
      position: fixed;
      top: 56px;
      left: -220px;
      background-color: #f8f9fa;
      padding-top: 20px;
      border-right: 1px solid #ddd;
      transition: left 0.3s ease;
      z-index: 999;
    }

    .sidebar.active {
      left: 0;
    }

    .sidebar a {
      display: block;
      padding: 10px 20px;
      color: #333;
      text-decoration: none;
    }

    .sidebar a:hover {
      background-color: #e2e6ea;
    }

    .main-content {
      margin-top: 70px;
      padding: 20px;
    }

    .widget {
      background-color: white;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 15px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .widget h5 {
      margin-bottom: 10px;
      font-weight: bold;
    }

    .activity-list {
      list-style: none;
      padding-left: 0;
    }

    .activity-list li {
      padding: 5px 0;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar">
    <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
    <span class="navbar-brand mx-auto text-white">Open Registry User</span>
  </nav>

  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <a href="#">Dashboard</a>
    <a href="editRedirect.php">Edit Database (<?= $module ?>)</a>
    <a href="modules/masterRegister_edit.php" class="btn btn-outline-primary"><strong>Edit Register</strong></a>
    <!-- <a href="viewDatabase.php">View Database </a> -->
    <a href="#" id="viewDatabaseLink" class="nav-link">📄 View Database</a>
    
      <!-- <li class="nav-item">
    <a class="nav-link" href="viewDatatabase.php">
      📄 View Database
    </a> -->

    </li>
    <a href="#">View Database - Meeting Minutes</a>
    <a href="#">View Database - Financial Records</a>
    <a href="#">Logout</a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <h3>Welcome, <?= htmlspecialchars($username) ?></h3>
    <p>Here are your recent activities:</p>

    <!-- Recent Edits Widget -->
    <div class="widget">
      <h5>Your Recent Edits to <strong><?= $module ?></strong></h5>
      <ul class="activity-list">
        <li>Entry updated on 2025-07-21</li>
        <li>New record added on 2025-07-20</li>
        <li>Edited document on 2025-07-19</li>
      </ul>
    </div>

    <!-- Total Entries Widget -->
    <div class="widget">
      <h5>Total Entries You've Made to <strong><?= $module ?></strong></h5>
      <p><strong><?= $totalEdits ?></strong> entries</p>
    </div>
    <div id="tableContainer"></div>
  </div>

  <div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="viewForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Select Register to View</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <select id="selectedTable" class="form-select" required>
            <option value="master_register">Master Register</option>
            <option value="pensioners_register">Pensioners Register</option>
            <option value="incoming_mail">Incoming Mail</option>
            <option value="contractstaff_register">Contract Staff Register</option>
            <option value="semicurrent_records">Semi-Current Records</option>
            <option value="file_diary">File Diary</option>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">View Register</button>
        </div>
      </form>
    </div>
  </div>

    <!-- Script -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script>
    // Blur effect
    const body = document.querySelector("body");
    const modal = new bootstrap.Modal(document.getElementById("viewModal"));

    // Trigger modal from sidebar
    document.getElementById("viewDatabaseLink").addEventListener("click", function(e) {
      e.preventDefault();
      body.classList.add("blurred");
      modal.show();
    });

    // Remove blur when modal closes
    document.getElementById("viewModal").addEventListener("hidden.bs.modal", function () {
      body.classList.remove("blurred");
    });

    // Handle form submission
    document.getElementById("viewForm").addEventListener("submit", function(e) {
      e.preventDefault();
      const tableName = document.getElementById("selectedTable").value;
      modal.hide();

      fetch("fetch_table.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ table: tableName })
      })
      .then(res => res.text())
      .then(html => {
        document.getElementById("tableContainer").innerHTML = html;
      });
    });
    </script> -->

    <style>
      .main-content.blurred,
      .sidebar.blurred {
        filter: blur(3px);
        transition: filter 0.3s ease;
      }
    </style>
    <script>
    const mainContent = document.querySelector(".main-content");
    const sidebar = document.querySelector(".sidebar");
    const modal = new bootstrap.Modal(document.getElementById("viewModal"));

    document.getElementById("viewDatabaseLink").addEventListener("click", function(e) {
      e.preventDefault();
      mainContent.classList.add("blurred");
      sidebar.classList.add("blurred");
      modal.show();
    });

    document.getElementById("viewModal").addEventListener("hidden.bs.modal", function () {
      mainContent.classList.remove("blurred");
      sidebar.classList.remove("blurred");
    });

    document.getElementById("viewForm").addEventListener("submit", function(e) {
      e.preventDefault();
      const tableName = document.getElementById("selectedTable").value;
      modal.hide();

      fetch("fetch_table.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ table: tableName })
      })
      .then(res => res.text())
      .then(html => {
        document.getElementById("tableContainer").innerHTML = html;
      });
    });

    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('active');
    }
    </script>

</body>
</html>