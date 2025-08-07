<?php
session_start();
include '../includes/db.php';

$result = $conn->query("SELECT * FROM master_register ORDER BY timestamp DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>View Master Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h4>View Master Register</h4>
    <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search by File Number..." onkeyup="searchTable()">
    <table class="table table-bordered" id="recordsTable">
      <thead>
        <tr>
          <th>File Number</th>
          <th>Full Name</th>
          <th>Designation</th>
          <th>Date Opened</th>
          <th>Status</th>
          <th>Created By</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row["file_number"] ?></td>
          <td><?= $row["full_name"] ?></td>
          <td><?= $row["designation"] ?></td>
          <td><?= $row["date_opened"] ?></td>
          <td><?= $row["status"] ?></td>
          <td><?= $row["created_by"] ?></td>
          <td><?= $row["timestamp"] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <script>
    function searchTable() {
      const input = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("#recordsTable tbody tr");
      rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? "" : "none";
      });
    }
  </script>
</body>
</html>