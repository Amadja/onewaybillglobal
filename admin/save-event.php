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
if ($shipmentId <= 0) {
    die('Shipment not found.');
}

$title = trim($_POST['title'] ?? '');
$location = trim($_POST['location'] ?? '');
$eventDate = trim($_POST['event_date'] ?? '');
$eventTime = trim($_POST['event_time'] ?? '');
$carrierName = trim($_POST['carrier_name'] ?? '');
$flightNumber = strtoupper(trim($_POST['flight_number'] ?? ''));
$description = trim($_POST['description'] ?? '');
$alertLevel = trim($_POST['alert_level'] ?? 'none');
$alertLevels = ['none', 'info', 'attention', 'warning', 'critical'];
$isAlert = $alertLevel !== 'none' ? 1 : 0;
$dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $eventDate . ' ' . $eventTime);

if ($title === '' || $location === '' || !$dateTime || $dateTime->format('Y-m-d H:i') !== $eventDate . ' ' . $eventTime || !in_array($alertLevel, $alertLevels, true)) {
    die('Enter a milestone, location, valid date, and time.');
}

if ($isAlert && $description === '') {
    die('Add a client-facing message for this alert.');
}

if ($isAlert) {
    die('Set customer alert severity in the preparation-stage customer alert editor.');
}

if (($carrierName !== '' || $flightNumber !== '') && $title !== 'Departed origin facility') {
    die('Airline and flight details belong to the Departed origin facility milestone.');
}
if (strlen($carrierName) > 120 || strlen($flightNumber) > 40) {
    die('Airline or flight number is too long.');
}

if ($title === 'Shipment received') {
    die('Shipment received is recorded automatically when the shipment is created.');
}

if ($title === 'Arrived at destination' && $isAlert) {
    die('Customer alerts for this journey must be attached to Preparing for international departure.');
}

$previousMilestone = [
    'Departed origin facility' => 'Shipment received',
    'Preparing for international departure' => 'Departed origin facility',
    'Arrived at destination' => 'Preparing for international departure',
];

if (isset($previousMilestone[$title])) {
    $previousTitle = $previousMilestone[$title];
    if ($title === 'Departed origin facility') {
        $previousStmt = db()->prepare("SELECT title, event_date, event_time FROM shipment_events WHERE shipment_id = ? AND title IN ('Shipment received', 'Goods received at warehouse') ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 1");
        $previousStmt->bind_param('i', $shipmentId);
    } else {
        $previousStmt = db()->prepare('SELECT title, event_date, event_time FROM shipment_events WHERE shipment_id = ? AND title = ? ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 1');
        $previousStmt->bind_param('is', $shipmentId, $previousTitle);
    }
    $previousStmt->execute();
    $previousEvent = $previousStmt->get_result()->fetch_assoc();
    $previousStmt->close();

    if (!$previousEvent) {
        die('Add the previous milestone first: ' . $previousTitle . '.');
    }
    $previousTitle = $previousEvent['title'];

    $duplicateStmt = db()->prepare('SELECT id FROM shipment_events WHERE shipment_id = ? AND title = ? LIMIT 1');
    $duplicateStmt->bind_param('is', $shipmentId, $title);
    $duplicateStmt->execute();
    $alreadyExists = (bool) $duplicateStmt->get_result()->fetch_assoc();
    $duplicateStmt->close();
    if ($alreadyExists) {
        die('This milestone has already been recorded.');
    }

    $previousDateTime = new DateTimeImmutable($previousEvent['event_date'] . ' ' . ($previousEvent['event_time'] ?: '00:00:00'));
    if ($dateTime < $previousDateTime) {
        die('The milestone date and time must follow ' . $previousTitle . '.');
    }
}

if ($title === 'Arrived at destination') {
    $destinationStmt = db()->prepare('SELECT destination_country, destination_location FROM shipments WHERE id = ?');
    $destinationStmt->bind_param('i', $shipmentId);
    $destinationStmt->execute();
    $destination = $destinationStmt->get_result()->fetch_assoc();
    $destinationStmt->close();

    if (!$destination || $destination['destination_country'] === 'To be determined' || trim($destination['destination_location']) === '') {
        die('Set the shipment destination before recording arrival.');
    }

    $location = $destination['destination_country'] === 'Warehouse'
        ? $destination['destination_location']
        : trim($destination['destination_location'] . ', ' . $destination['destination_country']);
}

if ($title === 'Collected by customer') {
    $warehouseStmt = db()->prepare('SELECT w.warehouse_name, w.warehouse_location, w.entry_date, w.entry_time, s.destination_country, s.current_status FROM warehouse_entries w JOIN shipments s ON s.id = w.shipment_id WHERE s.id = ? ORDER BY w.id DESC LIMIT 1');
    $warehouseStmt->bind_param('i', $shipmentId);
    $warehouseStmt->execute();
    $warehouse = $warehouseStmt->get_result()->fetch_assoc();
    $warehouseStmt->close();

    if (!$warehouse || $warehouse['destination_country'] !== 'Warehouse' || $warehouse['current_status'] !== 'Available for customer collection') {
        die('Choose warehouse collection before recording customer pickup.');
    }

    $entryAt = shipmentEventDateTime(['event_date' => $warehouse['entry_date'], 'event_time' => $warehouse['entry_time']]);
    if (!$entryAt || $dateTime < $entryAt) {
        die('Pickup date and time must follow the warehouse entry.');
    }

    $duplicateStmt = db()->prepare("SELECT id FROM shipment_events WHERE shipment_id = ? AND title = 'Collected by customer' LIMIT 1");
    $duplicateStmt->bind_param('i', $shipmentId);
    $duplicateStmt->execute();
    $alreadyCollected = (bool) $duplicateStmt->get_result()->fetch_assoc();
    $duplicateStmt->close();
    if ($alreadyCollected) {
        die('Customer pickup has already been recorded.');
    }

    $location = trim($warehouse['warehouse_name'] . ', ' . $warehouse['warehouse_location']);
}

$stmt = db()->prepare('INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, alert_level, description, carrier_name, flight_number) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)');
$stmt->bind_param('issssissss', $shipmentId, $title, $location, $eventDate, $eventTime, $isAlert, $alertLevel, $description, $carrierName, $flightNumber);

if ($stmt->execute()) {
    $eventId = $stmt->insert_id;
    $stmt->close();
    setLatestEventCurrent($shipmentId, $eventId);
    $status = trim($title);
    setShipmentCurrentStatus($shipmentId, $status);
    header('Location: shipment.php?id=' . $shipmentId);
    exit;
}

$stmt->close();
die('Unable to save event.');
