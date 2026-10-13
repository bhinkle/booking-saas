<?php
declare(strict_types=1);

function configValue(string $key, string $default = ''): string
{
    static $private;
    $environment = getenv($key);
    if ($environment !== false) {
        return $environment;
    }
    if ($private === null) {
        $private = [];
        $path = getenv('APP_CONFIG_FILE') ?: dirname(__DIR__) . '/config.local.php';
        if (is_file($path)) {
            $resolved = realpath($path);
            $public = realpath(dirname(__DIR__) . '/public');
            if ($resolved === false || ($public !== false && str_starts_with($resolved, $public . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Configuration must be outside the public directory.');
            }
            $loaded = require $resolved;
            if (!is_array($loaded)) {
                throw new RuntimeException('Private configuration must return an array.');
            }
            $private = $loaded;
        }
    }
    $value = $private[$key] ?? $default;
    return is_string($value) ? $value : $default;
}
