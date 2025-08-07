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
        $message = "✅ User created successfully!";
    } else {
        $message = "❌ Error: " . $stmt->error;
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
      background-color: #f4f4f4;
      padding: 20px;
      font-family: Arial, sans-serif;
    }
    .form-container {
      background-color: white;
      padding: 25px;
      border-radius: 8px;
      max-width: 500px;
      margin: auto;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>

  <div class="form-container">
    <h4>Create New User</h4>
    <?php if ($message): ?>
      <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Assign Module</label>
        <select name="module" class="form-control" required>
          <option value="">Select Module</option>
          <option value="file_diary">File Diary Register</option>
          <option value="incoming_mail">Incoming Mail Register</option>
          <option value="master_register">Master Register</option>
          <option value="pensioners_register">Pension Register</option>
          <option value="semicurrent_current">Semi Current Register</option>
           <option value="semicurrent_current">Semi Current Register</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Create User</button>
    </form>
  </div>

  <?php 
  $result = $conn->query("SELECT username, email, password, assigned_module FROM users");
  echo "<table class='table'>";
  echo "<thead><tr><th>Username</th><th>Module</th></tr></thead><tbody>";
  while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['username']}</td><td>{$row['email']}</td><td>{$row['password']}</td><td>{$row['assigned_module']}</td></tr>";
  }
  echo "</tbody></table>";
  
  ?>

</body>
</html>