<?php
  session_start();
  include '../includes/db.php';
  
  // if (!isset($_SESSION["username"])) {
  //     header("Location: ../user_login.php");
  //     exit();
  // } 

  // $username = $_SESSION["username"];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Master Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="../includes/autosave.js"></script>
<script>
  initAutosave({
    tableName: "master_register",
    triggerColumnIndex: 4 // index of 'status' column
  });
</script>
  <style>
    body {
      background-color: #f4f4f4;
      font-family: Arial, sans-serif;
    }
    .spreadsheet-table input {
      border: none;
      background-color: transparent;
      width: 100%;
      padding: 4px;
    }
    .spreadsheet-table tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    .spreadsheet-table td {
      border: 1px solid #ddd;
      padding: 0;
    }
    .warning {
      color: red;
      font-size: 14px;
      margin-top: 10px;
      
    }
    .row-saved {
        background-color: #d4edda !important;
        transition: background-color 0.5s ease;
      }
      .row-saved {
  background-color: #d4edda !important;
  transition: background-color 0.5s ease;
}
.card.text-white.bg-success {
  background-color: #b57526ff !important;
  color: #fff !important;
  border-radius: 8px;
  box-shadow: 0 2px 6px #5f5f5fff;
}
.spreadsheet-table th {
  background-color: #b57526ff;
  color: #fff;
}
.spreadsheet-table td {
  background-color: #fff;
  color: #262223;
}
.spreadsheet-table tr:nth-child(even) td {
  background-color: #f4e8dd;
}
  </style>
</head>
<body>
  <div>
    <?php include 'includes/navbar.php'; ?>
  </div>
<div class="row mb-3">
  <div class="col-md-4">
    <div class="card text-white bg-success">
      <div class="card-body">
        <h6 class="card-title">Saved Rows</h6>
        <p class="card-text fs-4" id="savedCount">0</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <label for="createdBy">Created By</label>
    <input type="text" id="createdBy" class="form-control" value="<?= $_SESSION['username'] ?>" readonly>
  </div>
  <div class="col-md-4 d-flex align-items-end">
    <button id="saveButton" class="btn btn-primary w-100">💾 Save (Ctrl + S)</button>
  </div>
</div>

<div class="container mt-5" id="tableContainer">
  <h4>Edit Master Register</h4>

  <!-- Trigger Modal -->
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rowModal">Start Editing</button>

  <!-- Modal -->
  <div class="modal fade" id="rowModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="rowForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Enter Row Count</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label for="rowCount">How many rows do you want to enter?</label>
          <input type="number" id="rowCount" class="form-control" min="1" required>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="continueRows">
            <label class="form-check-label" for="continueRows">Continue from previous unfinished rows</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Create Table</button>
        </div>
      </form>
    </div>
  </div>

 

  <!-- Table Container -->
  <div class="table-container mt-4" id="tableContainer"></div>
  <div class="warning" id="warningMessage"></div>
</div>

 <div class="mb-3">
    <label for="createdBy"><strong>Created By (Username)</strong></label>
    <input type="text" id="createdBy" class="form-control" placeholder="Enter your username" required>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById("rowForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const rowCount = parseInt(document.getElementById("rowCount").value);
    const continueRows = document.getElementById("continueRows").checked;

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById("rowModal"));
    modal.hide();

    // Generate table
    generateTable(rowCount);
  });

  function generateTable(rows) {
    const container = document.getElementById("tableContainer");
    container.innerHTML = "";

    const table = document.createElement("table");
    table.className = "table spreadsheet-table";

    const thead = document.createElement("thead");
    thead.innerHTML = `
      <tr>
        
        <th>File Number</th>
        <th>Full Name</th>
        <th>Designation</th>
        <th>Date Opened</th>
        <th>Date Closed</th>
        <th>Status</th>
      </tr>
    `;
     table.appendChild(thead);

  const tbody = document.createElement("tbody");

  for (let i = 0; i < rows; i++) {
    const row = document.createElement("tr");

    for (let col = 0; col < 6; col++) {
      const cell = document.createElement("td");
      let input;

      if (col === 3 || col === 4) {
        // Date Opened or Date Closed
        input = document.createElement("input");
        input.type = "date";
      } else if (col === 5) {
        // Status dropdown
        input = document.createElement("select");
        input.required = true;
        ["Active", "Semi-active", "Inactive", "Transferred"].forEach(opt => {
          const option = document.createElement("option");
          option.value = opt;
          option.textContent = opt;
          input.appendChild(option);
        });
        input.insertAdjacentHTML('afterbegin', '<option value="" disabled selected>Select status</option>');
      } else {
        // Text fields
  input = document.createElement("input");
        input.type = "text";
        if (col === 0) input.required = true; // File Number required
      }

      input.dataset.row = i;
      input.dataset.col = col;
      input.className = "form-control";
      input.addEventListener("blur", () => checkRowCompletion(i));
      cell.appendChild(input);
      row.appendChild(cell);
    }

    tbody.appendChild(row);
  }

  table.appendChild(tbody);
  container.appendChild(table);
}

function checkRowCompletion(rowIndex) {
  const inputs = document.querySelectorAll(`input[data-row='${rowIndex}'], select[data-row='${rowIndex}']`);
  let allFilled = true;
  let rowData = [];

  inputs.forEach((input, idx) => {
    // File Number and Status are required
    if ((idx === 0 || idx === 5) && (input.value.trim() === "" || input.value === "")) {
      allFilled = false;
    }
    rowData.push(input.value.trim());
  });

  if (allFilled) {
    document.getElementById("warningMessage").textContent = "";
    saveRow(rowData);
  } else {
    document.getElementById("warningMessage").textContent = "⚠️ Please fill all required fields (File Number and Status).";
  }
}

</script>

</body>
</html>