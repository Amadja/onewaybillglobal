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

$warehouseName = trim($_POST['warehouse_name'] ?? '');
$warehouseLocation = trim($_POST['warehouse_location'] ?? '');
$entryDate = trim($_POST['entry_date'] ?? '');
$entryTime = trim($_POST['entry_time'] ?? '');
$warehouseExitMode = trim($_POST['warehouse_exit_mode'] ?? 'current');
$exitDate = trim($_POST['exit_date'] ?? '');
$exitTime = trim($_POST['exit_time'] ?? '');
$departureLocation = trim($_POST['departure_location'] ?? 'EBB Airport, Uganda');
$carrierName = trim($_POST['carrier_name'] ?? '');
$flightNumber = strtoupper(trim($_POST['flight_number'] ?? ''));
$freeStorageDaysInput = (string) ($_POST['free_storage_days'] ?? '');
$dailyRateInput = trim((string) ($_POST['daily_rate_per_kg_usd'] ?? ''));
$entryDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $entryDate);
$entryTimeObject = DateTimeImmutable::createFromFormat('!H:i', $entryTime);

if ($warehouseName === '' || $warehouseLocation === '' || !$entryDateObject || $entryDateObject->format('Y-m-d') !== $entryDate || $entryDate > date('Y-m-d') || !$entryTimeObject || $entryTimeObject->format('H:i') !== $entryTime) {
    die('Enter valid warehouse details and an entry date/time that is not in the future.');
}

if (!preg_match('/^\d+$/', $freeStorageDaysInput) || !is_numeric($dailyRateInput) || (float) $dailyRateInput < 0) {
    die('Enter the free storage days and a non-negative daily USD rate.');
}

if (!in_array($warehouseExitMode, ['current', 'departed', 'collected'], true)) {
    die('Choose whether the goods are still stored, departed, or collected.');
}

$entryAt = shipmentEventDateTime(['event_date' => $entryDate, 'event_time' => $entryTime]);
$exitAt = null;
if ($warehouseExitMode !== 'current') {
    $exitDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $exitDate);
    $exitTimeObject = DateTimeImmutable::createFromFormat('!H:i', $exitTime);
    if (!$exitDateObject || $exitDateObject->format('Y-m-d') !== $exitDate || !$exitTimeObject || $exitTimeObject->format('H:i') !== $exitTime) {
        die('Enter a valid warehouse exit date and time.');
    }

    $exitAt = shipmentEventDateTime(['event_date' => $exitDate, 'event_time' => $exitTime]);
    if (!$entryAt || !$exitAt || $exitAt < $entryAt || $exitAt > new DateTimeImmutable()) {
        die('Warehouse exit must be at or after entry and not in the future.');
    }
}

if ($warehouseExitMode === 'departed' && $departureLocation === '') {
    die('Enter the departure location.');
}
if (strlen($carrierName) > 120 || strlen($flightNumber) > 40) {
    die('Airline or flight number is too long.');
}

$freeStorageDays = (int) $freeStorageDaysInput;
$dailyRate = (float) $dailyRateInput;
$formulaText = 'Charge = max((full_elapsed_days - free_days), 0) * weight_kg * daily_rate_usd_per_kg_per_day; days are 24-hour periods from entry time';

$existingEntryStmt = db()->prepare('SELECT id FROM warehouse_entries WHERE shipment_id = ? ORDER BY id ASC LIMIT 1');
$existingEntryStmt->bind_param('i', $shipmentId);
$existingEntryStmt->execute();
$hasExistingEntry = (bool) $existingEntryStmt->get_result()->fetch_assoc();
$existingEntryStmt->close();
if ($hasExistingEntry) {
    die('A warehouse entry is already recorded. Use the shipment timeline to record a later departure or customer pickup.');
}

