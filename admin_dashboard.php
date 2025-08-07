<!-- dashboardAdmin.php -->
<?php
include 'includes/db.php';

// $result2 = $conn->query("SELECT created_by, timestamp FROM master_register ORDER BY timestamp DESC LIMIT 1");
// $row2 = $result2->fetch_assoc();
// if ($row2) {
//     $lastEditor = $row2["created_by"];
//     $lastEditTime = $row2["timestamp"];
// } else {
//     $lastEditor = "N/A";
//     $lastEditTime = "N/A";
// }

// Total entries in master_register
$result = $conn-> query("SELECT COUNT(*) AS total_entries FROM master_register");
$row = $result->fetch_assoc();
$totalMaster = $row["total_entries"];


// Last edit info
$result2 = $conn->query("SELECT created_by, timestamp FROM master_register ORDER BY timestamp DESC LIMIT 1");
$row2 = $result2->fetch_assoc();
if ($row2) {
    $lastEditor = $row2["created_by"];
    $lastEditTime = $row2["timestamp"];
} else {
    $lastEditor = "N/A";
    $lastEditTime = "N/A";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
    }
    .navbar {
      background-color: #b57526ff;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000
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
      margin-left: 0;
      padding: 20px;
      margin-top: 56px;
       transition: margin-left 0.3s ease;
    }
    .sidebar.active ~ .main-content {
    margin-left: 220px; /* Add margin when sidebar is visible */
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

   <!-- Top Navbar -->
  <nav class="navbar">
    <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
    <span class="navbar-brand mx-auto text-white">Open Registry Admin</span>
  </nav>

  <!-- Sidebar Drawer -->
  <div class="sidebar" id="sidebar">
    <a href="#">Dashboard</a>
    <a href="create_user.php">Create User</a>
    <a href="#">Edit Database</a>
    <a href="#">View Database</a>
    <a href="#">Manage Templates</a>
    <a href="#">Settings</a>
    <a href="#">Logout</a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <h3>Welcome, Admin</h3>
    <p>Here are the recent activities by users:</p>

    <!-- Recent Edits Widget -->
    <div class="widget">
      <p><strong>Total Entries: </strong> <?= $totalMaster ?></p>
      <h5>Recent Edits</h5>
      <ul class="activity-list">
        <li>User A edited Visitor Log on 2025-07-21</li>
        <li>User B added entry to Meeting Minutes on 2025-07-20</li>
        <li>User C updated Financial Records on 2025-07-19</li>
      </ul>
    </div>

    <!-- Recent Views Widget -->
    <div class="widget">
      <h5>Recent Views</h5>
      <ul class="activity-list">
        <li>User D viewed Visitor Log on 2025-07-21</li>
        <li>User A viewed Meeting Minutes on 2025-07-20</li>
        <li>User B viewed Financial Records on 2025-07-19</li>
      </ul>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('active');
    }
  </script>

</body>
</html>