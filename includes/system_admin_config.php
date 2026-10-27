<?php
/**
 * System operator account, configured in system_admin.local.php.
 */
if (!defined('OR_SYSTEM_ADMIN_USER')) {
    $sysAdminFile = __DIR__ . '/system_admin.local.php';
    if (!is_readable($sysAdminFile)) {
        die('Missing includes/system_admin.local.php. Copy system_admin.example.php to create it.');
    }
    $sysAdmin = require $sysAdminFile;
    define('OR_SYSTEM_ADMIN_USER', $sysAdmin['user']);
    define('OR_SYSTEM_ADMIN_EMAIL', $sysAdmin['email']);
    define('OR_SYSTEM_ADMIN_PASS', $sysAdmin['pass']);
    unset($sysAdmin, $sysAdminFile);
}

function sync_system_operator_user(PgConnection $conn): void
{
    if (!function_exists('openregistry_column_exists')) {
        require_once __DIR__ . '/schema_extensions.php';
    }
    $username = OR_SYSTEM_ADMIN_USER;
    $email = OR_SYSTEM_ADMIN_EMAIL;
    $passwordHash = password_hash(OR_SYSTEM_ADMIN_PASS, PASSWORD_DEFAULT);

    // Prefer to keep a dedicated user_type column if present
    $hasUserType = openregistry_column_exists($conn, 'users', 'user_type');

    // Try finding existing row by username
    $check = $conn->prepare("SELECT username FROM users WHERE username = ? LIMIT 1");
    if (!$check) {
        return;
    }
    $check->bind_param('s', $username);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $existingUsername = $result->fetch_assoc()['username'];
        $check->close();
        if ($hasUserType) {
            $update = $conn->prepare("UPDATE users SET email = ?, role = 'system_operator', user_type = 'system_operator', status = 'active' WHERE username = ?");
            if ($update) {
                $update->bind_param('ss', $email, $existingUsername);
                $update->execute();
                $update->close();
            }
        } else {
            $update = $conn->prepare("UPDATE users SET email = ?, role = 'system_operator', status = 'active' WHERE username = ?");
            if ($update) {
                $update->bind_param('ss', $email, $existingUsername);
                $update->execute();
                $update->close();
            }
        }

        // Refresh password only when it does not match the configured operator password.
        $verify = $conn->prepare('SELECT password FROM users WHERE username = ? LIMIT 1');
        if ($verify) {
            $verify->bind_param('s', $existingUsername);
            $verify->execute();
            $row = $verify->get_result()->fetch_assoc();
            $verify->close();
            if (!$row || !password_verify(OR_SYSTEM_ADMIN_PASS, (string)$row['password'])) {
                $pwUpdate = $conn->prepare('UPDATE users SET password = ? WHERE username = ?');
                if ($pwUpdate) {
                    $pwUpdate->bind_param('ss', $passwordHash, $existingUsername);
                    $pwUpdate->execute();
                    $pwUpdate->close();
                }
            }
        }
        return;
    }

    // Insert new system operator row
    $check->close();
    if ($hasUserType) {
        $insert = $conn->prepare("INSERT INTO users (username, email, password, role, user_type, status, created_at) VALUES (?, ?, ?, 'system_operator', 'system_operator', 'active', NOW())");
        if ($insert) {
            $insert->bind_param('sss', $username, $email, $passwordHash);
            $insert->execute();
            $insert->close();
        }
    } else {
        $insert = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, 'system_operator', 'active', NOW())");
        if ($insert) {
            $insert->bind_param('sss', $username, $email, $passwordHash);
            $insert->execute();
            $insert->close();
        }
    }
}