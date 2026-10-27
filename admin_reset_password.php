<?php
session_start();
include 'includes/db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_identifier = trim($_POST["user_identifier"]);
    $new_password = $_POST["new_password"];

    // Validate input
    if (empty($user_identifier) || empty($new_password)) {
        $error = "All fields are required.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if admin user exists by username or email
        $stmt = $conn->prepare("SELECT id, role FROM users WHERE (username = ? OR email = ?)");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $user_identifier, $user_identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Admin account not found. Please check your username or email.";
            } else {
                $user = $result->fetch_assoc();
                $stmt->close();

                if (($user['role'] ?? '') !== 'admin') {
                    $error = "Password reset is available only for administrator accounts.";
                } else {
                    // Hash new password and update
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ? OR email = ?");

                    if (!$stmt) {
                        $error = "Database error: " . $conn->error;
                    } else {
                        $stmt->bind_param("sss", $password_hash, $user_identifier, $user_identifier);

                        if ($stmt->execute()) {
                            $success = "Password updated successfully! You can now login with your new password.";
                        } else {
                            $error = "Failed to update password. Please try again.";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Set session messages and redirect
if (!empty($error)) {
    $_SESSION['admin_reset_error'] = $error;
} elseif (!empty($success)) {
    $_SESSION['admin_reset_success'] = $success;
}

header("Location: index.php");
exit();
?>

