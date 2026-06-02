<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'host' => env_value('DB_HOST', 'localhost'),
    'port' => env_value('DB_PORT', '3306'),
    'database' => env_value('DB_DATABASE', 'was_telecom'),
    'username' => env_value('DB_USERNAME', 'was_telecom'),
    'password' => env_value('DB_PASSWORD', ''),
    'charset' => env_value('DB_CHARSET', 'utf8mb4'),
];
