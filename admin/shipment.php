<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Shipment missing.');
}

$stmt = db()->prepare('SELECT s.*, c.name AS customer_name, c.company_name, c.phone, c.address, c.import_permit, c.website FROM shipments s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$shipment) {
    die('Shipment not found.');
}

$events = getShipmentTimeline($id);
$preparationEvent = null;
$departureEvent = null;
foreach ($events as $event) {
    if ($event['title'] === 'Preparing for international departure') {
        $preparationEvent = $event;
    }
    if ($event['title'] === 'Departed origin facility') {
        $departureEvent = $event;
    }
}
$nextMilestone = '';
if (!in_array($shipment['current_status'], ['Available for customer collection', 'Collected by customer'], true)) {
    foreach (['Departed origin facility', 'Preparing for international departure', 'Arrived at destination'] as $milestone) {
        $milestoneExists = false;
        foreach ($events as $event) {
            if ($event['title'] === $milestone) {
                $milestoneExists = true;
                break;
            }
        }
        if (!$milestoneExists) {
            $nextMilestone = $milestone;
            break;
        }
    }
}
$destinationTimelineLocation = $shipment['destination_country'] === 'Warehouse'
    ? (string) $shipment['destination_location']
    : trim(($shipment['destination_location'] ? $shipment['destination_location'] . ', ' : '') . $shipment['destination_country']);
$defaultEventLocation = match ($nextMilestone) {
    'Departed origin facility' => 'EBB Airport, Uganda',
    'Arrived at destination' => $destinationTimelineLocation,
    default => '',
};
$eventDateValue = in_array($nextMilestone, ['Preparing for international departure', 'Arrived at destination'], true) ? '' : date('Y-m-d');
$eventTimeValue = in_array($nextMilestone, ['Preparing for international departure', 'Arrived at destination'], true) ? '' : date('H:i');
$warehouse = db()->prepare('SELECT * FROM warehouse_entries WHERE shipment_id = ? ORDER BY id DESC LIMIT 1');
$warehouse->bind_param('i', $id);
$warehouse->execute();
$warehouseResult = $warehouse->get_result()->fetch_assoc();
$warehouse->close();

$warehouseExitEvent = null;
if ($warehouseResult) {
    $warehouseEntryAt = shipmentEventDateTime(['event_date' => $warehouseResult['entry_date'], 'event_time' => $warehouseResult['entry_time']]);
    foreach ($events as $event) {
        if (!isWarehouseExitMilestone((string) $event['title'])) {
            continue;
        }

        $eventDateTime = shipmentEventDateTime($event);
        if ($warehouseEntryAt && $eventDateTime && $eventDateTime >= $warehouseEntryAt && $eventDateTime <= new DateTimeImmutable()) {
            if (!$warehouseExitEvent || $eventDateTime < shipmentEventDateTime($warehouseExitEvent)) {
                $warehouseExitEvent = $event;
            }
        }
    }
}

