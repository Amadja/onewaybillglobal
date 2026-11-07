<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
requireCsrfToken();

$customerId = (int) ($_POST['customer_id'] ?? 0);
if ($customerId <= 0) {
    die('Customer is required.');
}

$originCountry = trim($_POST['origin_country'] ?? '');
$destinationCountry = trim($_POST['destination_country'] ?? '') ?: 'To be determined';
$originLocation = trim($_POST['origin_location'] ?? '');
$destinationLocation = trim($_POST['destination_location'] ?? '');
$receivedLocation = trim($_POST['shipment_received_location'] ?? '') ?: 'Warehouse, Entebbe, Uganda';
$serviceType = trim($_POST['service_type'] ?? '');
$packageDescription = trim($_POST['package_description'] ?? '');
$packageWeight = (float) ($_POST['package_weight_kg'] ?? 0);

if ($originCountry === '' || $serviceType === '' || $packageWeight <= 0) {
    die('Origin, service type, and a package weight greater than zero are required.');
}

$trackingNumber = generateTrackingNumber($originCountry, $destinationCountry);

$stmt = db()->prepare('INSERT INTO shipments (tracking_number, customer_id, origin_country, destination_country, origin_location, destination_location, service_type, package_description, package_weight_kg, current_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param(
    'sissssssds',
    $trackingNumber,
    $customerId,
    $originCountry,
    $destinationCountry,
    $originLocation,
    $destinationLocation,
    $serviceType,
    $packageDescription,
    $packageWeight,
    $status
);
$status = 'Shipment received';

if ($stmt->execute()) {
    $shipmentId = $stmt->insert_id;

    $eventStmt = db()->prepare('INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, description) VALUES (?, ?, ?, ?, ?, 1, 0, ?)');
    $title = 'Shipment received';
    $location = $receivedLocation;
    $eventDate = date('Y-m-d');
    $eventTime = date('H:i:s');
    $description = 'Shipment has been recorded in the system.';
    $eventStmt->bind_param('isssss', $shipmentId, $title, $location, $eventDate, $eventTime, $description);
    $eventStmt->execute();
    $eventStmt->close();

    $stmt->close();
    header('Location: shipment.php?id=' . $shipmentId);
    exit;
}

$stmt->close();
die('Unable to save shipment.');
