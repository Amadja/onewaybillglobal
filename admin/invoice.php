<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$shipmentId = (int) ($_GET['id'] ?? 0);
if ($shipmentId <= 0) {
    http_response_code(400);
    die('Shipment not found.');
}

$stmt = db()->prepare('SELECT s.*, c.name AS customer_name, c.company_name, c.phone, c.address FROM shipments s JOIN customers c ON c.id = s.customer_id WHERE s.id = ? LIMIT 1');
$stmt->bind_param('i', $shipmentId);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$shipment) {
    http_response_code(404);
    die('Shipment not found.');
}

$warehouseStmt = db()->prepare('SELECT warehouse_name, warehouse_location, entry_date, entry_time, free_storage_days, daily_rate_per_kg_usd FROM warehouse_entries WHERE shipment_id = ? ORDER BY id DESC LIMIT 1');
$warehouseStmt->bind_param('i', $shipmentId);
$warehouseStmt->execute();
$warehouse = $warehouseStmt->get_result()->fetch_assoc();
$warehouseStmt->close();
if (!$warehouse) {
    http_response_code(409);
    die('This shipment has no warehouse entry to invoice.');
}

$eventsStmt = db()->prepare("SELECT title, event_date, event_time FROM shipment_events WHERE shipment_id = ? AND title IN ('Departed origin facility', 'Preparing for international departure', 'Arrived at destination', 'Collected by customer') ORDER BY event_date ASC, event_time ASC, id ASC");
$eventsStmt->bind_param('i', $shipmentId);
$eventsStmt->execute();
$events = $eventsStmt->get_result();
$entryAt = shipmentEventDateTime(['event_date' => $warehouse['entry_date'], 'event_time' => $warehouse['entry_time']]);
$exitEvent = null;
$exitAt = null;
while ($event = $events->fetch_assoc()) {
    $eventAt = shipmentEventDateTime($event);
    if ($entryAt && $eventAt && $eventAt >= $entryAt && $eventAt <= new DateTimeImmutable()) {
        $exitEvent = $event;
        $exitAt = $eventAt;
        break;
    }
}
$eventsStmt->close();

$storage = getWarehouseStorageSnapshot(
    (float) $shipment['package_weight_kg'],
    (string) $warehouse['entry_date'],
    $warehouse['entry_time'],
    (int) $warehouse['free_storage_days'],
    (float) $warehouse['daily_rate_per_kg_usd'],
    $exitEvent['event_date'] ?? null,
    $exitEvent['event_time'] ?? null
);
if ($storage['storage_charge_usd'] <= 0) {
    http_response_code(409);
    die('No warehouse fee has accrued for this shipment, so there is no invoice to print.');
}

