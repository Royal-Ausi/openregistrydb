<?php
session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/system_admin_config.php';

$error = "";

function syncSystemOperatorToUsers(mysqli $conn): void {
    $username = OR_SYSTEM_ADMIN_USER;
    $email = 'system.operator@openregistry.local';
    $passwordHash = password_hash(OR_SYSTEM_ADMIN_PASS, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'admin'");
    if (!$check) {
        return;
    }
    $check->bind_param("s", $username);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $update = $conn->prepare("UPDATE users SET password = ?, email = ? WHERE username = ? AND role = 'admin'");
        if ($update) {
            $update->bind_param("sss", $passwordHash, $email, $username);
            $update->execute();
            $update->close();
        }
    } else {
        $status = 'active';
        $role = 'admin';
        $insert = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($insert) {
            $insert->bind_param("sssss", $username, $email, $passwordHash, $role, $status);
            $insert->execute();
            $insert->close();
        }
    }

    $check->close();
}

// Keep hardcoded system operator mirrored in users table (best-effort)
syncSystemOperatorToUsers($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    // System operator uses the same admin login form
    if ($username === OR_SYSTEM_ADMIN_USER && $password === OR_SYSTEM_ADMIN_PASS) {
        $_SESSION["system_operator"] = true;
        $_SESSION["username"] = OR_SYSTEM_ADMIN_USER;
        unset($_SESSION["role"], $_SESSION["user_type"], $_SESSION["assigned_modules"], $_SESSION["module"]);
        header("Location: system_admin_dashboard.php");
        exit();
    }

    // Use unified users table for admins
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
    if (!$stmt) {
        $error = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

            if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {
                unset($_SESSION["system_operator"]);
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = "admin";
                $_SESSION["user_type"] = "admin";
                unset($_SESSION["can_import"], $_SESSION["can_export"]);
                $_SESSION["assigned_modules"] = ["admin_dashboard", "viewDatabase", "create_user", "admin_status_manager"];
                $_SESSION["module"] = "admin_dashboard";
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Incorrect password, Please Try Again";
            }
        } else {
            $error = "Admin username not found.";
        }
        $stmt->close();
    }
}

// If we reach here, there was an error
$_SESSION['admin_login_error'] = $error;
header("Location: index.php");
exit();
?>
