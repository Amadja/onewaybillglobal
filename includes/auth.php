<?php
require_once __DIR__ . '/../config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim($basePath, '/') . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_user']['id']);
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: ' . appUrl('admin/login.php'));
        exit;
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requireCsrfToken(): void
{
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals(csrfToken(), $submittedToken)) {
        http_response_code(419);
        die('Your session expired. Refresh the page and try again.');
    }
}