$warehouseName = trim(implode(', ', array_filter([$warehouse['warehouse_name'], $warehouse['warehouse_location']])));
$entryAt = $entryAt ?: new DateTimeImmutable($warehouse['entry_date']);
$periodEnd = $exitAt ?: new DateTimeImmutable();
$invoiceNumber = 'GSC-INV-' . $shipment['tracking_number'];
$invoiceDate = new DateTimeImmutable();
$dailyRate = (float) $warehouse['daily_rate_per_kg_usd'];
$weightKg = (float) $shipment['package_weight_kg'];
$chargeableDays = (int) $storage['chargeable_days'];
$amountDue = (float) $storage['storage_charge_usd'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Invoice <?= e($shipment['tracking_number']); ?></title>
    <style>
        :root { --ink: #1b2c38; --muted: #657783; --line: #d8e1e6; --blue: #176c93; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: #eef2f3; font: 14px/1.5 Segoe UI, Arial, sans-serif; }
        .toolbar { width: min(820px, calc(100% - 32px)); margin: 20px auto 12px; display: flex; justify-content: space-between; }
        .toolbar a { color: var(--blue); text-decoration: none; }
        .toolbar button { border: 0; border-radius: 5px; padding: 10px 16px; background: #102d3a; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .invoice { width: min(820px, calc(100% - 32px)); margin: 0 auto 32px; padding: 48px; background: #fff; border: 1px solid var(--line); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 24px; border-bottom: 2px solid var(--ink); }
        .brand img { width: 210px; max-height: 64px; object-fit: contain; object-position: left center; }
        .brand p, .invoice-meta p { margin: 5px 0; color: var(--muted); font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 28px; }
        h2 { margin: 28px 0 12px; font-size: 13px; text-transform: uppercase; }
        .invoice-meta { text-align: right; }
        .reference { margin: 24px 0; padding: 17px 20px; background: #f0f5f6; border-left: 4px solid var(--blue); display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .reference small, .detail small { display: block; color: var(--muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; }
        .reference strong { font-size: 19px; }
        .barcode-wrap { width: min(54%, 390px); min-width: 240px; padding: 8px 12px; background: #fff; }
        #invoiceBarcode { display: block; width: 100%; height: 72px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid var(--line); }
        .detail { min-height: 68px; padding: 12px 14px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); overflow-wrap: anywhere; }
        .detail:nth-child(2n) { border-right: 0; }
        .detail span { display: block; margin-top: 5px; font-weight: 600; }
        table { width: 100%; margin-top: 25px; border-collapse: collapse; }
        th, td { padding: 12px 9px; border-bottom: 1px solid var(--line); text-align: left; }
        th { color: var(--muted); font-size: 10px; text-transform: uppercase; }
        .numeric { text-align: right; white-space: nowrap; }
        .total { margin-left: auto; width: min(320px, 100%); padding-top: 14px; text-align: right; }
        .total small { display: block; color: var(--muted); font-size: 11px; text-transform: uppercase; }
        .total strong { display: block; font-size: 27px; }
        .note { margin-top: 28px; color: var(--muted); font-size: 12px; }
        @media(max-width:600px) {
            .invoice { padding: 24px 18px; }
            .header { flex-direction: column; }
            .invoice-meta { text-align: left; }
            .reference { align-items: flex-start; flex-direction: column; gap: 12px; }
            .barcode-wrap { width: 100%; min-width: 0; }
            .grid { grid-template-columns: 1fr; }
            .detail { border-right: 0; }
            table, tbody { display: block; width: 100%; }
            thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
            tbody tr { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0 12px; width: 100%; }
            td { min-width: 0; padding: 9px 4px; overflow-wrap: anywhere; white-space: normal; text-align: left; }
            td::before { content: attr(data-label); display: block; margin-bottom: 3px; color: var(--muted); font-size: 9px; font-weight: 700; text-transform: uppercase; }
            td:first-child, td:last-child { grid-column: 1 / -1; }
            td:last-child { display: flex; align-items: center; justify-content: space-between; gap: 12px; text-align: right; font-weight: 700; }
            td:last-child::before { margin: 0; }
            .numeric { white-space: normal; }
            table { font-size: 12px; }
        }
        @media print { @page { size: A4; margin: 14mm; } body { background: #fff; } .toolbar { display: none; } .invoice { width: 100%; margin: 0; padding: 0; border: 0; } .reference, table, .total { break-inside: avoid; } }
    </style>
</head>
<body>
    <nav class="toolbar"><a href="shipment.php?id=<?= (int) $shipment['id']; ?>">Back to shipment</a><button type="button" onclick="window.print()">Print invoice</button></nav>
    <main class="invoice">
        <header class="header">
            <div class="brand">
                <img src="<?= e(siteAssetUrl('logogaramba.png')); ?>" alt="Garamba Sky Cargo Ltd">
                <p>Entebbe, Uganda · 0200 444 231</p>
                <p>info@garambacargo.com · garambacargo.com</p>
            </div>
            <div class="invoice-meta">
                <h1>Warehouse Invoice</h1>
                <p>Invoice: <?= e($invoiceNumber); ?></p>
                <p>Issued: <?= e($invoiceDate->format('d M Y')); ?></p>
                <p>Amount due · USD</p>
            </div>
        </header>

        <div class="reference">
            <div><small>Tracking reference</small><strong><?= e((string) $shipment['tracking_number']); ?></strong></div>
            <div class="barcode-wrap"><svg id="invoiceBarcode" role="img" aria-label="Barcode for shipment tracking number <?= e((string) $shipment['tracking_number']); ?>"></svg></div>
        </div>

        <h2>Bill to</h2>
        <div class="grid">
            <div class="detail"><small>Client</small><span><?= e((string) $shipment['customer_name']); ?></span></div>
            <div class="detail"><small>Company</small><span><?= e((string) $shipment['company_name']); ?></span></div>
            <div class="detail"><small>Phone</small><span><?= e((string) $shipment['phone']); ?></span></div>
            <div class="detail"><small>Address</small><span><?= e((string) $shipment['address']); ?></span></div>
        </div>

        <h2>Storage charge</h2>
        <div class="grid">
            <div class="detail"><small>Warehouse</small><span><?= e($warehouseName); ?></span></div>
            <div class="detail"><small>Billing period</small><span><?= e($entryAt->format('d M Y H:i')); ?> – <?= e($periodEnd->format('d M Y H:i')); ?></span></div>
            <div class="detail"><small>Elapsed warehouse days</small><span><?= (int) $storage['days_in_warehouse']; ?> days</span></div>
            <div class="detail"><small>Free days</small><span><?= (int) $warehouse['free_storage_days']; ?> days</span></div>
        </div>

        <table>
            <thead><tr><th>Description</th><th class="numeric">Qty</th><th class="numeric">Rate</th><th class="numeric">Amount</th></tr></thead>
            <tbody><tr>
                <td data-label="Description">Warehouse storage · <?= e((string) $shipment['package_description']); ?></td>
                <td class="numeric" data-label="Quantity"><?= $chargeableDays; ?> days × <?= number_format($weightKg, 2); ?> kg</td>
                <td class="numeric" data-label="Rate"><?= formatMoney($dailyRate); ?>/kg/day</td>
                <td class="numeric" data-label="Amount"><?= formatMoney($amountDue); ?></td>
            </tr></tbody>
        </table>
        <div class="total"><small>Total due · USD</small><strong><?= formatMoney($amountDue); ?></strong></div>
        <p class="note">Calculation: max((<?= (int) $storage['days_in_warehouse']; ?> elapsed days − <?= (int) $warehouse['free_storage_days']; ?> free days), 0) × <?= number_format($weightKg, 2); ?> kg × <?= formatMoney($dailyRate); ?>/kg/day. This invoice is not a payment receipt.</p>
    </main>
    <script src="<?= e(siteAssetUrl('JsBarcode.all.min.js')); ?>"></script>
    <script>
        if (window.JsBarcode) {
            window.JsBarcode('#invoiceBarcode', <?= json_encode((string) $shipment['tracking_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, {
                format: 'CODE128',
                displayValue: false,
                height: 64,
                width: 2,
                margin: 14,
                background: '#ffffff',
                lineColor: '#111111'
            });
        }
    </script>
</body>
</html>
