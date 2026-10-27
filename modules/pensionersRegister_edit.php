<?php
session_start();
include '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../userlogin.php");
    exit();
}

$username = $_SESSION["username"];
$user_type = $_SESSION["user_type"] ?? "user";
require_once __DIR__ . '/../includes/auth.php';
$registryDashboardUrl = openregistry_dashboard_href();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Pensioners Register | Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #FCF1E3;
      font-family: Arial, sans-serif;
      margin-top: 80px;
    }
    
    /* Excel-like Table Styling with Horizontal Scroll */
    .table-wrapper {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 70vh;
      border: 2px solid #E08D3C;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .excel-table {
      border-collapse: collapse;
      width: 100%;
      min-width: 900px;
      background-color: white;
      table-layout: fixed;
    }
    
    .excel-table th {
      background-color: #E08D3C;
      color: white;
      padding: 12px 8px;
      text-align: center;
      font-weight: bold;
      border: 1px solid #ddd;
      position: sticky;
      top: 0;
      z-index: 10;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .excel-table td {
      border: 1px solid #ddd;
      padding: 0;
      background-color: white;
      vertical-align: middle;
    }
    
    .excel-table tr:nth-child(even) td {
      background-color: #f9f9f9;
    }
    
    .excel-table input, .excel-table select {
      border: none;
      background-color: transparent;
      width: 100%;
      padding: 10px;
      font-size: 14px;
      outline: none;
      min-height: 40px;
      box-sizing: border-box;
    }
    
    .excel-table input:focus, .excel-table select:focus {
      background-color: #fff3cd;
      border: 2px solid #E08D3C;
      box-shadow: inset 0 0 5px rgba(232, 73, 42, 0.3);
    }
    
    .excel-table select {
      cursor: pointer;
    }
    .excel-table input[type="text"] {
      resize: both;
      min-width: 100px;
      transition: all 0.2s ease;
    }
    
    .excel-table input[type="text"]:focus {
      min-width: 200px;
      resize: both;
    }
    
    .excel-table td {
      position: relative;
      overflow: visible;
    }
    
    .excel-table td:hover {
      background-color: #f8f9fa !important;
    }
    
    .cell-resize-handle {
      position: absolute;
      bottom: 0;
      right: 0;
      width: 8px;
      height: 8px;
      background: #E08D3C;
      cursor: se-resize;
      opacity: 0;
      transition: opacity 0.2s ease;
    }
    
    .excel-table td:hover .cell-resize-handle {
      opacity: 1;
    }
    
    /* Column Width Definitions */
    .col-file-number { width: 120px; min-width: 120px; }
    .col-full-name { width: 200px; min-width: 200px; }
    .col-designation { width: 180px; min-width: 180px; }
    .col-date-opened { width: 140px; min-width: 140px; }
    .col-date-closed { width: 140px; min-width: 140px; }
    .col-status { width: 120px; min-width: 120px; }
    
    
    /* Excel-like Table Wrapper with Horizontal Scroll */
    .table-wrapper {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 70vh;
      border: 2px solid #E08D3C;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    /* Scrollbar Styling */
    .table-wrapper::-webkit-scrollbar {
      height: 12px;
      width: 12px;
    }
    
    .table-wrapper::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb {
      background: #E08D3C;
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb:hover {
      background: #c0392b;
    }
    
    .table-wrapper::-webkit-scrollbar-corner {
      background: #f1f1f1;
    }

    /* Enhanced Toolbar */
    .toolbar {
      background-color: #E08D3C;
      color: white;
      padding: 15px;
      margin: -20px -20px 20px -20px;
      border-radius: 8px 8px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .toolbar-btn {
      background-color: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      padding: 8px 15px;
      border-radius: 5px;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    
    .toolbar-btn:hover {
      background-color: rgba(255,255,255,0.3);
      color: white;
      text-decoration: none;
    }
    
    .toolbar-btn i {
      font-size: 14px;
    }
    
    /* Scrollbar Styling */
    .table-wrapper::-webkit-scrollbar {
      height: 12px;
      width: 12px;
    }
    
    .table-wrapper::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb {
      background: #E08D3C;
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb:hover {
      background: #c0392b;
    }
    
    .table-wrapper::-webkit-scrollbar-corner {
      background: #f1f1f1;
    }
    
    /* Toolbar Styling */
    .toolbar {
      background-color: #E08D3C;
      color: white;
      padding: 15px;
      margin: -20px -20px 20px -20px;
      border-radius: 8px 8px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .toolbar-btn {
      background-color: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      padding: 8px 15px;
      border-radius: 5px;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    
    .toolbar-btn:hover {
      background-color: rgba(255,255,255,0.3);
      color: white;
      text-decoration: none;
    }
    
    .toolbar-btn i {
      font-size: 14px;
    }
    
    /* Stats Cards */
    .stats-container {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .stat-card {
      background: linear-gradient(135deg, #E08D3C, #c0392b);
      color: white;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      min-width: 120px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    
    .stat-card h6 {
      margin: 0 0 5px 0;
      font-size: 12px;
      opacity: 0.9;
    }
    
    .stat-card .stat-number {
      font-size: 24px;
      font-weight: bold;
      margin: 0;
    }
    
    /* Main Container */
    .main-container {
      background-color: white;
      border-radius: 8px;
      padding: 20px;
      margin: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    /* Controls */
    .controls-section {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
      flex-wrap: wrap;
      align-items: end;
    }
    
    .control-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    
    .control-group label {
      font-weight: bold;
      color: #333;
      font-size: 14px;
    }
    
    .btn-primary {
      background-color: #E08D3C;
      border-color: #E08D3C;
    }
    
    .btn-primary:hover {
      background-color: #c0392b;
      border-color: #c0392b;
    }
    
    .btn-success {
      background-color: #27ae60;
      border-color: #27ae60;
    }
    
    /* Row saved animation */
    .row-saved {
      background-color: #d4edda !important;
      transition: background-color 0.5s ease;
    }
    
    /* Warning message */
    .warning {
      color: #E08D3C;
      font-size: 14px;
      margin-top: 10px;
      padding: 10px;
      background-color: #fdf2f2;
      border-left: 4px solid #E08D3C;
      border-radius: 4px;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .toolbar {
        flex-direction: column;
        gap: 15px;
      }
      
      .toolbar-left, .toolbar-right {
        justify-content: center;
        width: 100%;
      }
      
      .stats-container {
        justify-content: center;
      }
      
      .controls-section {
        flex-direction: column;
        align-items: stretch;
      }
      
      .main-container {
        margin: 10px;
        padding: 15px;
      }
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <div class="navbar">
    <div class="navbar-content">
      <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
      <img src="../includes/images/mlhud jpeg 6.jpg" alt="Open Registry Logo" class="navbar-logo">
      <span class="navbar-brand">Edit Pensioners Register</span>
    </div>
    <button class="btn btn-outline-light" onclick="history.back()">
      <i class="fas fa-arrow-left"></i> Back
    </button>
  </div>

  <div class="main-container">
    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <a href="<?= htmlspecialchars($registryDashboardUrl) ?>" class="toolbar-btn">
        Home
        </a>
        <button class="toolbar-btn" onclick="history.back()">
          <i class="fas fa-arrow-left"></i> Back
        </button>
      </div>
      <div class="toolbar-right">
        <button class="toolbar-btn" onclick="addMoreRows()">
          <i class="fas fa-plus"></i> Add Rows
        </button>
        <button class="toolbar-btn" onclick="exportTable()">
          Export
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-container">
      <div class="stat-card">
        <h6>Saved Rows</h6>
        <p class="stat-number" id="savedCount">0</p>
      </div>
      <div class="stat-card">
        <h6>Total Rows</h6>
        <p class="stat-number" id="totalRows">0</p>
      </div>
      <div class="stat-card">
        <h6>User</h6>
        <p class="stat-number"><?= htmlspecialchars($username) ?></p>
      </div>
    </div>

    <!-- Controls -->
    <div class="controls-section">
      <div class="control-group">
        <label for="createdBy">Created By</label>
        <input type="text" id="createdBy" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
      </div>
      <div class="control-group">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rowModal">
 Start Editing
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="table-wrapper">
      <div id="tableContainer"></div>
    </div>
    
    <div class="warning" id="warningMessage" style="display: none;"></div>
  </div>

  <!-- Row Count Modal -->
  <div class="modal fade" id="rowModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="rowForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Enter Row Count</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label for="rowCount">How many rows do you want to enter?</label>
          <input type="number" id="rowCount" class="form-control" min="1" max="100" value="10" required>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="continueRows">
            <label class="form-check-label" for="continueRows">Continue from previous unfinished rows</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">
 Create Table
          </button>
        </div>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let totalRows = 0;
    let savedRows = 0;

    document.getElementById("rowForm").addEventListener("submit", function(e) {
      e.preventDefault();
      const rowCount = parseInt(document.getElementById("rowCount").value);
      
      // Close modal
      const modal = bootstrap.Modal.getInstance(document.getElementById("rowModal"));
      modal.hide();
      
      // Generate table
      generateTableWithInteractivity(rowCount);
    });

    function generateTable(rows) {
      const container = document.getElementById("tableContainer");
      container.innerHTML = "";

      const table = document.createElement("table");
      table.className = "excel-table";

      const thead = document.createElement("thead");
      thead.innerHTML = `
        <tr>
          <th class="col-file-number">File Number</th>
          <th class="col-full-name">Full Name</th>
          <th class="col-designation">Designation</th>
          <th class="col-date-opened">Date Opened</th>
          <th class="col-date-closed">Date Closed</th>
          <th class="col-status">Status</th>
        </tr>
      `;
      table.appendChild(thead);

      const tbody = document.createElement("tbody");

      for (let i = 0; i < rows; i++) {
        const row = document.createElement("tr");

        for (let col = 0; col < 5; col++) {
          const cell = document.createElement("td");
          let input;

          if (col === 3 || col === 4) {
            // Date fields
            input = document.createElement("input");
            input.type = "date";
          } else if (col === 5) {
            // Status dropdown
            input = document.createElement("select");
            input.required = true;
            ["Active", "Semi-active", "Transferred", "Deceased"].forEach(opt => {
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
            if (col === 0) input.placeholder = "Enter file number";
            if (col === 1) input.placeholder = "Enter full name";
            if (col === 2) input.placeholder = "Enter designation";
            if (col === 0) input.required = true; // File Number required
          }

          input.dataset.row = i;
          input.dataset.col = col;
          input.addEventListener("blur", () => checkRowCompletion(i));
          input.addEventListener("input", () => checkRowCompletion(i));
          
          cell.appendChild(input);
          row.appendChild(cell);
        }

        tbody.appendChild(row);
      }

      table.appendChild(tbody);
      container.appendChild(table);
      
      totalRows = rows;
      document.getElementById("totalRows").textContent = totalRows;
    }

    function checkRowCompletion(rowIndex) {
      const inputs = document.querySelectorAll(`input[data-row='${rowIndex}'], select[data-row='${rowIndex}']`);
      let rowData = [];

      inputs.forEach((input, idx) => {
        rowData.push(input.value.trim());
      });

      // Check required fields: File Number (0), Full Name (1), Designation (2), Date Opened (3)
      const requiredFields = [0, 1, 2, 3];
      const missingFields = [];
      
      requiredFields.forEach(fieldIndex => {
        if (!rowData[fieldIndex] || rowData[fieldIndex] === '') {
          missingFields.push(fieldIndex);
        }
      });

      if (missingFields.length === 0) {
        document.getElementById('warningMessage').style.display = 'none';
        saveRow(rowData);
      } else {
        const fieldNames = ['File Number', 'Full Name', 'Designation', 'Date Opened', 'Date Closed'];
        const missingNames = missingFields.map(idx => fieldNames[idx]).join(', ');
        document.getElementById('warningMessage').textContent = `Please fill required fields: ${missingNames}`;
        document.getElementById('warningMessage').style.display = 'block';
      }
    }'], select[data-row='${rowIndex}']`);
      let rowData = [];

      inputs.forEach((input, idx) => {
        rowData.push(input.value.trim());
      });

      // Check required fields: File Number (0), Full Name (1), Designation (2), Date Opened (3)
      const requiredFields = [0, 1, 2, 3];
      const missingFields = [];
      
      requiredFields.forEach(fieldIndex => {
        if (!rowData[fieldIndex] || rowData[fieldIndex] === "") {
          missingFields.push(fieldIndex);
        }
      });

      if (missingFields.length === 0) {
        document.getElementById("warningMessage").style.display = "none";
        saveRow(rowData);
      } else {
        const fieldNames = ['File Number', 'Full Name', 'Designation', 'Date Opened', 'Date Closed', 'Status'];
        const missingNames = missingFields.map(idx => fieldNames[idx]).join(', ');
        document.getElementById("warningMessage").textContent = `Please fill required fields: ${missingNames}`;
        document.getElementById("warningMessage").style.display = "block";
      }
    }

    function saveRow(data) {
      fetch("streamlined_save.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
          table: "pensioners_register",
          row: data 
        })
      })
      .then(res => res.json())
      .then(response => {
        if (response.success) {
          // Highlight the row
          const inputs = document.querySelectorAll(`input[data-row='${data[0]}']`);
          inputs.forEach(input => {
            input.parentElement.parentElement.classList.add("row-saved");
          });

          // Update saved row counter
          savedRows++;
          document.getElementById("savedCount").textContent = savedRows;

          // Remove highlight after 3 seconds
          setTimeout(() => {
            inputs.forEach(input => {
              input.parentElement.parentElement.classList.remove("row-saved");
            });
          }, 3000);

          // Show success message
          showMessage("Row saved successfully!", "success");
        } else {
          showMessage("Error saving row: " + response.error, "error");
        }
      })
      .catch(err => {
        console.error("Error saving row:", err);
        showMessage("Error saving row. Please try again.", "error");
      });
    }

    function showMessage(message, type) {
      // Remove existing messages
      const existingMessage = document.getElementById('saveMessage');
      if (existingMessage) {
        existingMessage.remove();
      }

      // Create new message
      const messageDiv = document.createElement('div');
      messageDiv.id = 'saveMessage';
      messageDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
      messageDiv.style.position = 'fixed';
      messageDiv.style.top = '20px';
      messageDiv.style.right = '20px';
      messageDiv.style.zIndex = '9999';
      messageDiv.style.minWidth = '300px';
      
      messageDiv.innerHTML = `
          ${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;

      document.body.appendChild(messageDiv);

      // Auto-remove after 5 seconds
      setTimeout(() => {
        if (messageDiv.parentNode) {
          messageDiv.remove();
        }
      }, 5000);
    }

    function addMoreRows() {
      const currentRows = document.querySelectorAll("tbody tr").length;
      const additionalRows = parseInt(prompt("How many more rows do you want to add?", "5"));
      
      if (additionalRows && additionalRows > 0) {
        const container = document.getElementById("tableContainer");
        const table = container.querySelector("table");
        const tbody = table.querySelector("tbody");
        
        for (let i = 0; i < additionalRows; i++) {
          const row = document.createElement("tr");
          const rowIndex = currentRows + i;

          for (let col = 0; col < 5; col++) {
            const cell = document.createElement("td");
            let input;

            if (col === 3 || col === 4) {
              input = document.createElement("input");
              input.type = "date";
            } else if (col === 5) {
              input = document.createElement("select");
              input.required = true;
              ["Active", "Semi-active", "Transferred", "Deceased"].forEach(opt => {
                const option = document.createElement("option");
                option.value = opt;
                option.textContent = opt;
                input.appendChild(option);
              });
              input.insertAdjacentHTML('afterbegin', '<option value="" disabled selected>Select status</option>');
            } else {
              input = document.createElement("input");
              input.type = "text";
              if (col === 0) input.placeholder = "Enter file number";
              if (col === 1) input.placeholder = "Enter full name";
              if (col === 2) input.placeholder = "Enter designation";
              if (col === 0) input.required = true;
            }

            input.dataset.row = rowIndex;
            input.dataset.col = col;
            input.addEventListener("blur", () => checkRowCompletion(rowIndex));
            input.addEventListener("input", () => checkRowCompletion(rowIndex));
            
            cell.appendChild(input);
            row.appendChild(cell);
          }

          tbody.appendChild(row);
        }
        
        totalRows += additionalRows;
        document.getElementById("totalRows").textContent = totalRows;
        
        // Re-initialize interactivity for new rows
        setTimeout(addSpreadsheetInteractivity, 100);
      }
    }

    function exportTable() {
      alert("Export functionality will be implemented soon!");
    }



    // Spreadsheet-like interactivity
    function addSpreadsheetInteractivity() {
      const table = document.querySelector(".excel-table");
      if (!table) return;

      // Auto-expand text inputs based on content
      function autoExpandInput(input) {
        const minWidth = 100;
        const maxWidth = 400;
        const textLength = input.value.length;
        
        if (textLength > 15) {
          const newWidth = Math.min(minWidth + (textLength * 8), maxWidth);
          input.style.width = newWidth + "px";
        } else {
          input.style.width = "100%";
        }
      }

      // Add event listeners to all inputs
      function addInputListeners() {
        const inputs = table.querySelectorAll("input[type=\"text\"]");
        inputs.forEach(input => {
          // Auto-expand on input
          input.addEventListener("input", function() {
            autoExpandInput(this);
          });
          
          // Auto-expand on focus
          input.addEventListener("focus", function() {
            autoExpandInput(this);
            this.style.minWidth = "200px";
          });
          
          // Reset on blur if empty
          input.addEventListener("blur", function() {
            if (this.value.trim() === "") {
              this.style.width = "100%";
              this.style.minWidth = "100px";
            }
          });
          
          // Handle Enter key for next cell navigation
          input.addEventListener("keydown", function(e) {
            if (e.key === "Enter") {
              e.preventDefault();
              navigateToNextCell(this);
            }
          });
          
          // Handle Tab key for cell navigation
          input.addEventListener("keydown", function(e) {
            if (e.key === "Tab") {
              e.preventDefault();
              navigateToNextCell(this);
            }
          });
        });
      }

      // Navigate to next cell
      function navigateToNextCell(currentInput) {
        const currentCell = currentInput.closest("td");
        const currentRow = currentCell.closest("tr");
        const currentColIndex = Array.from(currentRow.children).indexOf(currentCell);
        
        let nextCell = null;
        
        // Try to find next cell in same row
        if (currentColIndex < currentRow.children.length - 1) {
          nextCell = currentRow.children[currentColIndex + 1].querySelector("input, select");
        } else {
          // Move to first cell of next row
          const nextRow = currentRow.nextElementSibling;
          if (nextRow) {
            nextCell = nextRow.querySelector("input, select");
          }
        }
        
        if (nextCell) {
          nextCell.focus();
          if (nextCell.tagName === "INPUT" && nextCell.type === "text") {
            nextCell.select();
          }
        }
      }

      // Add resize handles to cells
      function addResizeHandles() {
        const cells = table.querySelectorAll("td");
        cells.forEach(cell => {
          if (!cell.querySelector(".cell-resize-handle")) {
            const handle = document.createElement("div");
            handle.className = "cell-resize-handle";
            cell.appendChild(handle);
            
            // Add resize functionality
            handle.addEventListener("mousedown", function(e) {
              e.preventDefault();
              startResize(cell, e);
            });
          }
        });
      }

      // Cell resize functionality
      function startResize(cell, e) {
        const startX = e.clientX;
        const startWidth = cell.offsetWidth;
        
        function doResize(e) {
          const newWidth = startWidth + (e.clientX - startX);
          if (newWidth > 100) {
            cell.style.width = newWidth + "px";
            cell.style.minWidth = newWidth + "px";
          }
        }
        
        function stopResize() {
          document.removeEventListener("mousemove", doResize);
          document.removeEventListener("mouseup", stopResize);
        }
        
        document.addEventListener("mousemove", doResize);
        document.addEventListener("mouseup", stopResize);
      }

      // Initialize interactivity
      addInputListeners();
      addResizeHandles();
      
      // Re-initialize when new rows are added
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === "childList") {
            addInputListeners();
            addResizeHandles();
          }
        });
      });
      
      observer.observe(table, {
        childList: true,
        subtree: true
      });
    }

    // Enhanced table generation with interactivity
    function generateTableWithInteractivity(rows) {
      generateTable(rows);
      setTimeout(addSpreadsheetInteractivity, 100);
    }
    


    // Spreadsheet-like interactivity
    function addSpreadsheetInteractivity() {
      const table = document.querySelector('.excel-table');
      if (!table) return;

      // Auto-expand text inputs based on content
      function autoExpandInput(input) {
        const minWidth = 100;
        const maxWidth = 400;
        const textLength = input.value.length;
        
        if (textLength > 15) {
          const newWidth = Math.min(minWidth + (textLength * 8), maxWidth);
          input.style.width = newWidth + 'px';
        } else {
          input.style.width = '100%';
        }
      }

      // Add event listeners to all inputs
      function addInputListeners() {
        const inputs = table.querySelectorAll('input[type="text"]');
        inputs.forEach(input => {
          // Auto-expand on input
          input.addEventListener('input', function() {
            autoExpandInput(this);
          });
          
          // Auto-expand on focus
          input.addEventListener('focus', function() {
            autoExpandInput(this);
            this.style.minWidth = '200px';
          });
          
          // Reset on blur if empty
          input.addEventListener('blur', function() {
            if (this.value.trim() === '') {
              this.style.width = '100%';
              this.style.minWidth = '100px';
            }
          });
          
          // Handle Enter key for next cell navigation
          input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              navigateToNextCell(this);
            }
          });
          
          // Handle Tab key for cell navigation
          input.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
              e.preventDefault();
              navigateToNextCell(this);
            }
          });
        });
      }

      // Navigate to next cell
      function navigateToNextCell(currentInput) {
        const currentCell = currentInput.closest('td');
        const currentRow = currentCell.closest('tr');
        const currentColIndex = Array.from(currentRow.children).indexOf(currentCell);
        
        let nextCell = null;
        
        // Try to find next cell in same row
        if (currentColIndex < currentRow.children.length - 1) {
          nextCell = currentRow.children[currentColIndex + 1].querySelector('input, select');
        } else {
          // Move to first cell of next row
          const nextRow = currentRow.nextElementSibling;
          if (nextRow) {
            nextCell = nextRow.querySelector('input, select');
          }
        }
        
        if (nextCell) {
          nextCell.focus();
          if (nextCell.tagName === 'INPUT' && nextCell.type === 'text') {
            nextCell.select();
          }
        }
      }

      // Add resize handles to cells
      function addResizeHandles() {
        const cells = table.querySelectorAll('td');
        cells.forEach(cell => {
          if (!cell.querySelector('.cell-resize-handle')) {
            const handle = document.createElement('div');
            handle.className = 'cell-resize-handle';
            cell.appendChild(handle);
            
            // Add resize functionality
            handle.addEventListener('mousedown', function(e) {
              e.preventDefault();
              startResize(cell, e);
            });
          }
        });
      }

      // Cell resize functionality
      function startResize(cell, e) {
        const startX = e.clientX;
        const startWidth = cell.offsetWidth;
        
        function doResize(e) {
          const newWidth = startWidth + (e.clientX - startX);
          if (newWidth > 100) {
            cell.style.width = newWidth + 'px';
            cell.style.minWidth = newWidth + 'px';
          }
        }
        
        function stopResize() {
          document.removeEventListener('mousemove', doResize);
          document.removeEventListener('mouseup', stopResize);
        }
        
        document.addEventListener('mousemove', doResize);
        document.addEventListener('mouseup', stopResize);
      }

      // Initialize interactivity
      addInputListeners();
      addResizeHandles();
      
      // Re-initialize when new rows are added
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'childList') {
            addInputListeners();
            addResizeHandles();
          }
        });
      });
      
      observer.observe(table, {
        childList: true,
        subtree: true
      });
    }

    // Enhanced table generation with interactivity
    function generateTableWithInteractivity(rows) {
      generateTable(rows);
      setTimeout(addSpreadsheetInteractivity, 100);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        // Auto-save functionality can be added here
      }
    });
  </script>

</body>
</html>

