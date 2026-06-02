<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'host' => env_value('SMTP_HOST', ''),
    'port' => (int) env_value('SMTP_PORT', '587'),
    'secure' => env_value('SMTP_SECURE', 'tls'),
    'username' => env_value('SMTP_USERNAME', ''),
    'password' => env_value('SMTP_PASSWORD', ''),
    'from_email' => env_value('SMTP_FROM_EMAIL', ''),
    'from_name' => env_value('SMTP_FROM_NAME', 'WAS TELECOM'),
    'to_email' => env_value('SMTP_TO_EMAIL', ''),
    'to_name' => env_value('SMTP_TO_NAME', 'WAS TELECOM'),
];
