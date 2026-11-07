<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
requireCsrfToken();

$shipmentId = (int) ($_POST['shipment_id'] ?? 0);
$alertLevel = trim($_POST['alert_level'] ?? 'none');
$description = trim($_POST['description'] ?? '');
$allowedLevels = ['none', 'info', 'attention', 'warning', 'critical'];
if ($shipmentId <= 0 || !in_array($alertLevel, $allowedLevels, true)) {
    die('Select a valid preparation alert severity.');
}
if ($alertLevel !== 'none' && $description === '') {
    die('Add a client-facing message for this alert.');
}
if ($alertLevel === 'none') {
    $description = '';
}

$eventStmt = db()->prepare("SELECT id FROM shipment_events WHERE shipment_id = ? AND title = 'Preparing for international departure' ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 1");
$eventStmt->bind_param('i', $shipmentId);
$eventStmt->execute();
$preparationEvent = $eventStmt->get_result()->fetch_assoc();
$eventStmt->close();
if (!$preparationEvent) {
    die('Record Preparing for international departure before adding its alert.');
}

$isAlert = $alertLevel === 'none' ? 0 : 1;
$update = db()->prepare('UPDATE shipment_events SET is_alert = ?, alert_level = ?, description = ? WHERE id = ? AND shipment_id = ?');
$update->bind_param('issii', $isAlert, $alertLevel, $description, $preparationEvent['id'], $shipmentId);
$update->execute();
$update->close();

header('Location: shipment.php?id=' . $shipmentId . '&preparation_alert_saved=1');
exit;
