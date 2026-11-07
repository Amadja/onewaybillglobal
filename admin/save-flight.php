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
$eventId = (int) ($_POST['event_id'] ?? 0);
$carrierName = trim($_POST['carrier_name'] ?? '');
$flightNumber = strtoupper(trim($_POST['flight_number'] ?? ''));
if ($shipmentId <= 0 || $eventId <= 0 || strlen($carrierName) > 120 || strlen($flightNumber) > 40) {
    die('Enter valid flight details.');
}

$eventStmt = db()->prepare("SELECT id FROM shipment_events WHERE id = ? AND shipment_id = ? AND title = 'Departed origin facility' LIMIT 1");
$eventStmt->bind_param('ii', $eventId, $shipmentId);
$eventStmt->execute();
$eventExists = (bool) $eventStmt->get_result()->fetch_assoc();
$eventStmt->close();
if (!$eventExists) {
    die('Departure milestone not found.');
}

$update = db()->prepare('UPDATE shipment_events SET carrier_name = ?, flight_number = ? WHERE id = ? AND shipment_id = ?');
$update->bind_param('ssii', $carrierName, $flightNumber, $eventId, $shipmentId);
$update->execute();
$update->close();

header('Location: shipment.php?id=' . $shipmentId . '&flight_saved=1');
exit;
