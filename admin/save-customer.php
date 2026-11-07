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
$name = trim($_POST['name'] ?? '');
$companyName = trim($_POST['company_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$importPermit = trim($_POST['import_permit'] ?? '');
$website = trim($_POST['website'] ?? '');

if ($name === '' || ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL))) {
    die('Enter a client name and a valid website URL.');
}

if ($customerId > 0) {
    $stmt = db()->prepare('UPDATE customers SET name = ?, company_name = ?, phone = ?, address = ?, import_permit = ?, website = ? WHERE id = ?');
    $stmt->bind_param('ssssssi', $name, $companyName, $phone, $address, $importPermit, $website, $customerId);
} else {
    $stmt = db()->prepare('INSERT INTO customers (name, company_name, phone, address, import_permit, website) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $name, $companyName, $phone, $address, $importPermit, $website);
}

if ($stmt->execute()) {
    $savedCustomerId = $customerId > 0 ? $customerId : $stmt->insert_id;
    $stmt->close();
    header('Location: customer.php?id=' . $savedCustomerId . '&saved=1');
    exit;
}

$stmt->close();
http_response_code(500);
die('Unable to save client.');