$storageCharge = 0;
$daysInWarehouse = 0;
if ($warehouseResult) {
    $storage = getWarehouseStorageSnapshot(
        (float) $shipment['package_weight_kg'],
        (string) $warehouseResult['entry_date'],
        $warehouseResult['entry_time'],
        (int) $warehouseResult['free_storage_days'],
        (float) $warehouseResult['daily_rate_per_kg_usd'],
        $warehouseExitEvent['event_date'] ?? null,
        $warehouseExitEvent['event_time'] ?? null
    );
    $daysInWarehouse = $storage['days_in_warehouse'];
    $storageCharge = $storage['storage_charge_usd'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment: <?= e($shipment['tracking_number']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; color: #112333; margin: 0; }
        .wrap { max-width: 1000px; margin: 40px auto; padding: 24px; }
        .card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 10px 30px rgba(10,30,45,.06); margin-bottom: 22px; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .meta div { padding: 12px; background: #f8fafc; border-radius: 10px; }
        .timeline { padding-left: 20px; }
        .timeline li { margin-bottom: 10px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; }
        input, textarea, select, button { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #dbe5f0; }
        form { display: grid; gap: 12px; }
        button { background: #0d6eb8; color: white; border: 0; font-weight: 700; cursor: pointer; }
        .alert { color: #a52c1d; background: #fff1ee; border: 1px solid #f0c7bf; border-radius: 8px; padding: 10px 12px; }
        a { color: #0d6eb8; }
        .action-link { display: inline-block; padding: 9px 13px; border-radius: 5px; background: #102d3a; color: #fff; text-decoration: none; font-weight: 700; }
        .topbar { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        .topbar form { margin-left: auto; }
        .topbar button { width: auto; padding: 9px 14px; }
        .field-hint { display: block; margin-top: 5px; color: #657783; font-size: 12px; }
        .alert-level { display: inline-block; margin-right: 6px; padding: 2px 7px; border-radius: 3px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .alert-level-info { color: #174d65; background: #edf6fa; }
        .alert-level-attention { color: #654b00; background: #fff8df; }
        .alert-level-warning { color: #793109; background: #fff1e8; }
        .alert-level-critical { color: #76221b; background: #fff0ee; }
        .flight-detail { color: #315968; font-size: 12px; font-weight: 650; }
        .period-fields[hidden], .departure-fields[hidden] { display: none !important; }
    </style>
    <link rel="stylesheet" href="<?= e(assetUrl('admin/admin-ui.css')); ?>">
</head>
<body>
<main class="wrap">
            <nav class="topbar" aria-label="Admin navigation">
                <a href="index.php">Dashboard</a>
                <a href="customers.php">Clients</a>
                <a href="customer.php?id=<?= (int) $shipment['customer_id']; ?>">Manage client</a>
                <a href="receipt.php?id=<?= (int) $shipment['id']; ?>" target="_blank" rel="noopener">Print receipt</a>
                <?php if ($warehouseResult): ?>
                    <?php if ($storageCharge > 0): ?>
                        <a href="invoice.php?id=<?= (int) $shipment['id']; ?>" target="_blank" rel="noopener">Print invoice</a>
                    <?php else: ?>
                        <span class="field-hint" title="No warehouse storage fee has accrued">Invoice unavailable · no fee</span>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="account.php">Account</a>
                <form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>"><button type="submit">Sign out</button></form>
            </nav>
            <?php if (isset($_GET['flight_saved'])): ?><p class="alert">Flight details saved.</p><?php endif; ?>
            <div class="card">
                <h1>Shipment: <?= e($shipment['tracking_number']); ?></h1>
                <p><strong>Status:</strong> <?= e($shipment['current_status']); ?></p>
                <div class="meta">
                    <div><strong>Customer:</strong> <?= e($shipment['customer_name']); ?></div>
                    <div><strong>Service:</strong> <?= e($shipment['service_type']); ?></div>
                    <div><strong>Origin:</strong> <?= e($shipment['origin_country']); ?> / <?= e($shipment['origin_location']); ?></div>
                    <div><strong>Destination:</strong> <?= $shipment['destination_country'] === 'Warehouse' ? e($shipment['destination_location']) : e($shipment['destination_country'] . ($shipment['destination_location'] ? ' / ' . $shipment['destination_location'] : '')); ?></div>
                    <div><strong>Weight:</strong> <?= e($shipment['package_weight_kg']); ?> kg</div>
                    <div><strong>Description:</strong> <?= e($shipment['package_description']); ?></div>
                </div>
            </div>
    <div class="card">
        <h2>Customer Details</h2>
        <p><strong>Phone:</strong> <?= e($shipment['phone']); ?></p>
        <p><strong>Address:</strong> <?= e($shipment['address']); ?></p>
        <p><strong>Import Permit:</strong> <?= e($shipment['import_permit']); ?></p>
        <?php if (!empty($shipment['website'])): ?>
            <p><strong>Website:</strong> <a href="<?= e($shipment['website']); ?>" target="_blank" rel="noopener"><?= e($shipment['website']); ?></a></p>
        <?php endif; ?>
        <a href="customer.php?id=<?= (int) $shipment['customer_id']; ?>">Manage / delete client</a>
    </div>

    <?php if ($warehouseResult): ?>
        <div class="card">
            <h2>Warehouse Charge</h2>
            <p><strong>Warehouse:</strong> <?= e($warehouseResult['warehouse_name']); ?></p>
            <p><strong>Location:</strong> <?= e($warehouseResult['warehouse_location']); ?></p>
            <p><strong>Formula:</strong> <?= e($warehouseResult['formula_text']); ?></p>
            <p><strong>Daily rate:</strong> <?= formatMoney((float) $warehouseResult['daily_rate_per_kg_usd']); ?> per kg per day</p>
            <p><strong>Free days:</strong> <?= (int) $warehouseResult['free_storage_days']; ?></p>
            <p><strong>Days in warehouse:</strong> <?= (int) $daysInWarehouse; ?></p>
            <p><strong>Charge due:</strong> <?= formatMoney($storageCharge); ?></p>
            <?php if ($storageCharge > 0): ?>
                <a class="action-link" href="invoice.php?id=<?= (int) $shipment['id']; ?>" target="_blank" rel="noopener">Print storage invoice</a>
            <?php endif; ?>
            <?php if (!empty($storage['departed_before_accrual'])): ?>
                <p class="alert">No warehouse fee was charged because the shipment departed before the free period ended.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Next step after warehouse</h2>
            <p>Choose a destination to continue the shipment, or leave it at this warehouse for customer collection. You can update this plan later.</p>
            <form method="POST" action="save-route-decision.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id']; ?>">
                <input type="hidden" name="decision" value="continue">
                <h3>Continue to a destination</h3>
                <div class="grid">
                    <div>
                        <label>Destination country</label>
                        <input type="text" name="destination_country" value="<?= $shipment['destination_country'] !== 'Warehouse' && $shipment['destination_country'] !== 'To be determined' ? e($shipment['destination_country']) : ''; ?>" placeholder="Enter destination country" required>
                    </div>
                    <div>
                        <label>Destination location</label>
                        <input type="text" name="destination_location" value="<?= $shipment['destination_country'] !== 'Warehouse' && $shipment['destination_country'] !== 'To be determined' ? e($shipment['destination_location']) : ''; ?>" placeholder="City, airport, or address" required>
                    </div>
                </div>
                <button type="submit">Continue shipment</button>
            </form>
            <form method="POST" action="save-route-decision.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id']; ?>">
                <input type="hidden" name="decision" value="warehouse_collection">
                <button type="submit">Leave at warehouse for customer collection</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Record Initial Warehouse Entry</h2>
            <p>For a live stay, leave the status as “Still in warehouse.” For historical data, enter the exit date/time and select how the goods left.</p>
        <form method="POST" action="save-warehouse.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id']; ?>">
            <div class="grid">
                <div>
                    <label>Warehouse name</label>
                    <input type="text" name="warehouse_name" value="Garamba Warehouse" required>
                </div>
                <div>
                    <label>Warehouse location</label>
                    <input type="text" name="warehouse_location" value="Entebbe, Uganda" required>
                </div>
                <div>
                    <label>Entry date</label>
                    <input type="date" name="entry_date" value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
                </div>
                <div>
                    <label>Entry time</label>
                    <input type="time" name="entry_time" value="<?= date('H:i'); ?>" required>
                </div>
                <div>
                    <label for="warehouseExitMode">Warehouse status</label>
                    <select id="warehouseExitMode" name="warehouse_exit_mode">
                        <option value="current">Still in warehouse</option>
                        <option value="departed">Already departed to destination</option>
                        <option value="collected">Already collected by customer</option>
                    </select>
                </div>
                <div>
                    <label>Free days (default 5)</label>
                    <input type="number" name="free_storage_days" value="5" min="0" step="1" required>
                </div>
                <div>
                    <label>Rate (USD per kg per day)</label>
                    <input type="number" step="0.01" name="daily_rate_per_kg_usd" placeholder="Enter daily rate" min="0" required>
                </div>
            </div>
            <div id="warehouseExitFields" class="grid period-fields" hidden>
                <div>
                    <label for="warehouseExitDate">Exit date</label>
                    <input id="warehouseExitDate" type="date" name="exit_date" value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" disabled>
                </div>
                <div>
                    <label for="warehouseExitTime">Exit time</label>
                    <input id="warehouseExitTime" type="time" name="exit_time" value="<?= date('H:i'); ?>" disabled>
                </div>
            </div>
            <div id="warehouseDepartureFields" class="grid departure-fields" hidden>
                <div><label for="warehouseDepartureLocation">Departure airport/location</label><input id="warehouseDepartureLocation" name="departure_location" value="EBB Airport, Uganda" disabled></div>
                <div><label for="warehouseCarrier">Airline / carrier <span>(optional)</span></label><input id="warehouseCarrier" name="carrier_name" maxlength="120" placeholder="e.g. Kenya Airways" disabled></div>
                <div><label for="warehouseFlight">Flight number <span>(optional)</span></label><input id="warehouseFlight" name="flight_number" maxlength="40" placeholder="e.g. KQ402" disabled></div>
            </div>
            <button type="submit">Save Warehouse Details</button>
        </form>
    </div>

    <div class="card">
        <h2>Add Shipment Event</h2>
        <form method="POST" action="save-event.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id']; ?>">
            <div class="grid">
                <div>
                    <label>Milestone or update</label>
                    <input type="text" id="eventTitle" name="title" list="shipmentMilestones" value="<?= e($nextMilestone); ?>" placeholder="Choose or type a shipment update" required>
                    <datalist id="shipmentMilestones">
                        <option value="Departed origin facility"></option>
                        <option value="Preparing for international departure"></option>
                        <option value="Arrived at destination"></option>
                        <option value="Collected by customer"></option>
                    </datalist>
                </div>
                <div>
                    <label>Location</label>
                    <input type="text" id="eventLocation" name="location" value="<?= e($defaultEventLocation); ?>" placeholder="Enter airport, country, or destination" required>
                </div>
                <div>
                    <label>Event date</label>
                    <input type="date" id="eventDate" name="event_date" value="<?= e($eventDateValue); ?>" required>
                </div>
                <div>
                    <label>Event time</label>
                    <input type="time" id="eventTime" name="event_time" value="<?= e($eventTimeValue); ?>" required>
                </div>
            </div>
            <?php if ($nextMilestone === 'Departed origin facility'): ?>
                <div class="grid">
                    <div><label for="departureCarrier">Airline / operating carrier <span>(if known)</span></label><input id="departureCarrier" name="carrier_name" maxlength="120" placeholder="e.g. Kenya Airways"></div>
                    <div><label for="departureFlight">Flight number <span>(if known)</span></label><input id="departureFlight" name="flight_number" maxlength="40" placeholder="e.g. KQ402"></div>
                </div>
            <?php endif; ?>
            <div>
                <label for="eventDescription">Event note <span>(optional)</span></label>
                <textarea id="eventDescription" name="description" rows="4" placeholder="Optional context shown in the shipment history."></textarea>
            </div>
            <button type="submit">Save Event</button>
        </form>
    </div>

    <?php if ($departureEvent): ?>
        <div class="card">
            <h2>Operating flight</h2>
            <p>Optional flight details shown to customers beside the departure milestone.</p>
            <form method="POST" action="save-flight.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id']; ?>">
                <input type="hidden" name="event_id" value="<?= (int) $departureEvent['id']; ?>">
                <div class="grid">
                    <div><label for="savedCarrier">Airline / operating carrier</label><input id="savedCarrier" name="carrier_name" maxlength="120" value="<?= e((string) ($departureEvent['carrier_name'] ?? '')); ?>" placeholder="e.g. Kenya Airways"></div>
                    <div><label for="savedFlight">Flight number</label><input id="savedFlight" name="flight_number" maxlength="40" value="<?= e((string) ($departureEvent['flight_number'] ?? '')); ?>" placeholder="e.g. KQ402"></div>
                </div>
                <button type="submit">Save flight details</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Preparation-stage customer alert</h2>
        <?php if ($preparationEvent): ?>
            <p>This alert appears with “Preparing for international departure” in public tracking. It is shown on the tracking page; email/SMS delivery is not configured.</p>
            <form method="POST" action="save-preparation-alert.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id']; ?>">
                <div class="grid">
                    <div>
                        <label for="preparationAlertLevel">Severity</label>
                        <?php $preparationAlertLevel = in_array($preparationEvent['alert_level'], ['info', 'attention', 'warning', 'critical'], true) ? $preparationEvent['alert_level'] : 'none'; ?>
                        <select id="preparationAlertLevel" name="alert_level">
                            <option value="none" <?= $preparationAlertLevel === 'none' ? 'selected' : ''; ?>>No alert</option>
                            <option value="info" <?= $preparationAlertLevel === 'info' ? 'selected' : ''; ?>>Info · blue</option>
                            <option value="attention" <?= $preparationAlertLevel === 'attention' ? 'selected' : ''; ?>>Attention · amber</option>
                            <option value="warning" <?= $preparationAlertLevel === 'warning' ? 'selected' : ''; ?>>Warning · orange</option>
                            <option value="critical" <?= $preparationAlertLevel === 'critical' ? 'selected' : ''; ?>>Critical · red</option>
                        </select>
                    </div>
                    <div>
                        <label for="preparationAlertMessage">Message for customer</label>
                        <textarea id="preparationAlertMessage" name="description" rows="3" placeholder="Required for an alert level"><?= $preparationEvent['is_alert'] ? e((string) $preparationEvent['description']) : ''; ?></textarea>
                        <small class="field-hint">Choose “No alert” to clear the message.</small>
                    </div>
                </div>
                <button type="submit">Save preparation alert</button>
            </form>
        <?php else: ?>
            <p>Add the “Preparing for international departure” milestone first. Its customer alert can then be managed here.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Timeline</h2>
        <ul class="timeline">
            <?php foreach ($events as $event): ?>
                <li>
                    <strong><?= e($event['title']); ?></strong>
                    <?php if (!empty($event['carrier_name']) || !empty($event['flight_number'])): ?>
                        <div class="flight-detail">Flight: <?= e(trim(($event['carrier_name'] ? $event['carrier_name'] . ' ' : '') . $event['flight_number'])); ?></div>
                    <?php endif; ?>
                    <div><?= e($event['location']); ?></div>
                    <div><?= !empty($event['event_date']) ? e($event['event_date']) : 'Pending'; ?><?php if (!empty($event['event_time'])): ?> · <?= e($event['event_time']); ?><?php endif; ?></div>
                    <?php if (!empty($event['description'])): ?><div><?= e($event['description']); ?></div><?php endif; ?>
                    <?php if (!empty($event['is_alert'])): ?><span class="alert-level alert-level-<?= e((string) $event['alert_level']); ?>"><?= e(strtoupper((string) $event['alert_level'])); ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</main>
<script>
const eventTitle = document.getElementById('eventTitle');
const eventLocation = document.getElementById('eventLocation');
const eventDate = document.getElementById('eventDate');
const eventTime = document.getElementById('eventTime');
const preparationAlertLevel = document.getElementById('preparationAlertLevel');
const preparationAlertMessage = document.getElementById('preparationAlertMessage');
const warehouseExitMode = document.getElementById('warehouseExitMode');
const warehouseExitFields = document.getElementById('warehouseExitFields');
const warehouseDepartureFields = document.getElementById('warehouseDepartureFields');
const destinationTimelineLocation = <?= json_encode($destinationTimelineLocation, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const currentEventDate = <?= json_encode(date('Y-m-d')); ?>;
const currentEventTime = <?= json_encode(date('H:i')); ?>;
eventTitle.addEventListener('change', () => {
    const defaults = {
        'Departed origin facility': 'EBB Airport, Uganda',
        'Preparing for international departure': '',
        'Arrived at destination': destinationTimelineLocation,
    };
    if (Object.prototype.hasOwnProperty.call(defaults, eventTitle.value)) {
        eventLocation.value = defaults[eventTitle.value];
        if (eventTitle.value === 'Preparing for international departure' || eventTitle.value === 'Arrived at destination') {
            eventDate.value = '';
            eventTime.value = '';
        } else {
            eventDate.value = currentEventDate;
            eventTime.value = currentEventTime;
        }
    }
});
if (preparationAlertLevel && preparationAlertMessage) {
    preparationAlertLevel.addEventListener('change', () => {
        preparationAlertMessage.required = preparationAlertLevel.value !== 'none';
    });
    preparationAlertMessage.required = preparationAlertLevel.value !== 'none';
}
if (warehouseExitMode && warehouseExitFields && warehouseDepartureFields) {
    const exitDate = document.getElementById('warehouseExitDate');
    const exitTime = document.getElementById('warehouseExitTime');
    const departureInputs = warehouseDepartureFields.querySelectorAll('input');
    const updateWarehouseExitFields = () => {
        const hasExit = warehouseExitMode.value !== 'current';
        const isDeparture = warehouseExitMode.value === 'departed';
        warehouseExitFields.hidden = !hasExit;
        warehouseDepartureFields.hidden = !isDeparture;
        exitDate.disabled = !hasExit;
        exitTime.disabled = !hasExit;
        exitDate.required = hasExit;
        exitTime.required = hasExit;
        departureInputs.forEach((input) => {
            input.disabled = !isDeparture;
            input.required = isDeparture && input.id === 'warehouseDepartureLocation';
        });
    };
    warehouseExitMode.addEventListener('change', updateWarehouseExitFields);
    updateWarehouseExitFields();
}
</script>
</body>
</html>
