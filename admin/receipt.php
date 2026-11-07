<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$shipmentId = (int) ($_GET['id'] ?? 0);
if ($shipmentId <= 0) {
    http_response_code(400);
    die('Shipment not found.');
}

$stmt = db()->prepare('SELECT s.*, c.name AS customer_name, c.company_name, c.phone, c.address, c.import_permit FROM shipments s JOIN customers c ON c.id = s.customer_id WHERE s.id = ? LIMIT 1');
$stmt->bind_param('i', $shipmentId);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$shipment) {
    http_response_code(404);
    die('Shipment not found.');
}

$warehouseStmt = db()->prepare('SELECT warehouse_name, warehouse_location, entry_date, entry_time FROM warehouse_entries WHERE shipment_id = ? ORDER BY id DESC LIMIT 1');
$warehouseStmt->bind_param('i', $shipmentId);
$warehouseStmt->execute();
$warehouse = $warehouseStmt->get_result()->fetch_assoc();
$warehouseStmt->close();

$destination = $shipment['destination_country'] === 'Warehouse'
    ? (string) $shipment['destination_location']
    : trim(($shipment['destination_location'] ? $shipment['destination_location'] . ', ' : '') . $shipment['destination_country']);
if ($destination === 'To be determined') {
    $destination = 'To be determined';
}
$receiptDate = date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment Receipt <?= e((string) $shipment['tracking_number']); ?></title>
    <style>
        :root { --ink: #1b2c38; --muted: #657783; --line: #d8e1e6; --blue: #176c93; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: #eef2f3; font: 14px/1.5 Segoe UI, Arial, sans-serif; }
        .toolbar { width: min(820px, calc(100% - 32px)); margin: 20px auto 12px; display: flex; justify-content: space-between; }
        .toolbar a { color: var(--blue); text-decoration: none; }
        .toolbar button { border: 0; border-radius: 5px; padding: 10px 16px; background: #102d3a; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .receipt { width: min(820px, calc(100% - 32px)); margin: 0 auto 32px; padding: 48px; background: #fff; border: 1px solid var(--line); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 24px; border-bottom: 2px solid var(--ink); }
        .brand img { width: 210px; max-height: 64px; object-fit: contain; object-position: left center; }
        .brand p, .receipt-meta p { margin: 5px 0; color: var(--muted); font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 28px; }
        h2 { margin: 28px 0 12px; font-size: 14px; text-transform: uppercase; letter-spacing: .07em; }
        .receipt-meta { text-align: right; }
        .tracking { margin: 24px 0; padding: 17px 20px; background: #f0f5f6; border-left: 4px solid var(--blue); display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .tracking small, .detail small { display: block; color: var(--muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .tracking strong { font-size: 22px; }
        .barcode-wrap { width: min(54%, 390px); min-width: 240px; padding: 8px 12px; background: #fff; }
        #receiptBarcode { display: block; width: 100%; height: 72px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid var(--line); }
        .detail { min-height: 76px; padding: 13px 15px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); overflow-wrap: anywhere; }
        .detail:nth-child(2n) { border-right: 0; }
        .detail span { display: block; margin-top: 5px; font-weight: 600; }
        .note { margin-top: 28px; color: var(--muted); font-size: 12px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; margin-top: 58px; }
        .signature { padding-top: 8px; border-top: 1px solid #8a979e; color: var(--muted); font-size: 11px; }
        @media(max-width:600px) { .receipt { padding: 24px 18px; } .header { flex-direction: column; } .receipt-meta { text-align: left; } .tracking { align-items: flex-start; flex-direction: column; gap: 12px; } .barcode-wrap { width: 100%; min-width: 0; } .grid { grid-template-columns: 1fr; } .detail { border-right: 0; } .signatures { gap: 24px; } }
        @media print {
            @page { size: A4; margin: 14mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { width: 100%; margin: 0; padding: 0; border: 0; }
            .detail, .tracking { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <nav class="toolbar"><a href="shipment.php?id=<?= (int) $shipment['id']; ?>">Back to shipment</a><button type="button" onclick="window.print()">Print receipt</button></nav>
    <main class="receipt">
        <header class="header">
            <div class="brand">
                <img src="<?= e(siteAssetUrl('logogaramba.png')); ?>" alt="Garamba Sky Cargo Ltd">
                <p>Entebbe, Uganda · 0200 444 231</p>
                <p>info@garambacargo.com · garambacargo.com</p>
            </div>
            <div class="receipt-meta">
                <h1>Shipment Receipt</h1>
                <p>Receipt date: <?= e($receiptDate); ?></p>
                <p>Status: <?= e((string) $shipment['current_status']); ?></p>
            </div>
        </header>

        <div class="tracking">
            <div><small>Tracking reference</small><strong><?= e((string) $shipment['tracking_number']); ?></strong></div>
            <div class="barcode-wrap"><svg id="receiptBarcode" role="img" aria-label="Barcode for shipment tracking number <?= e((string) $shipment['tracking_number']); ?>"></svg></div>
        </div>

        <h2>Client</h2>
        <div class="grid">
            <div class="detail"><small>Name</small><span><?= e((string) $shipment['customer_name']); ?></span></div>
            <div class="detail"><small>Company</small><span><?= e((string) $shipment['company_name']); ?></span></div>
            <div class="detail"><small>Phone</small><span><?= e((string) $shipment['phone']); ?></span></div>
            <div class="detail"><small>Import permit</small><span><?= e((string) $shipment['import_permit']); ?></span></div>
            <div class="detail"><small>Address</small><span><?= e((string) $shipment['address']); ?></span></div>
        </div>

        <h2>Shipment details</h2>
        <div class="grid">
            <div class="detail"><small>Service</small><span><?= e((string) $shipment['service_type']); ?></span></div>
            <div class="detail"><small>Package</small><span><?= e((string) $shipment['package_description']); ?></span></div>
            <div class="detail"><small>Weight</small><span><?= e((string) $shipment['package_weight_kg']); ?> kg</span></div>
            <div class="detail"><small>Origin</small><span><?= e(trim(($shipment['origin_location'] ? $shipment['origin_location'] . ', ' : '') . $shipment['origin_country'])); ?></span></div>
            <div class="detail"><small>Destination</small><span><?= e($destination); ?></span></div>
            <div class="detail"><small>Shipment created</small><span><?= e(date('d M Y H:i', strtotime($shipment['created_at']))); ?></span></div>
            <?php if ($warehouse): ?>
                <div class="detail"><small>Warehouse received</small><span><?= e(trim($warehouse['warehouse_name'] . ', ' . $warehouse['warehouse_location'])); ?></span></div>
                <div class="detail"><small>Warehouse entry date/time</small><span><?= e(date('d M Y', strtotime($warehouse['entry_date'])) . ' ' . substr((string) $warehouse['entry_time'], 0, 5)); ?></span></div>
            <?php endif; ?>
        </div>

        <p class="note">This receipt confirms that the shipment was recorded by Garamba Sky Cargo Ltd. It is not proof of payment.</p>
        <div class="signatures"><div class="signature">Received by</div><div class="signature">Client acknowledgment</div></div>
    </main>
    <script src="<?= e(siteAssetUrl('JsBarcode.all.min.js')); ?>"></script>
    <script>
        if (window.JsBarcode) {
            window.JsBarcode('#receiptBarcode', <?= json_encode((string) $shipment['tracking_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, {
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
