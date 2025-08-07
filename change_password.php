<?php 
session_start();
include 'includes/db.php';

$username = $_SESSION["username"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $current = $_POST["current_password"];
  $new = password_hash($_POST["new_password"], PASSWORD_DEFAULT);

  $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  if (password_verify($current, $row["password"])) {
    $update = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $update->bind_param("ss", $new, $username);
    $update->execute();
    echo "✅ Password changed successfully.";
  } else {
    echo "❌ Incorrect current password.";
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
</head>
<body>
    <form method="POST" action="">
    <label>Current Password</label>
    <input type="password" name="current_password" required>

    <label>New Password</label>
    <input type="password" name="new_password" required>

    <button type="submit">Change Password</button>
    </form>
</body>
</html>