<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/TenantManagement.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejected(array $data): bool
{
    try {
        TenantManagement::validate($data);
        return false;
    } catch (InvalidArgumentException $exception) {
        return true;
    }
}
$valid = ['business_name' => 'Harbor Rentals', 'contact_name' => 'Owner', 'contact_email' => 'OWNER@example.test', 'rental_type' => 'Jet skis', 'timezone' => 'America/New_York', 'lifecycle_status' => 'pending_review'];
check(TenantManagement::validate($valid)['contact_email'] === 'owner@example.test', 'Email normalization');
foreach (['business_name' => '', 'contact_name' => str_repeat('a', 101), 'contact_email' => 'bad', 'rental_type' => 'injected', 'timezone' => 'UTC+unknown', 'lifecycle_status' => 'paid'] as $field => $value) {
    check(rejected(array_replace($valid, [$field => $value])), 'Reject invalid ' . $field);
}
check(rejected(array_replace($valid, ['business_name' => ['unexpected']])), 'Reject array inputs');
$now = new DateTimeImmutable('2026-10-13 12:00:00', new DateTimeZone('UTC'));
$billing = ['billing_status' => 'active', 'last_verified_at' => '2026-10-13 11:00:00', 'paid_through_at' => '2026-11-13 12:00:00'];
check(TenantManagement::canActivate($billing, $now), 'Verified paid subscription permits activation');
foreach (['billing_status' => 'pending', 'last_verified_at' => null, 'paid_through_at' => '2026-10-13 12:00:00'] as $field => $value) {
    check(!TenantManagement::canActivate(array_replace($billing, [$field => $value]), $now), 'Reject activation for ' . $field);
}
check(TenantManagement::revision($valid) !== TenantManagement::revision(array_replace($valid, ['lifecycle_status' => 'suspended'])), 'Revision detects concurrent status changes');
echo "PASS: tenant validation, normalization, activation rules, and revision conflicts\n";
