<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
require_once __DIR__ . '/includes/system_admin_config.php';

openregistry_ensure_extensions($conn);
sync_system_operator_user($conn);

function getColumnExists(PgConnection $conn, string $column): bool
{
    return openregistry_column_exists($conn, 'users', $column);
}
$hasUserTypeColumn = getColumnExists($conn, 'user_type');
$hasStatusColumn = getColumnExists($conn, 'status');
$hasTitleColumn = getColumnExists($conn, 'title');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'login'));
    $accountType = trim((string)($_POST['account_type'] ?? 'admin'));
    $accountType = $accountType === 'admin' ? 'admin' : 'user';

    if ($action === 'register') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $title = trim((string)($_POST['title'] ?? ''));

        $allowedTitles = [
            'Senior Records Officer',
            'Asst Records Officer',
        ];

        if ($accountType !== 'admin') {
            $_SESSION['auth_error'] = 'Regular users cannot self-register. Please log in with assigned credentials.';
            header('Location: index.php');
            exit();
        }

        if ($username === '' || $email === '' || $password === '' || $confirmPassword === '' || $title === '') {
            $_SESSION['auth_error'] = 'All fields are required to register an administrator.';
            header('Location: index.php');
            exit();
        }

        if (!in_array($title, $allowedTitles, true)) {
            $_SESSION['auth_error'] = 'Please choose a valid title.';
            header('Location: index.php');
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr(strtolower($email), -strlen('@mlhud.go.ug')) !== '@mlhud.go.ug') {
            $_SESSION['auth_error'] = 'Email must be a valid @mlhud.go.ug address.';
            header('Location: index.php');
            exit();
        }

        if ($password !== $confirmPassword) {
            $_SESSION['auth_error'] = 'Passwords do not match.';
            header('Location: index.php');
            exit();
        }

        if (strlen($password) < 6) {
            $_SESSION['auth_error'] = 'Password must be at least 6 characters long.';
            header('Location: index.php');
            exit();
        }

        $stmt = $conn->prepare('SELECT username FROM users WHERE username = ? OR email = ?');
        if (!$stmt) {
            $_SESSION['auth_error'] = 'Database error: ' . $conn->error;
            header('Location: index.php');
            exit();
        }
        $stmt->bind_param('ss', $username, $email);
        if (!$stmt->execute()) {
            $_SESSION['auth_error'] = 'Database error: ' . $stmt->error;
            $stmt->close();
            header('Location: index.php');
            exit();
        }
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $_SESSION['auth_error'] = 'Username or email already exists. Please choose a different account.';
            $stmt->close();
            header('Location: index.php');
            exit();
        }
        $stmt->close();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($hasUserTypeColumn && $hasTitleColumn && $hasStatusColumn) {
            $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, user_type, status, title) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $status = 'pending';
            $stmt->bind_param('sssssss', $username, $email, $passwordHash, $accountType, $accountType, $status, $title);
        } elseif ($hasUserTypeColumn && $hasStatusColumn) {
            $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, user_type, status) VALUES (?, ?, ?, ?, ?, ?)');
            $status = 'pending';
            $stmt->bind_param('ssssss', $username, $email, $passwordHash, $accountType, $accountType, $status);
        } elseif ($hasStatusColumn) {
            $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)');
            $status = 'pending';
            $stmt->bind_param('sssss', $username, $email, $passwordHash, $accountType, $status);
        } else {
            $stmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $username, $email, $passwordHash, $accountType);
        }

        if (!$stmt) {
            $_SESSION['auth_error'] = 'Database error: ' . $conn->error;
            header('Location: index.php');
            exit();
        }

        if ($stmt->execute()) {
            $_SESSION['auth_success'] = 'Administrator registration submitted. Approval is required before first login.';
        } else {
            $err = trim($stmt->error ?: $conn->error);
            if (stripos($err, 'users_id_key') !== false || stripos($err, 'duplicate key') !== false) {
                openregistry_sync_serial_sequence($conn, 'users', 'id');
                $_SESSION['auth_error'] = 'Registration failed due to a database sequence issue. Please try again.';
            } else {
                $_SESSION['auth_error'] = 'Failed to register administrator. ' . ($err !== '' ? $err : 'Please try again.');
            }
        }
        $stmt->close();
        header('Location: index.php');
        exit();
    }

    $usernameOrEmail = trim((string)($_POST['username_or_email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($usernameOrEmail === '' || $password === '') {
        $_SESSION['auth_error'] = 'Username/email and password are required.';
        header('Location: index.php');
        exit();
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    if (!$stmt) {
        $_SESSION['auth_error'] = 'Database error: ' . $conn->error;
        header('Location: index.php');
        exit();
    }
    $stmt->bind_param('ss', $usernameOrEmail, $usernameOrEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $_SESSION['auth_error'] = 'Invalid credentials. Please check your username/email and password.';
        $stmt->close();
        header('Location: index.php');
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if ($hasStatusColumn) {
        $status = strtolower(trim((string)($user['status'] ?? 'active')));
        if ($status !== 'active') {
            if ($status === 'pending') {
                $_SESSION['auth_error'] = 'Your account is pending approval. Please wait for activation.';
                header('Location: index.php');
                exit();
            }
            $_SESSION['auth_error'] = 'Your account is not active. Please contact support.';
            header('Location: index.php');
            exit();
        }
    }

    if (!password_verify($password, $user['password'])) {
        $_SESSION['auth_error'] = 'Incorrect password. Please try again.';
        header('Location: index.php');
        exit();
    }

    $resolvedRole = openregistry_apply_user_session($user);

    if ($resolvedRole === 'system_operator' || ($hasUserTypeColumn && strtolower(trim((string)($user['user_type'] ?? ''))) === 'system_operator')) {
        $_SESSION['system_operator'] = true;
    }

    // keep role columns aligned when legacy rows disagree.
    if ($hasUserTypeColumn) {
        $dbRole = strtolower(trim((string)($user['role'] ?? '')));
        $dbUserType = strtolower(trim((string)($user['user_type'] ?? '')));
        if ($dbRole !== $resolvedRole || $dbUserType !== $resolvedRole) {
            $sync = $conn->prepare('UPDATE users SET role = ?, user_type = ? WHERE username = ?');
            if ($sync) {
                $sync->bind_param('sss', $resolvedRole, $resolvedRole, $user['username']);
                $sync->execute();
                $sync->close();
            }
        }
    }

    header('Location: ' . openregistry_login_redirect_for_role($resolvedRole));
    exit();
}

header('Location: index.php');
exit();
