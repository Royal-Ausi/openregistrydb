<?php
 


// if ($_SERVER["REQUEST_METHOD"] == "POST") {
//   $username = trim($_POST["username"]);
//   $password = $_POST["password"];

//   $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
//   $stmt->bind_param("s", $username);
//   $stmt->execute();
//   $result = $stmt->get_result();
//   $user = $result->fetch_assoc();

//   if ($result->num_rows === 1) {
 

//     if (password_verify($password, $user["password"])) {
//       // Login success
//       session_start();
//       $_SESSION["username"] = $user["username"];
//       $_SESSION["assigned_modules"] = $modules;
//       $_SESSION["module"] = $user["assigned_module"];
//       $_SESSION["role"] = $user["role"];
//       header("Location: user_dashboard.php");
//       exit();
//     } else {
//       $error = "❌ Incorrect password.";
//     }
//   } else {
//     $error = "❌ Username not found.";
//   }
// <?php
// include 'includes/db.php';

// $

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
    $user = $result->fetch_assoc(); // ✅ Fetch user first

    if (password_verify($password, $user["password"])) {
      // ✅ Login success
      session_start();
      $_SESSION["username"] = $user["username"];
      $_SESSION["role"] = $user["role"];

      // ✅ Handle assigned modules
      $modules = explode(",", $user["assigned_module"]); // Split by comma
      $_SESSION["assigned_modules"] = $modules;

      // ✅ Optional: store first module as default
      $_SESSION["module"] = $modules[0];

      header("Location: user_dashboard.php");
      exit();
    } else {
      echo "❌ Incorrect password.";
    }
  } else {
    echo "❌ Username not found.";
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
  </style>
</head>
<body>

  <div class="login-box">
    <h4 class="mb-3">User Login</h4>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn custom-login-btn"  >Login</button>
    </form>
  </div>

</body>
</html>