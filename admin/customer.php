<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$customerId = (int) ($_GET['id'] ?? 0);
if ($customerId <= 0) {
    http_response_code(400);
    die('Client not found.');
}

$stmt = db()->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) {
    http_response_code(404);
    die('Client not found.');
}

$shipmentStmt = db()->prepare('SELECT id, tracking_number, current_status, destination_country, destination_location, created_at FROM shipments WHERE customer_id = ? ORDER BY created_at DESC');
$shipmentStmt->bind_param('i', $customerId);
$shipmentStmt->execute();
$shipments = $shipmentStmt->get_result();
$shipmentCount = $shipments->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e((string) $customer['name']); ?> | Client</title>
    <style>
        :root { --ink: #1b2c38; --muted: #677985; --line: #dce4e9; --blue: #176c93; --navy: #102d3a; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: #f3f6f7; font: 14px/1.5 Segoe UI, Arial, sans-serif; }
        .wrap { width: min(980px, calc(100% - 32px)); margin: 32px auto; }
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
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 9px; border-bottom: 1px solid #e8edf0; text-align: left; }
        th { color: var(--muted); font-size: 11px; text-transform: uppercase; }
        .table-wrap { overflow-x: auto; }
        .danger-zone { border-color: #e6c4c0; }
        .danger-zone p { color: #7c4842; }
        .delete-button { background: #963a32; }
        .muted { color: var(--muted); }
        @media (max-width: 640px) { .fields { grid-template-columns: 1fr; } textarea { grid-column: auto; } .topbar form { margin-left: 0; } }
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
        <h1>Client profile</h1>
        <p><strong><?= e((string) $customer['name']); ?></strong><?php if ($customer['company_name']): ?> · <?= e((string) $customer['company_name']); ?><?php endif; ?></p>
        <form method="POST" action="save-customer.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="customer_id" value="<?= (int) $customer['id']; ?>">
            <div class="fields">
                <div><label for="client_name">Client name</label><input id="client_name" name="name" value="<?= e((string) $customer['name']); ?>" required></div>
                <div><label for="company_name">Company</label><input id="company_name" name="company_name" value="<?= e((string) $customer['company_name']); ?>"></div>
                <div><label for="client_phone">Phone</label><input id="client_phone" name="phone" value="<?= e((string) $customer['phone']); ?>" inputmode="tel"></div>
                <div><label for="import_permit">Import permit</label><input id="import_permit" name="import_permit" value="<?= e((string) $customer['import_permit']); ?>"></div>
                <div><label for="client_website">Website</label><input id="client_website" type="url" name="website" value="<?= e((string) $customer['website']); ?>"></div>
                <div><label for="client_address">Address</label><textarea id="client_address" name="address"><?= e((string) $customer['address']); ?></textarea></div>
            </div>
            <button type="submit">Save changes</button>
        </form>
    </section>
    <section class="panel">
        <h2>Shipment history</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Tracking</th><th>Status</th><th>Destination</th><th>Created</th></tr></thead>
            <tbody>
            <?php if ($shipments->num_rows === 0): ?><tr><td colspan="4" class="muted">No shipments for this client.</td></tr><?php endif; ?>
            <?php while ($shipment = $shipments->fetch_assoc()): ?>
                <?php $destination = $shipment['destination_country'] === 'Warehouse' ? $shipment['destination_location'] : trim(($shipment['destination_location'] ? $shipment['destination_location'] . ', ' : '') . $shipment['destination_country']); ?>
                <tr><td><a href="shipment.php?id=<?= (int) $shipment['id']; ?>"><?= e((string) $shipment['tracking_number']); ?></a></td><td><?= e((string) $shipment['current_status']); ?></td><td><?= e((string) $destination); ?></td><td><?= e((string) $shipment['created_at']); ?></td></tr>
            <?php endwhile; ?>
            </tbody>
        </table></div>
    </section>
    <section class="panel danger-zone">
        <h2>Delete client</h2>
        <p>This permanently deletes this client and <?= (int) $shipmentCount; ?> linked shipment(s), including their tracking history, charges, and warehouse records.</p>
        <form method="POST" action="delete-customer.php" onsubmit="return confirm('Permanently delete this client and all linked shipment data? This cannot be undone.')">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="customer_id" value="<?= (int) $customer['id']; ?>">
            <button class="delete-button" type="submit">Delete client and linked records</button>
        </form>
    </section>
</main>
</body>
</html>
