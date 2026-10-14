<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Registration.php';
require dirname(__DIR__) . '/src/Integrations/NMI/NmiGateway.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
$data = ['business_name' => 'Test rental', 'contact_name' => 'Test owner', 'contact_email' => 'test@example.test', 'rental_type' => 'Jet skis', 'timezone' => 'America/New_York', 'lifecycle_status' => 'pending_review', 'plan_id' => '1', 'phone' => '+1 555 010 1234', 'existing_website' => 'https://example.test', 'preferred_domain' => 'EXAMPLE.TEST', 'billing_name' => 'Test rental', 'billing_email' => 'billing@example.test', 'address1' => 'Test address', 'address2' => '', 'city' => 'Test city', 'region' => 'FL', 'postal_code' => '00000', 'country' => 'us'];
verify(Registration::validate($data)['preferred_domain'] === 'example.test', 'Normalize requested domain');
verify(Registration::validate($data)['country'] === 'US', 'Normalize country');
foreach (['existing_website' => 'javascript:alert(1)', 'preferred_domain' => 'https://example.test/path', 'phone' => 'bad', 'billing_email' => 'bad', 'address1' => '', 'plan_id' => '', 'country' => '123'] as $key => $value) {
    $rejected = false;
    try { Registration::validate(array_replace($data, [$key => $value])); }
    catch (InvalidArgumentException $exception) { $rejected = true; }
    verify($rejected, 'Reject invalid ' . $key);
}
$date = new DateTimeImmutable('2026-01-31', new DateTimeZone('UTC'));
verify(NmiGateway::nextDate($date)->format('Y-m-d') === '2026-02-28', 'Clamp monthly boundary');
verify(NmiGateway::nextDate(new DateTimeImmutable('2028-01-31'))->format('Y-m-d') === '2028-02-29', 'Leap-year boundary');
foreach ([[29900,49900,'798.00'],[49900,99900,'1498.00'],[89900,149900,'2398.00']] as [$monthly,$setup,$expected]) {
    $order = $data + ['id' => 'fixture', 'currency' => 'USD', 'monthly_amount_minor' => $monthly, 'setup_amount_minor' => $setup];
    $request = NmiGateway::payload($order, 'dummy-token-for-unit-test', $date);
    verify($request['amount'] === $expected, 'Initial amount includes setup and month');
    verify($request['plan_amount'] === number_format($monthly/100,2,'.',''), 'Setup excluded from recurring amount');
    verify($request['start_date'] === '20260228', 'First renewal is deferred');
    verify(!isset($request['ccnumber'], $request['cvv']), 'Only tokenized payment details');
}
echo "PASS: registration validation, initial totals, recurring amounts and calendar boundaries\n";