$stmt = db()->prepare('INSERT INTO warehouse_entries (shipment_id, warehouse_name, warehouse_location, entry_date, entry_time, free_storage_days, daily_rate_per_kg_usd, formula_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('issssids', $shipmentId, $warehouseName, $warehouseLocation, $entryDate, $entryTime, $freeStorageDays, $dailyRate, $formulaText);

if ($stmt->execute()) {
    $stmt->close();

    $warehouseLocationText = $warehouseName . ', ' . $warehouseLocation;
    if (!$hasExistingEntry) {
        $receiptStmt = db()->prepare("SELECT id FROM shipment_events WHERE shipment_id = ? AND title IN ('Shipment received', 'Goods received at warehouse') ORDER BY id ASC LIMIT 1");
        $receiptStmt->bind_param('i', $shipmentId);
        $receiptStmt->execute();
        $receiptEvent = $receiptStmt->get_result()->fetch_assoc();
        $receiptStmt->close();

        if ($receiptEvent) {
            $receiptTitle = 'Goods received at warehouse';
            $updateReceiptStmt = db()->prepare("UPDATE shipment_events SET title = ?, location = ?, event_date = ?, event_time = ?, description = '' WHERE id = ?");
            $updateReceiptStmt->bind_param('ssssi', $receiptTitle, $warehouseLocationText, $entryDate, $entryTime, $receiptEvent['id']);
            $updateReceiptStmt->execute();
            $updateReceiptStmt->close();
        } else {
            $receiptTitle = 'Goods received at warehouse';
            $receiptDescription = '';
            $receiptStmt = db()->prepare('INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, description) VALUES (?, ?, ?, ?, ?, 1, 0, ?)');
            $receiptStmt->bind_param('isssss', $shipmentId, $receiptTitle, $warehouseLocationText, $entryDate, $entryTime, $receiptDescription);
            $receiptStmt->execute();
            $receiptStmt->close();
        }

        setShipmentCurrentStatus($shipmentId, 'Goods received at warehouse');
    }

    $accrualDate = $entryDateObject->modify('+' . $freeStorageDays . ' days')->format('Y-m-d');
    $accrualTitle = 'Storage fee accrual starts';
    $accrualDescription = 'Warehouse charges begin after ' . $freeStorageDays . ' free days.';
    $accrualStmt = db()->prepare("SELECT id FROM shipment_events WHERE shipment_id = ? AND title = 'Storage fee accrual starts' ORDER BY id ASC LIMIT 1");
    $accrualStmt->bind_param('i', $shipmentId);
    $accrualStmt->execute();
    $accrualEvent = $accrualStmt->get_result()->fetch_assoc();
    $accrualStmt->close();

    if ($accrualEvent) {
        $updateAccrualStmt = db()->prepare('UPDATE shipment_events SET location = ?, event_date = ?, event_time = ?, description = ? WHERE id = ?');
        $updateAccrualStmt->bind_param('ssssi', $warehouseLocationText, $accrualDate, $entryTime, $accrualDescription, $accrualEvent['id']);
        $updateAccrualStmt->execute();
        $updateAccrualStmt->close();
    } else {
        $insertAccrualStmt = db()->prepare('INSERT INTO shipment_events (shipment_id, title, location, event_date, event_time, is_current, is_alert, description) VALUES (?, ?, ?, ?, ?, 0, 0, ?)');
        $insertAccrualStmt->bind_param('isssss', $shipmentId, $accrualTitle, $warehouseLocationText, $accrualDate, $entryTime, $accrualDescription);
        $insertAccrualStmt->execute();
        $insertAccrualStmt->close();
    }

    if ($warehouseExitMode !== 'current') {
        $exitTitle = $warehouseExitMode === 'departed' ? 'Departed origin facility' : 'Collected by customer';
        $exitLocation = $warehouseExitMode === 'departed' ? $departureLocation : $warehouseLocationText;
        $exitAlertLevel = 'none';
        $exitDescription = '';
        $exitStmt = db()->prepare('INSERT INTO shipment_events (shipment_id, title, location, carrier_name, flight_number, event_date, event_time, is_current, is_alert, alert_level, description) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)');
        $exitStmt->bind_param('issssssss', $shipmentId, $exitTitle, $exitLocation, $carrierName, $flightNumber, $exitDate, $exitTime, $exitAlertLevel, $exitDescription);

        if (!$exitStmt->execute()) {
            $exitStmt->close();
            die('Warehouse entry saved, but its exit milestone could not be recorded.');
        }
        $exitEventId = $exitStmt->insert_id;
        $exitStmt->close();
        setLatestEventCurrent($shipmentId, $exitEventId);

        if ($warehouseExitMode === 'collected') {
            $destinationCountry = 'Warehouse';
            $destinationLocation = $warehouseLocationText;
            $destinationStmt = db()->prepare('UPDATE shipments SET destination_country = ?, destination_location = ? WHERE id = ?');
            $destinationStmt->bind_param('ssi', $destinationCountry, $destinationLocation, $shipmentId);
            $destinationStmt->execute();
            $destinationStmt->close();
        }

        setShipmentCurrentStatus($shipmentId, $exitTitle);
    }

    header('Location: shipment.php?id=' . $shipmentId);
    exit;
}

$stmt->close();
die('Unable to save warehouse details.');
