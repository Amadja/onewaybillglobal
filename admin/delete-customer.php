<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customers.php');
    exit;
}
requireCsrfToken();

$customerId = (int) ($_POST['customer_id'] ?? 0);
if ($customerId <= 0) {
    header('Location: customers.php?error=not_found');
    exit;
}

try {
    $connection = db();
    $connection->begin_transaction();
    $transactionStarted = true;

    $customerStmt = $connection->prepare('SELECT id FROM customers WHERE id = ? FOR UPDATE');
    $customerStmt->bind_param('i', $customerId);
    $customerStmt->execute();
    $customerExists = (bool) $customerStmt->get_result()->fetch_assoc();
    $customerStmt->close();

    if (!$customerExists) {
        $connection->rollback();
        header('Location: customers.php?error=not_found');
        exit;
    }

    $countStmt = $connection->prepare('SELECT COUNT(*) AS shipment_count FROM shipments WHERE customer_id = ?');
    $countStmt->bind_param('i', $customerId);
    $countStmt->execute();
    $shipmentCount = (int) $countStmt->get_result()->fetch_assoc()['shipment_count'];
    $countStmt->close();

    $deleteStmt = $connection->prepare('DELETE FROM customers WHERE id = ?');
    $deleteStmt->bind_param('i', $customerId);
    $deleteStmt->execute();
    $deleteStmt->close();
    $connection->commit();
    $transactionStarted = false;
} catch (Throwable $exception) {
    if (!empty($transactionStarted)) {
        $connection->rollback();
    }
    error_log($exception->getMessage());
    header('Location: customers.php?error=delete_failed');
    exit;
}

header('Location: customers.php?deleted=' . $shipmentCount);
exit;
