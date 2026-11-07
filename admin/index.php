<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$customers = db()->query('SELECT * FROM customers ORDER BY created_at DESC LIMIT 50');
$shipments = db()->query('SELECT s.*, c.name AS customer_name, w.warehouse_name AS invoice_warehouse_name, w.warehouse_location AS invoice_warehouse_location, w.entry_date AS invoice_entry_date, w.entry_time AS invoice_entry_time, w.free_storage_days AS invoice_free_days, w.daily_rate_per_kg_usd AS invoice_daily_rate FROM shipments s JOIN customers c ON c.id = s.customer_id LEFT JOIN warehouse_entries w ON w.id = (SELECT MAX(w2.id) FROM warehouse_entries w2 WHERE w2.shipment_id = s.id) ORDER BY s.created_at DESC LIMIT 50');
$clientCount = (int) db()->query('SELECT COUNT(*) AS total FROM customers')->fetch_assoc()['total'];
$shipmentCount = (int) db()->query('SELECT COUNT(*) AS total FROM shipments')->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 0; background: #f3f6f7; color: #1b2c38; }
        .wrap { max-width: 1200px; margin: 32px auto; padding: 24px; }
        .box { background: #fff; padding: 22px; border: 1px solid #dce4e9; border-radius: 8px; margin-bottom: 18px; }
        h1, h2 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        form { display: grid; gap: 12px; }
        input, select, textarea { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid #d9e3ee; border-radius: 5px; font: inherit; }
        button { background: #102d3a; color: white; border: 0; border-radius: 5px; padding: 11px 16px; cursor: pointer; font: inherit; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e9eef5; }
        a { color: #0d6eb8; }
        .topbar { display: flex; align-items: center; gap: 18px; margin: 16px 0 24px; }
        .topbar form { margin-left: auto; }
        .topbar button { width: auto; padding: 9px 14px; }
        .table-wrap { overflow-x: auto; }
        .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 20px 0; }
        .stat { padding: 16px 18px; background: #eaf1f3; border-left: 3px solid #176c93; }
        .stat strong { display: block; font-size: 24px; }
        .stat span { color: #677985; font-size: 12px; }
        .field label { display: block; margin-bottom: 5px; color: #526570; font-size: 12px; font-weight: 700; }
        .table-wrap { overflow-x: auto; }
        @media (max-width: 700px) { .grid, .stats { grid-template-columns: 1fr; } .topbar { flex-wrap: wrap; } .topbar form { margin-left: 0; } }
    </style>
    <link rel="stylesheet" href="<?= e(assetUrl('admin/admin-ui.css')); ?>">
</head>
<body>
    <div class="wrap">
        <h1>Operations dashboard</h1>
        <nav class="topbar" aria-label="Admin navigation">
            <a href="index.php">Dashboard</a>
            <a href="customers.php">Clients</a>
            <a href="account.php">Account</a>
            <form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>"><button type="submit">Sign out</button></form>
        </nav>

        <div class="stats" aria-label="Operations summary">
            <div class="stat"><strong><?= $clientCount; ?></strong><span>Clients</span></div>
            <div class="stat"><strong><?= $shipmentCount; ?></strong><span>Shipments</span></div>
        </div>

        <div class="box">
            <h2>Client records</h2>
            <p>Add clients, review shipment history, update contact details, or remove a client and its linked shipment records.</p>
            <a href="customers.php">Open client manager</a>
        </div>

        <div class="box">
            <h2>Add Shipment</h2>
            <form method="POST" action="save-shipment.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <div class="grid">
                    <div class="field"><label for="customer_id">Client</label><select id="customer_id" name="customer_id" required>
                        <option value="">Select customer</option>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option value="<?= (int) $customer['id']; ?>"><?= e($customer['name']); ?></option>
                        <?php endwhile; ?>
                    </select></div>
                    <div class="field"><label for="service_type">Service type</label><input id="service_type" type="text" name="service_type" required></div>
                    <div class="field"><label for="origin_country">Origin country</label><input id="origin_country" type="text" name="origin_country" required></div>
                    <div class="field"><label for="destination_country">Destination country <span>(optional if undecided)</span></label><input id="destination_country" type="text" name="destination_country"></div>
                    <div class="field"><label for="origin_location">Origin location</label><input id="origin_location" type="text" name="origin_location"></div>
                    <div class="field"><label for="destination_location">Destination location <span>(optional)</span></label><input id="destination_location" type="text" name="destination_location"></div>
                    <div class="field"><label for="shipment_received_location">Received at</label><input id="shipment_received_location" type="text" name="shipment_received_location" value="Warehouse, Entebbe, Uganda"></div>
                    <div class="field"><label for="package_description">Package description</label><input id="package_description" type="text" name="package_description"></div>
                    <div class="field"><label for="package_weight_kg">Package weight (kg)</label><input id="package_weight_kg" type="number" step="0.01" name="package_weight_kg" min="0.01" required></div>
                </div>
                <button type="submit">Create Shipment</button>
            </form>
        </div>

        <div class="box">
            <h2>Recent Shipments</h2>
            <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Tracking</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($shipment = $shipments->fetch_assoc()): ?>
                    <?php
                    $dashboardInvoiceAmount = 0.0;
                    if ($shipment['invoice_entry_date']) {
                        $dashboardEntryAt = shipmentEventDateTime(['event_date' => $shipment['invoice_entry_date'], 'event_time' => $shipment['invoice_entry_time']]);
                        $dashboardExitEvent = null;
                        $dashboardExitQuery = db()->prepare("SELECT title, event_date, event_time FROM shipment_events WHERE shipment_id = ? AND title IN ('Departed origin facility', 'Collected by customer') ORDER BY event_date ASC, event_time ASC, id ASC");
                        $dashboardExitQuery->bind_param('i', $shipment['id']);
                        $dashboardExitQuery->execute();
                        $dashboardExitRows = $dashboardExitQuery->get_result();
                        while ($dashboardExit = $dashboardExitRows->fetch_assoc()) {
                            $dashboardExitAt = shipmentEventDateTime($dashboardExit);
                            if ($dashboardEntryAt && $dashboardExitAt && $dashboardExitAt >= $dashboardEntryAt && $dashboardExitAt <= new DateTimeImmutable()) {
                                $dashboardExitEvent = $dashboardExit;
                                break;
                            }
                        }
                        $dashboardExitQuery->close();
                        $dashboardStorage = getWarehouseStorageSnapshot(
                            (float) $shipment['package_weight_kg'],
                            (string) $shipment['invoice_entry_date'],
                            $shipment['invoice_entry_time'],
                            (int) $shipment['invoice_free_days'],
                            (float) $shipment['invoice_daily_rate'],
                            $dashboardExitEvent['event_date'] ?? null,
                            $dashboardExitEvent['event_time'] ?? null
                        );
                        $dashboardInvoiceAmount = $dashboardStorage['storage_charge_usd'];
                    }
                    ?>
                    <tr>
                        <td><a href="shipment.php?id=<?= (int) $shipment['id']; ?>"><?= e($shipment['tracking_number']); ?></a></td>
                        <td><?= e($shipment['customer_name']); ?></td>
                        <td><?= e($shipment['service_type']); ?></td>
                        <td><?= e($shipment['current_status']); ?></td>
                        <td><div class="table-actions">
                            <a class="button-link" href="shipment.php?id=<?= (int) $shipment['id']; ?>">Open</a>
                            <a class="button-link secondary" href="receipt.php?id=<?= (int) $shipment['id']; ?>" target="_blank" rel="noopener">Receipt</a>
                            <?php if ($dashboardInvoiceAmount > 0): ?>
                                <a class="button-link secondary" href="invoice.php?id=<?= (int) $shipment['id']; ?>" target="_blank" rel="noopener">Invoice</a>
                            <?php else: ?>
                                <span class="disabled-action" title="No warehouse fee has accrued">Invoice</span>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>
