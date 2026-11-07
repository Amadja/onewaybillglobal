<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, username, display_name, password_hash FROM auth_users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
        ];
        header('Location: index.php');
        exit;
    }

    $errorMessage = 'The username or password is incorrect.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In | Garamba Sky Cargo</title>
    <style>
        :root { color-scheme: light; --ink: #182a39; --muted: #687a88; --line: #d8e0e6; --blue: #176c93; --deep: #102d3a; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; color: var(--ink); background: linear-gradient(135deg, #eaf1f3, #f8faf9 54%, #dce8e9); font: 15px/1.5 Segoe UI, Arial, sans-serif; }
        .login { width: min(100%, 420px); background: #fff; padding: 38px; border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 24px 70px rgba(16, 45, 58, .12); }
        .brand { color: var(--blue); font-size: 12px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; }
        h1 { margin: 10px 0 4px; font-size: 28px; }
        .intro { margin: 0 0 26px; color: var(--muted); }
        label { display: block; margin: 15px 0 6px; font-size: 13px; font-weight: 700; }
        input { width: 100%; height: 46px; padding: 0 12px; border: 1px solid var(--line); border-radius: 6px; font: inherit; }
        input:focus { outline: 3px solid rgba(23, 108, 147, .18); border-color: var(--blue); }
        button { width: 100%; margin-top: 22px; height: 46px; border: 0; border-radius: 6px; color: #fff; background: var(--deep); font: inherit; font-weight: 700; cursor: pointer; }
        .error { margin-top: 16px; padding: 10px 12px; border: 1px solid #e5b8b4; border-radius: 6px; color: #8e2e27; background: #fff4f2; }
        .back { display: inline-block; margin-top: 22px; color: var(--blue); text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>
    <main class="login">
        <div class="brand">Garamba Sky Cargo</div>
        <h1>Admin sign in</h1>
        <p class="intro">Access shipment and client management.</p>
        <form method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <label for="username">Username</label>
            <input id="username" name="username" autocomplete="username" required autofocus>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            <button type="submit">Sign in</button>
        </form>
        <?php if ($errorMessage !== ''): ?><div class="error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <a class="back" href="<?= htmlspecialchars(appUrl(), ENT_QUOTES, 'UTF-8') ?>">Back to website</a>
    </main>
</body>
</html>