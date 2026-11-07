<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM auth_users WHERE id = ? LIMIT 1');
    $userId = (int) $_SESSION['admin_user']['id'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        $errorMessage = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = 'Choose a new password with at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'The new passwords do not match.';
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = db()->prepare('UPDATE auth_users SET password_hash = ? WHERE id = ?');
        $update->bind_param('si', $passwordHash, $userId);
        $update->execute();
        $update->close();
        $message = 'Password updated.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Security | Garamba Sky Cargo Admin</title>
    <style>
        :root { --ink: #1b2c38; --muted: #677985; --line: #dce4e9; --blue: #176c93; --navy: #102d3a; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: #f3f6f7; font: 14px/1.5 Segoe UI, Arial, sans-serif; }
        .wrap { width: min(720px, calc(100% - 32px)); margin: 32px auto; }
        .topbar { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; }
        .topbar a { color: var(--blue); text-decoration: none; }
        .topbar form { margin-left: auto; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 25px; }
        p { color: var(--muted); }
        label { display: block; margin: 16px 0 6px; font-weight: 700; }
        input, button { width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 5px; font: inherit; }
        button { margin-top: 20px; border: 0; color: #fff; background: var(--navy); font-weight: 700; cursor: pointer; }
        .message, .error { padding: 11px 13px; border-radius: 5px; }
        .message { color: #1c634d; background: #eaf6f1; }
        .error { color: #8e2e27; background: #fff2ef; }
        @media(max-width:520px) { .topbar { flex-wrap: wrap; } .topbar form { margin-left: 0; } }
    </style>
    <link rel="stylesheet" href="<?= e(assetUrl('admin/admin-ui.css')); ?>">
</head>
<body>
<main class="wrap">
    <nav class="topbar" aria-label="Admin navigation">
        <a href="index.php">Dashboard</a><a href="customers.php">Clients</a><a href="account.php">Account</a>
        <form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>"><button type="submit">Sign out</button></form>
    </nav>
    <section class="panel">
        <h1>Account security</h1>
        <p>Signed in as <?= e((string) $_SESSION['admin_user']['username']); ?>. Change the starter password before exposing this site publicly.</p>
        <?php if ($message !== ''): ?><div class="message" role="status"><?= e($message); ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error" role="alert"><?= e($errorMessage); ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            <label for="new_password">New password</label>
            <input id="new_password" type="password" name="new_password" autocomplete="new-password" minlength="8" required>
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
            <button type="submit">Update password</button>
        </form>
    </section>
</main>
</body>
</html>
