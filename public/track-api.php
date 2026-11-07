<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/functions.php';

    $trackingNumber = strtoupper(trim((string) ($_GET['tracking'] ?? '')));
    if ($trackingNumber === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Enter a tracking number to continue.']);
        exit;
    }

    $stmt = db()->prepare('SELECT s.id, s.tracking_number, s.origin_country, s.destination_country, s.origin_location, s.destination_location, s.service_type, s.package_description, s.package_weight_kg, s.current_status, c.name AS customer_name, c.company_name, c.phone FROM shipments s JOIN customers c ON c.id = s.customer_id WHERE s.tracking_number = ? LIMIT 1');
    $stmt->bind_param('s', $trackingNumber);
    $stmt->execute();
    $shipment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$shipment) {
        http_response_code(404);
        echo json_encode(['error' => 'Tracking number not found. Please check the number and try again.']);
        exit;
    }

    $eventStmt = db()->prepare('SELECT title, location, carrier_name, flight_number, event_date, event_time, is_current, is_alert, alert_level, description FROM shipment_events WHERE shipment_id = ? ORDER BY event_date ASC, event_time ASC, id ASC');
    $eventStmt->bind_param('i', $shipment['id']);
    $eventStmt->execute();
    $eventResult = $eventStmt->get_result();
    $events = [];
    $warehouseExitCandidates = [];
    $departureEvent = null;
    $receivedEvent = null;

    while ($event = $eventResult->fetch_assoc()) {
        if ($receivedEvent === null && in_array($event['title'], ['Shipment received', 'Goods received at warehouse'], true)) {
            $receivedEvent = $event;
        }
        if (isWarehouseExitMilestone((string) $event['title'])) {
            $warehouseExitCandidates[] = $event;
        }
        if ($event['title'] === 'Departed origin facility') {
            $departureEvent = $event;
        }
        $eventDateTime = shipmentEventDateTime($event);
        $events[] = [
            'title' => $event['title'],
            'location' => $event['location'] ?? '',
            'carrier_name' => $event['carrier_name'] ?? '',
            'flight_number' => $event['flight_number'] ?? '',
            'date' => $event['event_date'] ? date('d M Y', strtotime($event['event_date'])) : 'Pending',
            'time' => $event['event_time'] ? substr($event['event_time'], 0, 5) : '',
            'completed' => $eventDateTime !== null && $eventDateTime <= new DateTimeImmutable(),
            'current' => (bool) $event['is_current'],
            'alert' => (bool) $event['is_alert'],
            'alert_level' => $event['alert_level'] !== 'none'
                ? $event['alert_level']
                : ((bool) $event['is_alert'] ? 'warning' : 'none'),
            'description' => $event['description'] ?? '',
        ];
    }
    $eventStmt->close();

    $warehouseStmt = db()->prepare('SELECT warehouse_name, warehouse_location, entry_date, entry_time, free_storage_days, daily_rate_per_kg_usd, formula_text FROM warehouse_entries WHERE shipment_id = ? ORDER BY id DESC LIMIT 1');
    $warehouseStmt->bind_param('i', $shipment['id']);
    $warehouseStmt->execute();
    $warehouse = $warehouseStmt->get_result()->fetch_assoc();
    $warehouseStmt->close();

    $warehouseEntryAt = $warehouse
        ? shipmentEventDateTime(['event_date' => $warehouse['entry_date'], 'event_time' => $warehouse['entry_time']])
        : null;
    $warehouseExitEvent = null;
    if ($warehouse && $warehouseEntryAt) {
        foreach ($warehouseExitCandidates as $event) {
            $eventDateTime = shipmentEventDateTime($event);
            if ($eventDateTime && $eventDateTime >= $warehouseEntryAt && $eventDateTime <= new DateTimeImmutable()) {
                if (!$warehouseExitEvent || $eventDateTime < shipmentEventDateTime($warehouseExitEvent)) {
                    $warehouseExitEvent = $event;
                }
            }
        }
    }

    $warehouseInStorage = $warehouse && !$warehouseExitEvent;
    $warehouseStorage = null;
    if ($warehouse) {
        $warehouseStorage = getWarehouseStorageSnapshot(
            (float) $shipment['package_weight_kg'],
            (string) $warehouse['entry_date'],
            $warehouse['entry_time'],
            (int) $warehouse['free_storage_days'],
            (float) $warehouse['daily_rate_per_kg_usd'],
            $warehouseExitEvent['event_date'] ?? null,
            $warehouseExitEvent['event_time'] ?? null
        );
    }

    $hasDeparted = $warehouseExitEvent
        ? in_array($warehouseExitEvent['title'], ['Departed origin facility', 'Preparing for international departure', 'Arrived at destination'], true)
        : (!$warehouse && $departureEvent !== null);
    if ($warehouseExitEvent) {
        $savedReceiptAt = $receivedEvent ? shipmentEventDateTime($receivedEvent) : null;
        $warehouseReceiptAt = $savedReceiptAt && $savedReceiptAt <= shipmentEventDateTime($warehouseExitEvent)
            ? $savedReceiptAt
            : $warehouseEntryAt;
        $events = array_values(array_filter($events, static function (array $event) use ($hasDeparted): bool {
            if (in_array($event['title'], ['Shipment received', 'Goods received at warehouse'], true)) {
                return false;
            }
            return !$hasDeparted || $event['title'] !== 'Storage fee accrual starts';
        }));

        $receiptTitle = $hasDeparted ? 'Shipment received' : 'Goods received at warehouse';
        $receiptLocation = $hasDeparted
            ? trim('Warehouse, ' . (string) $warehouse['warehouse_location'])
            : trim(implode(', ', array_filter([$warehouse['warehouse_name'], $warehouse['warehouse_location']])));
        $events[] = [
            'title' => $receiptTitle,
            'location' => $receiptLocation,
            'date' => $warehouseReceiptAt->format('d M Y'),
            'time' => $warehouseReceiptAt->format('H:i'),
            'completed' => true,
            'current' => false,
            'alert' => false,
            'description' => '',
        ];
    }

    if ($warehouseStorage && $warehouseStorage['departed_before_accrual']) {
        $events = array_values(array_filter($events, static function (array $event): bool {
            return $event['title'] !== 'Storage fee accrual starts';
        }));
    }

    if ($warehouseInStorage) {
        $warehouseLocation = trim(implode(', ', array_filter([$warehouse['warehouse_name'], $warehouse['warehouse_location']])));
        $freeStorageDays = (int) $warehouse['free_storage_days'];
        $accrualAt = $warehouseStorage['accrual_at'] ?? null;
        $accrualDescription = 'Warehouse charges begin after ' . $freeStorageDays . ' free days.';
        $events = [
            [
                'title' => 'Goods received at warehouse',
                'location' => $warehouseLocation,
                'date' => $warehouseEntryAt ? $warehouseEntryAt->format('d M Y') : 'Pending',
                'time' => $warehouseEntryAt ? $warehouseEntryAt->format('H:i') : '',
                'completed' => true,
                'current' => true,
                'alert' => false,
                'description' => '',
            ],
            [
                'title' => 'Storage fee accrual starts',
                'location' => $warehouseLocation,
                'date' => $accrualAt ? $accrualAt->format('d M Y') : 'Pending',
                'time' => $accrualAt ? $accrualAt->format('H:i') : '',
                'completed' => $accrualAt !== null && $accrualAt <= new DateTimeImmutable(),
                'current' => false,
                'alert' => false,
                'description' => $accrualDescription,
            ],
        ];
    }

    if (!$warehouseInStorage && !in_array($shipment['current_status'], ['Available for customer collection', 'Collected by customer'], true)) {
        $hasDeparture = false;
        $hasPreparation = false;
        $hasArrival = false;
        foreach ($events as $event) {
            $hasDeparture = $hasDeparture || $event['title'] === 'Departed origin facility';
            $hasPreparation = $hasPreparation || $event['title'] === 'Preparing for international departure';
            $hasArrival = $hasArrival || $event['title'] === 'Arrived at destination';
        }

        if ($hasDeparture && !$hasPreparation) {
            $events[] = [
                'title' => 'Preparing for international departure',
                'location' => '',
                'date' => 'Pending',
                'time' => '',
                'completed' => false,
                'current' => false,
                'alert' => false,
                'description' => '',
            ];
        }

        if ($hasDeparture && !$hasArrival) {
            $arrivalLocation = $shipment['destination_country'] === 'Warehouse'
                ? (string) $shipment['destination_location']
                : trim(($shipment['destination_location'] ? $shipment['destination_location'] . ', ' : '') . $shipment['destination_country']);
            $events[] = [
                'title' => 'Arrived at destination',
                'location' => $arrivalLocation,
                'date' => 'Pending',
                'time' => '',
                'completed' => false,
                'current' => false,
                'alert' => false,
                'description' => '',
            ];
        }
    }

    $milestoneOrder = [
        'Shipment received' => 0,
        'Goods received at warehouse' => 0,
        'Departed origin facility' => 1,
        'Preparing for international departure' => 2,
        'Storage fee accrual starts' => 3,
        'Arrived at destination' => 4,
    ];
    usort($events, static function (array $left, array $right) use ($milestoneOrder): int {
        $leftAt = $left['date'] === 'Pending' ? PHP_INT_MAX : (strtotime($left['date'] . ' ' . ($left['time'] ?: '00:00')) ?: PHP_INT_MAX);
        $rightAt = $right['date'] === 'Pending' ? PHP_INT_MAX : (strtotime($right['date'] . ' ' . ($right['time'] ?: '00:00')) ?: PHP_INT_MAX);
        $timeOrder = $leftAt <=> $rightAt;
        if ($timeOrder !== 0) {
            return $timeOrder;
        }

        return ($milestoneOrder[$left['title']] ?? 5) <=> ($milestoneOrder[$right['title']] ?? 5);
    });

    $origin = trim(($shipment['origin_location'] ? $shipment['origin_location'] . ', ' : '') . $shipment['origin_country']);
    $destination = $shipment['destination_country'] === 'Warehouse'
        ? (string) $shipment['destination_location']
        : trim(($shipment['destination_location'] ? $shipment['destination_location'] . ', ' : '') . $shipment['destination_country']);
    $response = [
        'tracking_number' => $shipment['tracking_number'],
        'status' => $warehouseInStorage ? 'Goods received at warehouse' : $shipment['current_status'],
        'origin' => $origin,
        'destination' => $destination,
        'service' => $shipment['service_type'],
        'package_description' => $shipment['package_description'] ?? '',
        'package_weight' => number_format((float) $shipment['package_weight_kg'], 2, '.', '') . ' kg',
        'warehouse_route_pending' => (bool) $warehouseInStorage,
        'events' => $events,
    ];

    if ($warehouseInStorage) {
        $response['customer'] = [
            'name' => $shipment['customer_name'],
            'company_name' => $shipment['company_name'],
            'phone' => $shipment['phone'],
        ];
    }

    if ($warehouse && $warehouseInStorage) {
        $warehouseLocation = trim(implode(', ', array_filter([$warehouse['warehouse_name'], $warehouse['warehouse_location']])));
        $freeStorageDays = (int) $warehouse['free_storage_days'];
        $dailyRate = (float) $warehouse['daily_rate_per_kg_usd'];
        $response['warehouse'] = [
            'location' => $warehouseLocation,
            'entry_date' => $warehouse['entry_date'] ? date('d M Y', strtotime($warehouse['entry_date'])) : '',
            'days_in_warehouse' => $warehouseStorage['days_in_warehouse'],
            'free_storage_days' => $freeStorageDays,
            'daily_rate_per_kg_usd' => $dailyRate,
            'chargeable_days' => $warehouseStorage['chargeable_days'],
            'storage_charge_usd' => $warehouseStorage['storage_charge_usd'],
            'departed_before_accrual' => $warehouseStorage['departed_before_accrual'],
            'formula' => $warehouse['formula_text'],
        ];
    }

    echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Tracking is temporarily unavailable.']);
}