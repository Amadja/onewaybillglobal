<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = db()->prepare('SELECT c.*, COUNT(s.id) AS shipment_count FROM customers c LEFT JOIN shipments s ON s.customer_id = c.id WHERE c.name LIKE CONCAT("%", ?, "%") OR c.company_name LIKE CONCAT("%", ?, "%") OR c.phone LIKE CONCAT("%", ?, "%") GROUP BY c.id ORDER BY c.created_at DESC LIMIT 200');
    $stmt->bind_param('sss', $search, $search, $search);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = db()->query('SELECT c.*, COUNT(s.id) AS shipment_count FROM customers c LEFT JOIN shipments s ON s.customer_id = c.id GROUP BY c.id ORDER BY c.created_at DESC LIMIT 200');
}
$clientCount = $customers->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients | Garamba Sky Cargo Admin</title>
    <style>
        :root { --ink: #1b2c38; --muted: #677985; --line: #dce4e9; --blue: #176c93; --navy: #102d3a; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: #f3f6f7; font: 14px/1.5 Segoe UI, Arial, sans-serif; }
        .wrap { width: min(1180px, calc(100% - 32px)); margin: 32px auto; }
        .topbar { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-bottom: 24px; }
        .topbar a, a { color: var(--blue); text-decoration: none; }
        .topbar form { margin-left: auto; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 22px; margin-bottom: 18px; }
        h1, h2 { margin: 0 0 14px; }
        h1 { font-size: 26px; }
        h2 { font-size: 17px; }
        .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        input, textarea, button { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 5px; font: inherit; }
        textarea { min-height: 72px; grid-column: 1 / -1; }
        button { width: auto; cursor: pointer; border: 0; background: var(--navy); color: #fff; font-weight: 700; }
        .search { display: flex; gap: 8px; margin-bottom: 15px; }
        .search input { flex: 1; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 11px 9px; border-bottom: 1px solid #e8edf0; text-align: left; }
        th { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
        .actions { display: flex; align-items: center; gap: 10px; }
        .actions form { margin: 0; }
        .delete { background: #fff; border: 1px solid #d9aaa5; padding: 7px 10px; color: #963a32; }
        .muted { color: var(--muted); }
        .page-head { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin: 20px 0; }
        .page-head h1 { margin: 0; }
        .count { color: var(--muted); font-size: 13px; }
        .flash { padding: 12px 14px; margin-bottom: 16px; border-radius: 5px; background: #eaf5ef; color: #286044; }
        .flash.error { background: #fff0ed; color: #8e3329; }
        .create-client { margin-bottom: 18px; background: #fff; border: 1px solid var(--line); border-radius: 8px; }
        .create-client summary { padding: 15px 18px; color: var(--blue); font-weight: 700; cursor: pointer; }
        .create-client[open] summary { border-bottom: 1px solid var(--line); }
        .create-form { padding: 20px; }
        .directory { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 20px; }
        .directory-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .directory-head h2 { margin: 0 0 14px; }
        .client-name { font-weight: 700; }
        @media (max-width: 640px) { .fields { grid-template-columns: 1fr; } textarea { grid-column: auto; } .search { flex-direction: row; } .topbar form { margin-left: 0; } .page-head { align-items: flex-start; flex-direction: column; } .actions { align-items: flex-start; flex-direction: column; } }
    </style>
    <link rel="stylesheet" href="<?= e(assetUrl('admin/admin-ui.css')); ?>">
</head>
<body>
<main class="wrap">
    <nav class="topbar" aria-label="Admin navigation">
        <a href="index.php">Dashboard</a>
        <a href="customers.php" aria-current="page">Clients</a>
        <a href="account.php">Account</a>
        <form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>"><button type="submit">Sign out</button></form>
    </nav>
    <div class="page-head">
        <h1>Clients</h1>
        <span class="count"><?= (int) $clientCount; ?> shown<?= $search !== '' ? ' for “' . e($search) . '”' : ''; ?></span>
    </div>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="flash" role="status">Client deleted. <?= (int) $_GET['deleted']; ?> linked shipment(s) and their history were also removed.</div>
    <?php elseif (($_GET['error'] ?? '') === 'delete_failed'): ?>
        <div class="flash error" role="alert">The client could not be deleted. No linked records were changed.</div>
    <?php elseif (($_GET['error'] ?? '') === 'not_found'): ?>
        <div class="flash error" role="alert">That client no longer exists.</div>
    <?php endif; ?>

    <details class="create-client">
        <summary>+ Add a client</summary>
        <div class="create-form">
        <form method="POST" action="save-customer.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <div class="fields">
                <div><label for="new_client_name">Client name</label><input id="new_client_name" name="name" required></div>
                <div><label for="new_company_name">Company</label><input id="new_company_name" name="company_name"></div>
                <div><label for="new_phone">Phone</label><input id="new_phone" name="phone" inputmode="tel"></div>
                <div><label for="new_import_permit">Import permit</label><input id="new_import_permit" name="import_permit"></div>
                <div><label for="new_website">Website</label><input id="new_website" type="url" name="website"></div>
                <div><label for="new_address">Address</label><textarea id="new_address" name="address"></textarea></div>
            </div>
            <button type="submit">Save client</button>
        </form>
        </div>
    </details>

    <section class="directory">
        <div class="directory-head"><h2>Client directory</h2></div>
        <form class="search" method="GET">
            <input name="q" value="<?= e($search); ?>" placeholder="Search name, company, or phone" aria-label="Search clients">
            <button type="submit">Search</button>
            <?php if ($search !== ''): ?><a href="customers.php">Clear</a><?php endif; ?>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Client</th><th>Company</th><th>Phone</th><th>Shipments</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($customers->num_rows === 0): ?>
                    <tr><td colspan="5" class="muted">No clients found.</td></tr>
                <?php endif; ?>
                <?php while ($customer = $customers->fetch_assoc()): ?>
                    <tr>
                        <td class="client-name"><?= e((string) $customer['name']); ?></td>
                        <td><?= e((string) $customer['company_name']); ?></td>
                        <td><?= e((string) $customer['phone']); ?></td>
                        <td><?= (int) $customer['shipment_count']; ?></td>
                        <td><div class="actions"><a href="customer.php?id=<?= (int) $customer['id']; ?>">View / edit</a><form method="POST" action="delete-customer.php" onsubmit="return confirm('Permanently delete this client and all <?= (int) $customer['shipment_count']; ?> linked shipment(s), timeline events, and warehouse records? This cannot be undone.')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>"><input type="hidden" name="customer_id" value="<?= (int) $customer['id']; ?>"><button class="delete" type="submit">Delete</button></form></div></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
