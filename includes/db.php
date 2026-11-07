<?php
require_once __DIR__ . '/../config.php';

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

$db->set_charset('utf8mb4');

function db(): mysqli
{
    global $db;
    return $db;
}
