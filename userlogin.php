<?php

include 'includes/db.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = trim($_POST["username"]);
  $password = $_POST["password"];

  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user["password"])) {
      session_start();
      $_SESSION["username"] = $user["username"];
      $_SESSION["role"] = $user["role"];
      $modules = explode(",", $user["assigned_module"]);
      $_SESSION["assigned_modules"] = $modules;
      $_SESSION["module"] = $modules[0];
      header("Location: user_dashboard.php");
      exit();
    } else {
      $error = "Incorrect password, Please Try Again";
    }
  } else {
    $error = "Username not found.";
  }
  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Login - Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f4f4;
      padding: 30px;
      font-family: Arial, sans-serif;
    }
    .login-box {
      max-width: 400px;
      margin: auto;
      background-color: white;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .custom-login-btn {
      background-color: #b57526ff !important;
      color: white !important;
      border: none;
      width: 100%;
    }
    .password-wrapper {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #b57526ff;
      font-size: 1.2em;
      cursor: pointer;
      padding: 0;
    }
  </style>
</head>
<body>

  <div class="login-box">
    <h4 class="mb-3">User Login</h4>
    <form method="POST" action="">
      <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3 password-wrapper">
        <label>Password</label>
        <input type="password" name="password" id="passwordInput" class="form-control" required>
        <button type="button" class="toggle-password" tabindex="-1" onclick="togglePassword()">
          &#128065;
        </button>
      </div>
      <button type="submit" class="btn custom-login-btn">Login</button>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger mt-3"><?= $error ?></div>
      <?php endif; ?>
    </form>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('passwordInput');
      input.type = input.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>