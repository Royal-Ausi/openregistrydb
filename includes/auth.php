<?php
/**
 * Central access control helpers.
 * Admin access is granted when the session indicates role/user_type are 'admin'.
 * The application uses a unified `users` table for all account types.
 */

function openregistry_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Web path to the app root (no trailing slash), e.g. /openregistrydb
 */
function openregistry_project_base_uri(): string
{
    $sn = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($sn);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '';
    }
    $norm = rtrim($dir, '/');
    if (substr($norm, -strlen('/modules')) === '/modules') {
        $parent = dirname($norm);
        return ($parent === '/' || $parent === '.') ? '' : $parent;
    }
    return $norm;
}

/**
 * Absolute path from server root for redirects (works in subdirectory installs).
 */
function openregistry_uri(string $path): string
{
    $base = openregistry_project_base_uri();
    $path = ltrim($path, '/');
    if ($base === '') {
        return '/' . $path;
    }
    return $base . '/' . $path;
}

function openregistry_is_admin(): bool
{
    return isset($_SESSION['username'])
        && ($_SESSION['user_type'] ?? '') === 'admin'
        && ($_SESSION['role'] ?? '') === 'admin';
}

/** Separate system-operator (hardcoded) session; not organisation admin_accounts. */
function openregistry_is_system_operator(): bool
{
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    $userType = strtolower(trim((string)($_SESSION['user_type'] ?? '')));
    return !empty($_SESSION['system_operator'])
        || $role === 'system_operator'
        || $userType === 'system_operator';
}

/** Organisation admin OR system operator — full admin-area tools (dashboard, create user, status manager, etc.). */
function openregistry_has_admin_tools_access(): bool
{
    return openregistry_is_admin() || openregistry_is_system_operator();
}

function openregistry_can_import(): bool
{
    return openregistry_is_admin()
        || openregistry_is_system_operator()
        || !empty($_SESSION['can_import']);
}

function openregistry_can_export(): bool
{
    return openregistry_is_admin()
        || openregistry_is_system_operator()
        || !empty($_SESSION['can_export']);
}

function openregistry_require_admin(): void
{
    openregistry_session_start();
    if (openregistry_is_admin()) {
        return;
    }
    if (!isset($_SESSION['username'])) {
        header('Location: ' . openregistry_uri('index.php'));
    } else {
        header('Location: ' . openregistry_uri('user_dashboard.php'));
    }
    exit();
}

/** Use for pages that organisation admins and system operators may access. */
function openregistry_require_admin_tools_access(): void
{
    openregistry_session_start();
    if (openregistry_has_admin_tools_access()) {
        return;
    }
    if (!isset($_SESSION['username'])) {
        header('Location: ' . openregistry_uri('index.php'));
    } else {
        header('Location: ' . openregistry_uri('user_dashboard.php'));
    }
    exit();
}

function openregistry_is_ajax_request(): bool
{
    $xRequestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($xRequestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));
    return strpos($accept, 'application/json') !== false;
}

function openregistry_require_system_operator(): void
{
    openregistry_session_start();
    if (openregistry_is_system_operator()) {
        return;
    }

    if (openregistry_is_ajax_request()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit();
    }

    header('Location: ' . openregistry_uri('system_admin_login.php'));
    exit();
}

function openregistry_resolve_user_role(array $user): string
{
    $role = strtolower(trim((string)($user['role'] ?? '')));
    $userType = strtolower(trim((string)($user['user_type'] ?? '')));

    if ($role === 'admin' || $userType === 'admin') {
        return 'admin';
    }
    if ($role === 'system_operator' || $userType === 'system_operator') {
        return 'system_operator';
    }
    return 'user';
}

function openregistry_normalize_module_list($value): array
{
    if (is_array($value)) {
        $modules = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $modules[] = $item;
            }
        }
        return array_values(array_unique($modules));
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = strpos($value, ',') !== false
            ? array_map('trim', explode(',', $value))
            : [$value];

        $modules = [];
        foreach ($parts as $part) {
            if ($part !== '') {
                $modules[] = $part;
            }
        }
        return array_values(array_unique($modules));
    }

    return [];
}

function openregistry_apply_user_session(array $user): string
{
    openregistry_session_start();
    $resolvedRole = openregistry_resolve_user_role($user);

    $_SESSION['username'] = (string)($user['username'] ?? '');
    $_SESSION['email'] = (string)($user['email'] ?? '');
    $_SESSION['role'] = $resolvedRole;
    $_SESSION['user_type'] = $resolvedRole;

    if (isset($user['id'])) {
        $_SESSION['user_id'] = (int)$user['id'];
    }

    if (isset($user['assigned_modules'])) {
        $modules = openregistry_normalize_module_list($user['assigned_modules']);
    } elseif (isset($user['assigned_module'])) {
        $modules = openregistry_normalize_module_list($user['assigned_module']);
    } else {
        $modules = [];
    }

    $_SESSION['assigned_modules'] = $modules;
    $_SESSION['assigned_module'] = implode(',', $modules);

    if ($resolvedRole === 'system_operator') {
        $_SESSION['system_operator'] = true;
    } else {
        unset($_SESSION['system_operator']);
    }

    if (isset($user['can_import'])) {
        $_SESSION['can_import'] = (bool)$user['can_import'];
    }
    if (isset($user['can_export'])) {
        $_SESSION['can_export'] = (bool)$user['can_export'];
    }
    if (isset($user['status'])) {
        $_SESSION['status'] = (string)$user['status'];
    }

    return $resolvedRole;
}

function openregistry_resolve_user_role_from_session(): string
{
    openregistry_session_start();
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    $userType = strtolower(trim((string)($_SESSION['user_type'] ?? '')));

    if ($role === 'admin' || $userType === 'admin') {
        return 'admin';
    }
    if ($role === 'system_operator' || $userType === 'system_operator') {
        return 'system_operator';
    }
    return 'user';
}

function openregistry_login_redirect_for_role(string $role): string
{
    $role = strtolower(trim($role));
    if ($role === 'admin') {
        return openregistry_uri('admin_dashboard.php');
    }
    if ($role === 'system_operator') {
        return openregistry_uri('system_admin_dashboard.php');
    }
    return openregistry_uri('user_dashboard.php');
}

/** Dashboard home URL for the current session (admin vs user portal). */
function openregistry_dashboard_href(): string
{
    openregistry_session_start();
    if (openregistry_is_admin()) {
        return openregistry_uri('admin_dashboard.php');
    }
    if (openregistry_is_system_operator()) {
        return openregistry_uri('system_admin_dashboard.php');
    }
    return openregistry_uri('user_dashboard.php');
}
