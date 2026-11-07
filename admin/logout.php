<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

requireCsrfToken();
$cookieParameters = session_get_cookie_params();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookieParameters['path'],
        'domain' => $cookieParameters['domain'],
        'secure' => $cookieParameters['secure'],
        'httponly' => $cookieParameters['httponly'],
        'samesite' => $cookieParameters['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Location: login.php');
exit;