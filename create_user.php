<!-- create_user.php -->
<?php
include 'includes/db.php'; // DB connection

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $module = $_POST["module"];

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, assigned_module) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $password, $module);

    if ($stmt->execute()) {
        $message = "User created successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create User - Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: rgb(218, 215,182);
      padding: 20px;
      font-family: Arial, sans-serif;
    }
    .form-container {
      background-color: #F7E5D9;
      color: #262223;
      padding: 25px;
      border-radius: 8px;
      max-width: 800px;
      margin: auto;
      margin-top: 50px;
      box-shadow: 0 2px 6px #5f5f5fff;
    }

    #userTableContainer {
    background-color: #F7E5D9;
    color: #262223;
    border-radius: 8px;
    box-shadow: 0 2px 6px #5f5f5fff;
    padding: 25px;
    margin: auto;
    max-width: 800px;
    margin-top: 30px;
  }
  #userTableContainer table {
    background-color: #F7E5D9;
    color: #262223;
    border-radius: 8px;
    overflow: hidden;
  }
  #userTableContainer th {
    background-color: #b57526ff;
    color: #fff;
    font-weight: bold;
    border: none;
  }
  #userTableContainer td {
    background-color: #fff;
    color: #262223;
    border: none;
    vertical-align: middle;
  }
  #userTableContainer tr:nth-child(even) td {
    background-color: #f4e8dd;
  }
  .toggle-password .eye {
    font-size: 1.2em;
    color: #b57526ff;
    cursor: pointer;
  }
   
  </style>
</head>
<body>
  <?php include 'includes/navbar.php'; ?>

  <div class="form-container" style="">
    <h4 style="justify-content: center;" ><strong>Create New User</strong></h4>
    <?php if ($message): ?>
      <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="mb-3">
        <label><strong>Username</strong></label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label><strong>Email</strong></label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label><strong>Password</strong></strong></label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label><strong>Assign Task</strong></label>
        <select name="module" class="form-control" required>
          <option value="">Select Register</option>
          <option value="file_diary">File Diary</option>
          <option value="incoming_mail">Incoming Mail </option>
          <option value="master_register">Master Register</option>
          <option value="pensioners_register">Pension Register</option>
          <option value="contractstaff_register">Contract Staff Register</option>
           <option value="semicurrent_records">Semi Current Records</option>
        </select>
      </div>
      <button type="submit" class="btn" style="background-color: #b57526ff; color: #fff; 
        font-weight: bold; width: 30%;
        border-radius:6px; box-shadow: 0 2px 6px #5f5f5fff;";"
        onmouseover="this.style.backgroundColor='#b57526ff';"
        onmouseout="this.style.backgroundColor='#822b00';"
      >
        Create User
      </button>
      

    </form>
  </div>
  
    <?php

    $result = $conn->query("SELECT username, email, password, assigned_module, created_at FROM users");
    ?>
    
<div class="text-center my-3">
  <button class="btn" id="showUsersBtn" style="background-color: #b57526ff; 
    color: #fff; font-weight: bold; width: 30%; border-radius:6px; 
      box-shadow: 0 2px 6px #5f5f5fff;"
      onmouseover="this.style.backgroundColor='#b57526ff';"
    onmouseout="this.style.backgroundColor='#822b00';"
    >
    View User Logins

  </button>
</div>
    
    <div id="userTableContainer" style="display:none;">
      <table class="table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Password</th>
            <th>Module</th>
            <th>Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td>
              <span class="masked-password">********</span>
                <span class="real-password" style="display:none;"><?= htmlspecialchars($row['password']) ?></span>
                <button type="button" class="btn btn-sm btn-outline-secondary toggle-password">
                  <span class="eye">&#128065;</span>
                </button>
              </td>
              <td><?= htmlspecialchars($row['assigned_module']) ?></td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <script>
    document.getElementById('showUsersBtn').onclick = function() {
      const table = document.getElementById('userTableContainer');
      table.style.display = table.style.display === 'none' ? 'block' : 'none';
    };

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(btn => {
      btn.addEventListener('click', function() {
        const td = btn.parentElement;
        const masked = td.querySelector('.masked-password');
        const real = td.querySelector('.real-password');
        if (masked.style.display !== 'none') {
          masked.style.display = 'none';
          real.style.display = 'inline';
        } else {
          masked.style.display = 'inline';
          real.style.display = 'none';
        }
      });
    });
    </script>
</body>
</html>