<?php
// Authentication, authorization, session management and CSRF protection.
// Every page in php/ requires this file first.
//
//   Authentication = who are you?   (login, session, requireLogin)
//   Authorization  = what may you do? (requireRole, ownership checks in services)

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/services/userService.php';

const SESSION_IDLE_TIMEOUT = 1800;        // 30 minutes without activity
const SESSION_ABSOLUTE_TIMEOUT = 28800;   // 8 hours after login
const SESSION_REGENERATE_EVERY = 900;     // rotate the ID every 15 minutes

function requestIsHttps()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function startSecureSession()
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli' || headers_sent()) {
        return;   // CLI tests use a plain $_SESSION array
    }
    ini_set('session.use_strict_mode', '1');   // reject IDs the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept IDs in URLs
    ini_set('session.use_trans_sid', '0');
    session_name('PTH_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => requestIsHttps(),
        'httponly' => true,                    // JavaScript cannot read the cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

function sendSecurityHeaders()
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    // Protected pages must not be served from the browser cache after logout.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// ---------------------------------------------------------------------
// Flash messages (shown once on the next page)
// ---------------------------------------------------------------------
function setFlash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function takeFlashes()
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------
// CSRF protection: one random token per session, checked on every POST.
// ---------------------------------------------------------------------
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrfToken($submitted)
{
    return is_string($submitted) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function requirePost()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('POST required');
    }
}

function verifyCsrfOrFail()
{
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired form token. Go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------------
// UM-09 Log In (session part) / UM-10 Log Out
// ---------------------------------------------------------------------
function loginUser(array $user)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);           // prevent session fixation
    }
    $now = time();
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = $now;
    $_SESSION['last_activity'] = $now;
    $_SESSION['last_regenerated'] = $now;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // new token for the new session
}

function logoutUser()
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}

// Returns the signed-in user (fresh from the database) or null.
// Re-reading the account on every request means a deactivated account or a
// role change takes effect immediately, not at the next login.
function currentUser($pdo = null)
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $now = time();
    $idle = $now - (int)($_SESSION['last_activity'] ?? 0);
    $age = $now - (int)($_SESSION['login_time'] ?? 0);
    if ($idle > SESSION_IDLE_TIMEOUT || $age > SESSION_ABSOLUTE_TIMEOUT) {
        logoutUser();
        startSecureSession();
        setFlash('warning', 'Your session expired. Please log in again.');
        return null;
    }
    $pdo = $pdo ?: openDatabase();
    $user = findUserById($pdo, (int)$_SESSION['user_id']);
    if (!$user || $user['status'] !== 'Active') {
        logoutUser();
        startSecureSession();
        setFlash('warning', 'Your account is no longer active.');
        return null;
    }
    $_SESSION['last_activity'] = $now;
    $_SESSION['role'] = $user['role'];
    if ($now - (int)($_SESSION['last_regenerated'] ?? 0) > SESSION_REGENERATE_EVERY && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = $now;
    }
    return publicUserFields($user);
}

function redirectTo($location)
{
    header('Location: ' . $location, true, 303);
    exit;
}

// Authentication gate: anonymous visitors are sent to the login page.
function requireLogin($pdo = null)
{
    $user = currentUser($pdo);
    if (!$user) {
        if (empty($_SESSION['flash'])) {
            setFlash('info', 'Please log in to continue.');
        }
        redirectTo('login.php');
    }
    return $user;
}

// Authorization gate: signed-in users without the right role get 403.
function requireRole(array $roles, $pdo = null)
{
    $user = requireLogin($pdo);
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        renderAccessDenied($user);
        exit;
    }
    return $user;
}

function renderAccessDenied($user)
{
    $pageTitle = 'Access denied';
    require __DIR__ . '/header.php';
    require __DIR__ . '/nav.php';
    echo '<main class="container py-4"><h1>Access denied</h1>'
        . '<div class="alert alert-danger mt-3">Your account does not have permission to open this page.</div>'
        . '<a class="btn btn-secondary" href="dashboard.php">Back to dashboard</a></main>';
    require __DIR__ . '/footer.php';
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Bootstrap for every web request that includes this file.
if (PHP_SAPI !== 'cli') {
    startSecureSession();
    sendSecurityHeaders();
}
