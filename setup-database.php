<?php
require_once __DIR__ . '/config.php';

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($connection->connect_error) {
    die('Unable to connect to MySQL server: ' . $connection->connect_error);
}

$databaseName = str_replace('`', '``', DB_NAME);
try {
    $connection->query('CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (mysqli_sql_exception $exception) {
    // Shared hosting may only allow access to a database created in its control panel.
}

try {
    $connection->select_db(DB_NAME);
} catch (mysqli_sql_exception $exception) {
    die('Database is unavailable. Create it in your hosting control panel and update config.php.');
}

$sql = file_get_contents(__DIR__ . '/db/schema.sql');
if ($sql === false) {
    die('Unable to read schema file.');
}

if (!$connection->multi_query($sql)) {
    die('Schema error: ' . $connection->error);
}

while ($connection->more_results()) {
    $connection->next_result();
}

$carrierColumn = $connection->query("SHOW COLUMNS FROM shipment_events LIKE 'carrier_name'");
if ($carrierColumn->num_rows === 0) {
    $connection->query('ALTER TABLE shipment_events ADD COLUMN carrier_name VARCHAR(120) DEFAULT NULL AFTER location');
}
$flightColumn = $connection->query("SHOW COLUMNS FROM shipment_events LIKE 'flight_number'");
if ($flightColumn->num_rows === 0) {
    $connection->query('ALTER TABLE shipment_events ADD COLUMN flight_number VARCHAR(40) DEFAULT NULL AFTER carrier_name');
}

$alertLevelColumn = $connection->query("SHOW COLUMNS FROM shipment_events LIKE 'alert_level'");
if ($alertLevelColumn->num_rows === 0) {
    $connection->query("ALTER TABLE shipment_events ADD COLUMN alert_level VARCHAR(16) NOT NULL DEFAULT 'none' AFTER is_alert");
    $connection->query("UPDATE shipment_events SET alert_level = 'warning' WHERE is_alert = 1");
}

$misplacedAlerts = $connection->query("SELECT id, shipment_id, event_date, event_time, alert_level, description FROM shipment_events WHERE title = 'Arrived at destination' AND is_alert = 1 ORDER BY event_date, event_time, id");
while ($arrivalAlert = $misplacedAlerts->fetch_assoc()) {
    $preparationLookup = $connection->prepare("SELECT id FROM shipment_events WHERE shipment_id = ? AND title = 'Preparing for international departure' AND CONCAT(event_date, ' ', COALESCE(event_time, '00:00:00')) <= CONCAT(?, ' ', COALESCE(?, '00:00:00')) ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 1");
    $preparationLookup->bind_param('iss', $arrivalAlert['shipment_id'], $arrivalAlert['event_date'], $arrivalAlert['event_time']);
    $preparationLookup->execute();
    $preparation = $preparationLookup->get_result()->fetch_assoc();
    $preparationLookup->close();

    if ($preparation) {
        $alertLevel = $arrivalAlert['alert_level'] !== 'none' ? $arrivalAlert['alert_level'] : 'warning';
        $preparationUpdate = $connection->prepare('UPDATE shipment_events SET is_alert = 1, alert_level = ?, description = ? WHERE id = ?');
        $preparationUpdate->bind_param('ssi', $alertLevel, $arrivalAlert['description'], $preparation['id']);
        $preparationUpdate->execute();
        $preparationUpdate->close();

        $arrivalUpdate = $connection->prepare("UPDATE shipment_events SET is_alert = 0, alert_level = 'none', description = '' WHERE id = ?");
        $arrivalUpdate->bind_param('i', $arrivalAlert['id']);
        $arrivalUpdate->execute();
        $arrivalUpdate->close();
    }
}

$adminLookup = $connection->prepare('SELECT id FROM auth_users WHERE username = ? LIMIT 1');
$adminUsername = 'admin';
$adminLookup->bind_param('s', $adminUsername);
$adminLookup->execute();
$adminExists = (bool) $adminLookup->get_result()->fetch_assoc();
$adminLookup->close();

if (!$adminExists) {
    $adminName = 'Administrator';
    $adminPasswordHash = password_hash('admin', PASSWORD_DEFAULT);
    $adminInsert = $connection->prepare('INSERT INTO auth_users (username, display_name, password_hash, role) VALUES (?, ?, ?, ?)');
    $adminRole = 'admin';
    $adminInsert->bind_param('ssss', $adminUsername, $adminName, $adminPasswordHash, $adminRole);
    $adminInsert->execute();
    $adminInsert->close();
}

$connection->close();

echo 'Database setup completed successfully.';
