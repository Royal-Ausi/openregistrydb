<?php
/**
 * Lightweight session security layer for Open Registry.
 *
 * Provides:
 *  - CSRF token generation / validation
 *  - Session fingerprint binding (user-agent + IP prefix)
 *  - Session rotation on privilege change
 *  - Cross-tab heartbeat endpoint (?or_heartbeat=1)
 *  - A lightweight signed session-token cookie (HS256-style HMAC)
 *    so the session ID matches across tabs without relying solely on
 *    the PHP session cookie.
 *
 * Full JWT (RS256) would require php-jwt; this uses HMAC-SHA256
 * which is available in every PHP 7.4+ environment.
 */

declare(strict_types=1);

define('OR_SESSION_SECRET', getenv('OR_SESSION_SECRET') ?: 'change_this_in_production_32chars!');
define('OR_SESSION_COOKIE', 'or_stok');
define('OR_SESSION_LIFETIME', 1800); // 30 min inactivity

// ── Heartbeat endpoint
if (!empty($_GET['or_heartbeat'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: application/json');
    $alive = isset($_SESSION['username']) && isset($_SESSION['LAST_ACTIVITY'])
          && (time() - (int)$_SESSION['LAST_ACTIVITY'] < OR_SESSION_LIFETIME);
    if ($alive) $_SESSION['LAST_ACTIVITY'] = time();
    echo json_encode(['alive' => $alive, 'ts' => time()]);
    exit();
}

// Core helpers

function or_hmac_sign(string $payload): string {
    return base64_encode(hash_hmac('sha256', $payload, OR_SESSION_SECRET, true));
}

function or_make_token(string $sessionId, string $username): string {
    $payload = $sessionId . '|' . $username . '|' . time();
    return base64_encode($payload) . '.' . or_hmac_sign($payload);
}

function or_verify_token(string $token, string $sessionId, string $username): bool {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return false;
    $payload = base64_decode($parts[0]);
    if ($payload === false) return false;
    $bits = explode('|', $payload);
    if (count($bits) < 3) return false;
    if ($bits[0] !== $sessionId || $bits[1] !== $username) return false;
    if ((time() - (int)$bits[2]) > OR_SESSION_LIFETIME + 60) return false;
    return hash_equals($parts[1], or_hmac_sign($payload));
}

function or_session_fingerprint(): string {
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Use first 3 octets of IP to tolerate mobile IP rotation
    $ip  = implode('.', array_slice(explode('.', $_SERVER['REMOTE_ADDR'] ?? '0'), 0, 3));
    return hash('sha256', $ua . $ip . OR_SESSION_SECRET);
}

/**
 * Call after openregistry_session_start() on every protected page.
 * Issues / refreshes the signed cookie and validates fingerprint.
 */
function or_enforce_session(): void {
    $username = $_SESSION['username'] ?? null;
    if (!$username) return; // not logged in — nothing to enforce

    $sid = session_id();

    // Fingerprint check — guards against session fixation / theft
    if (isset($_SESSION['_fingerprint'])) {
        if ($_SESSION['_fingerprint'] !== or_session_fingerprint()) {
            // Fingerprint mismatch — destroy session
            session_unset();
            session_destroy();
            header('Location: ' . openregistry_uri('index.php') . '?err=session');
            exit();
        }
    } else {
        $_SESSION['_fingerprint'] = or_session_fingerprint();
    }

    // Refresh activity
    $_SESSION['LAST_ACTIVITY'] = time();

    // Issue / refresh signed cookie for cross-tab session continuity
    $cookieToken = $_COOKIE[OR_SESSION_COOKIE] ?? '';
    if (!$cookieToken || !or_verify_token($cookieToken, $sid, $username)) {
        $newToken = or_make_token($sid, $username);
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(OR_SESSION_COOKIE, $newToken, [
            'expires'  => 0,          // session cookie
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Generate or retrieve the per-session CSRF token.
 */
function or_csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

/**
 * Validate a submitted CSRF token (use on POST forms).
 */
function or_csrf_validate(string $submitted): bool {
    $token = $_SESSION['_csrf'] ?? '';
    return $token !== '' && hash_equals($token, $submitted);
}

/**
 * Rotate the session ID (call after login / privilege change).
 */
function or_rotate_session(): void {
    session_regenerate_id(true);
    $_SESSION['_fingerprint'] = or_session_fingerprint();
}
