<?php

define('APP_NAME', 'One Way Bill Global');
define('DB_HOST', 'localhost');
define('DB_NAME', 'garaofjv_garamba');
define('DB_USER', 'garaofjv_Makudeveloper');
define('DB_PASS', 'Helloyou22@');
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$requestHost = preg_replace('/:\\d+$/', '', $requestHost);
$baseUrl = in_array($requestHost, ['garambacargo.com', 'www.garambacargo.com'], true)
    ? 'https://garambacargo.com'
    : 'http://localhost/onewaybillglobal';
define('BASE_URL', $baseUrl);

date_default_timezone_set('Africa/Kampala');

function appUrl(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function assetUrl(string $path): string
{
    $relativePath = ltrim($path, '/');
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $version = is_file($filePath) ? (string) filemtime($filePath) : '1';
    return appUrl($relativePath) . '?v=' . rawurlencode($version);
}

function siteAssetUrl(string $filename): string
{
    $filename = ltrim(str_replace('\\', '/', $filename), '/');
    $rootPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filename);
    $nestedPath = 'onewaybillglobal_website/' . $filename;

    return assetUrl(is_file($rootPath) ? $filename : $nestedPath);
}
