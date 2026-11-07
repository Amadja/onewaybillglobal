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
$decision = $_POST['decision'] ?? '';
if ($shipmentId <= 0 || !in_array($decision, ['continue', 'warehouse_collection'], true)) {
    die('Invalid shipment route decision.');
}

$warehouseStmt = db()->prepare('SELECT warehouse_name, warehouse_location FROM warehouse_entries WHERE shipment_id = ? ORDER BY id DESC LIMIT 1');
$warehouseStmt->bind_param('i', $shipmentId);
$warehouseStmt->execute();
$warehouse = $warehouseStmt->get_result()->fetch_assoc();
$warehouseStmt->close();

if (!$warehouse) {
    die('Record the warehouse entry before choosing the next step.');
}

$warehouseLocation = trim(implode(', ', array_filter([$warehouse['warehouse_name'], $warehouse['warehouse_location']])));
if ($decision === 'continue') {
    $destinationCountry = trim($_POST['destination_country'] ?? '');
    $destinationLocation = trim($_POST['destination_location'] ?? '');
    if ($destinationCountry === '' || $destinationLocation === '') {
        die('Enter both a destination country and destination location.');
    }

    $eventTitle = null;
    $eventDescription = null;
} else {
    $destinationCountry = 'Warehouse';
    $destinationLocation = $warehouseLocation;
    $eventTitle = 'Available for customer collection';
    $eventDescription = 'The warehouse is the final destination. The shipment is awaiting collection by the customer.';
}

$connection = db();
$connection->begin_transaction();

try {
    $destinationStmt = $connection->prepare('UPDATE shipments SET destination_country = ?, destination_location = ? WHERE id = ?');
    $destinationStmt->bind_param('ssi', $destinationCountry, $destinationLocation, $shipmentId);
    $destinationStmt->execute();
    $destinationStmt->close();

    if ($decision === 'warehouse_collection') {
        $eventDate = date('Y-m-d');
        $eventTime = date('H:i:s');
        $eventStmt = $connection->prepare('INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, description) VALUES (?, ?, ?, ?, ?, 0, 0, ?)');
        $eventStmt->bind_param('isssss', $shipmentId, $eventTitle, $warehouseLocation, $eventDate, $eventTime, $eventDescription);
        $eventStmt->execute();
        $eventId = $eventStmt->insert_id;
        $eventStmt->close();

        setLatestEventCurrent($shipmentId, $eventId);
        setShipmentCurrentStatus($shipmentId, $eventTitle);
    } else {
        $latestMilestoneStmt = $connection->prepare("SELECT id, title FROM shipment_events WHERE shipment_id = ? AND title IN ('Shipment received', 'Goods received at warehouse', 'Departed origin facility', 'Preparing for international departure', 'Arrived at destination') ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 1");
        $latestMilestoneStmt->bind_param('i', $shipmentId);
        $latestMilestoneStmt->execute();
        $latestMilestone = $latestMilestoneStmt->get_result()->fetch_assoc();
        $latestMilestoneStmt->close();

        if ($latestMilestone) {
            setLatestEventCurrent($shipmentId, (int) $latestMilestone['id']);
        }
        setShipmentCurrentStatus($shipmentId, 'Route assigned');
    }
    $connection->commit();
} catch (Throwable $exception) {
    $connection->rollback();
    error_log($exception->getMessage());
    die('Unable to save the shipment route decision.');
}

header('Location: shipment.php?id=' . $shipmentId);
exit;
