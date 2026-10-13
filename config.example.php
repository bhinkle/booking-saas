<?php
declare(strict_types=1);

// Copy to config.local.php OUTSIDE the public web root. Never commit the copy.
// All passwords and identity values below are placeholders.
return [
    'APP_ENV' => 'staging',
    'DB_DSN' => 'mysql:host=localhost;port=3306;dbname=hinklema_booking;charset=utf8mb4',
    'DB_USER' => 'bhinkle',
    'DB_PASSWORD' => 'password',
    'OWNER_EMAIL' => 'owner@example.test',
    'OWNER_PASSWORD_HASH' => 'replace-with-a-PHP-password-hash',
    'SESSION_SECURE' => '1',
];
