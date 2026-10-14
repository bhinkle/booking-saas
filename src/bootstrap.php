<?php
declare(strict_types=1);

require_once __DIR__ . '/configuration.php';

// Early local/staging foundation. MFA and verified billing are launch gates.
// Buffer rendered HTML so a later load failure can still set its HTTP status.
ob_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
date_default_timezone_set('UTC');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('booking_owner');
session_set_cookie_params([
    'httponly' => true,
    'secure' => configValue('SESSION_SECURE') !== '0',
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();
// Expire owner access after 30 minutes idle, including POST requests.
if (isset($_SESSION['owner'], $_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (isset($_SESSION['owner'])) {
    $_SESSION['last_activity'] = time();
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfValid(): bool
{
    return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $dsn = configValue('DB_DSN');
    if (!$dsn || !str_starts_with($dsn, 'mysql:')) {
        throw new RuntimeException('MySQL environment configuration is required.');
    }
    $connection = new PDO($dsn, configValue('DB_USER'), configValue('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '+00:00'");
    return $connection;
}

function input(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function rateAllowed(PDO $db, string $purpose, int $maximum): bool
{
    // Trust only the server's remote address, never a client-forwarded header.
    $bucket = hash('sha256', $purpose . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $statement = $db->prepare('INSERT INTO request_limits (bucket, attempts, window_started_at) VALUES (?, 1, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts = IF(window_started_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, 1, attempts + 1), window_started_at = IF(window_started_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, UTC_TIMESTAMP(), window_started_at)');
    $statement->execute([$bucket]);
    $statement = $db->prepare('SELECT attempts FROM request_limits WHERE bucket = ?');
    $statement->execute([$bucket]);
    return (int) $statement->fetchColumn() <= $maximum;
}

function audit(PDO $db, string $actor, string $action, ?int $tenantId = null): void
{
    $statement = $db->prepare('INSERT INTO audit_logs (actor, action, tenant_id) VALUES (?, ?, ?)');
    $statement->execute([$actor, $action, $tenantId]);
}

function pageStart(string $title, bool $registration = false): void
{
    if ($registration) {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . escape($title) . ' — Booking Software</title><link rel="stylesheet" href="assets/site.css?v=registration-7"></head><body><header class="header wrap"><a class="brand" href="./"><span class="brand-mark" aria-hidden="true">b<span>↗</span></span>booking<span class="brand-light">software</span></a><a class="text-link" href="./">Back to website ↗</a></header><main class="contact-section"><div class="wrap contact-grid">';
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . escape($title) . ' — Booking Software</title><link rel="stylesheet" href="assets/site.css"><link rel="stylesheet" href="assets/admin.css?v=signup-3"></head><body><header class="header wrap"><a class="brand" href="./">booking<span class="brand-light">software</span></a><a href="./">Back to website</a></header><main class="wrap admin-main' . ($registration ? ' signup-main' : '') . '">';
    if (isset($_SESSION['owner'])) {
        echo '<nav class="admin-navigation" aria-label="Owner navigation"><a href="/admin.php">Tenants</a><a href="/tenant.php?id=new">Add tenant</a><a href="/plans.php">Plans</a></nav>';
    }
    echo '<p class="eyebrow">' . ($registration ? 'GET STARTED WITH BOOKING SOFTWARE' : 'PLATFORM ADMINISTRATION') . '</p><h1>' . escape($title) . '</h1>';
}

function pageEnd(bool $registration = false): void
{
    echo ($registration ? '</div>' : '') . '</main></body></html>';
}
