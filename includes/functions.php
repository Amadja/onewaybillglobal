<?php
require_once __DIR__ . '/db.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function safeInt($value): int
{
    return (int) ($value ?? 0);
}

function safeFloat($value): float
{
    return (float) ($value ?? 0);
}

function generateTrackingNumber(string $customerName, string $countryCode = 'UG'): string
{
    $customerPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $customerName), 0, 3));
    $countryPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $countryCode), 0, 2));

    if ($customerPrefix === '') {
        $customerPrefix = 'GRC';
    }

    if ($countryPrefix === '') {
        $countryPrefix = 'UG';
    }

    $datePart = date('dmy');
    $count = 1;

    do {
        $tracking = sprintf('%s-%s-%s%03d', $customerPrefix, $countryPrefix, $datePart, $count);
        $result = db()->prepare('SELECT id FROM shipments WHERE tracking_number = ? LIMIT 1');
        $result->bind_param('s', $tracking);
        $result->execute();
        $result->store_result();
        $exists = $result->num_rows > 0;
        $result->close();

        if (!$exists) {
            return $tracking;
        }

        $count++;
    } while (true);
}

function calculateWarehouseStorageCharge(float $weightKg, int $daysInWarehouse, int $freeStorageDays, float $dailyRate): float
{
    $chargeableDays = max($daysInWarehouse - $freeStorageDays, 0);
    return $chargeableDays * $weightKg * $dailyRate;
}

function calculateWarehouseDaysInWarehouse(string $entryDate): int
{
    $entry = DateTimeImmutable::createFromFormat('!Y-m-d', $entryDate);
    if (!$entry || $entry->format('Y-m-d') !== $entryDate) {
        return 0;
    }

    $today = new DateTimeImmutable('today');
    if ($entry > $today) {
        return 0;
    }

    return $entry->diff($today)->days;
}

function shipmentEventDateTime(array $event): ?DateTimeImmutable
{
    $eventDate = (string) ($event['event_date'] ?? '');
    if ($eventDate === '') {
        return null;
    }

    $eventTime = (string) ($event['event_time'] ?? '00:00:00');
    if (strlen($eventTime) === 5) {
        $eventTime .= ':00';
    }

    return DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $eventDate . ' ' . $eventTime) ?: null;
}

function isWarehouseExitMilestone(string $title): bool
{
    return in_array($title, [
        'Departed origin facility',
        'Preparing for international departure',
        'Arrived at destination',
        'Collected by customer',
    ], true);
}

function getWarehouseStorageSnapshot(
    float $weightKg,
    string $entryDate,
    ?string $entryTime,
    int $freeStorageDays,
    float $dailyRate,
    ?string $departureDate = null,
    ?string $departureTime = null
): array {
    $entryClock = $entryTime ? substr($entryTime, 0, 5) . ':00' : '00:00:00';
    $entryAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $entryDate . ' ' . $entryClock);
    if (!$entryAt || $entryAt->format('Y-m-d H:i:s') !== $entryDate . ' ' . $entryClock) {
        return ['days_in_warehouse' => 0, 'chargeable_days' => 0, 'storage_charge_usd' => 0.0, 'accrual_at' => null, 'departed_before_accrual' => false];
    }

    $accrualAt = $entryAt->modify('+' . max($freeStorageDays, 0) . ' days');
    $now = new DateTimeImmutable();
    $departureAt = null;
    if ($departureDate) {
        $departureClock = $departureTime ? substr($departureTime, 0, 5) . ':00' : '00:00:00';
        $departureAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $departureDate . ' ' . $departureClock) ?: null;
    }

    $departureOccurred = $departureAt !== null && $departureAt <= $now;
    $endAt = $departureOccurred ? $departureAt : $now;
    $elapsedSeconds = max($endAt->getTimestamp() - $entryAt->getTimestamp(), 0);
    $daysInWarehouse = intdiv($elapsedSeconds, 86400);

    $departedBeforeAccrual = $departureOccurred && $departureAt < $accrualAt;
    $chargeableDays = $departedBeforeAccrual ? 0 : max($daysInWarehouse - max($freeStorageDays, 0), 0);

    return [
        'days_in_warehouse' => $daysInWarehouse,
        'chargeable_days' => $chargeableDays,
        'storage_charge_usd' => $chargeableDays * $weightKg * $dailyRate,
        'accrual_at' => $accrualAt,
        'departed_before_accrual' => $departedBeforeAccrual,
    ];
}

function getShipmentTimeline(int $shipmentId): array
{
    $sql = "
        SELECT id, title, location, carrier_name, flight_number, event_date, event_time, is_current, is_alert, alert_level, description
        FROM shipment_events
        WHERE shipment_id = ?
        ORDER BY event_date ASC, event_time ASC
    ";

    $stmt = db()->prepare($sql);
    $stmt->bind_param('i', $shipmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function formatMoney(float $value): string
{
    return '$' . number_format($value, 2, '.', ',');
}

function setShipmentCurrentStatus(int $shipmentId, string $status): void
{
    $stmt = db()->prepare('UPDATE shipments SET current_status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $shipmentId);
    $stmt->execute();
    $stmt->close();
}

function setLatestEventCurrent(int $shipmentId, int $eventId): void
{
    $clear = db()->prepare('UPDATE shipment_events SET is_current = 0 WHERE shipment_id = ?');
    $clear->bind_param('i', $shipmentId);
    $clear->execute();
    $clear->close();

    $set = db()->prepare('UPDATE shipment_events SET is_current = 1 WHERE id = ?');
    $set->bind_param('i', $eventId);
    $set->execute();
    $set->close();
}
