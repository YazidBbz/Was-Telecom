<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$blockedPrefixes = ['/config/', '/vendor/', '/tmp/', '/logs/'];
$blockedFiles = [
    '/.env',
    '/database.sql',
    '/setup-database.bat',
    '/server.js',
    '/package.json',
    '/composer.json',
    '/composer.lock',
];

foreach ($blockedPrefixes as $prefix) {
    if (str_starts_with($path, $prefix)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if (in_array($path, $blockedFiles, true)) {
    http_response_code(403);
    exit('Forbidden');
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.html';
