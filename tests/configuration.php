<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/configuration.php';

$path = tempnam(sys_get_temp_dir(), 'booking-config-test-');
if ($path === false) throw new RuntimeException('Unable to create test fixture.');
$previousPath = getenv('APP_CONFIG_FILE');
$previousUser = getenv('DB_USER');
try {
    file_put_contents($path, "<?php return ['DB_USER' => 'fixture_user', 'TEST_MALFORMED' => ['bad']];");
    putenv('APP_CONFIG_FILE=' . $path);
    putenv('DB_USER');
    if (configValue('DB_USER') !== 'fixture_user') throw new RuntimeException('Private config was not loaded.');
    putenv('DB_USER=environment_user');
    if (configValue('DB_USER') !== 'environment_user') throw new RuntimeException('Environment must take precedence.');
    if (configValue('TEST_MALFORMED', 'fallback') !== 'fallback') throw new RuntimeException('Non-string configuration must use default.');
    echo "PASS: private configuration, environment precedence, and malformed-value fallback\n";
} finally {
    unlink($path);
    putenv($previousPath === false ? 'APP_CONFIG_FILE' : 'APP_CONFIG_FILE=' . $previousPath);
    putenv($previousUser === false ? 'DB_USER' : 'DB_USER=' . $previousUser);
}
