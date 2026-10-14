<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/Integrations/NMI/NmiGateway.php';
if (!in_array(configValue('APP_ENV'), ['local', 'staging'], true)) {
    http_response_code(503); pageStart('Checkout is not open yet'); pageEnd(); exit;
}
if (!is_string($_SESSION['signup_attempt'] ?? null)) { header('Location: /register.php', true, 303); exit; }
$error = ''; $order = null;
$enabled = configValue('NMI_SANDBOX_ENABLED') === '1' && configValue('NMI_SECURITY_KEY') !== '' && configValue('NMI_TOKENIZATION_KEY') !== '' && function_exists('curl_init');
try {
    $connection = db();
    $statement = $connection->prepare('SELECT a.*, p.name AS plan_name, o.* FROM billing_signup_attempts a JOIN plans p ON p.id = a.plan_id JOIN tenant_onboarding o ON o.tenant_id = a.tenant_id WHERE a.id = ?');
    $statement->execute([$_SESSION['signup_attempt']]);
    $order = $statement->fetch();
    if (!$order) throw new RuntimeException('Registration not found.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfValid()) { http_response_code(403); $error = 'Your payment form expired. Reload before continuing.'; }
        elseif (!$enabled) { http_response_code(503); $error = 'Payment collection is not configured.'; }
        elseif (input('billing_consent') !== 'yes' || input('payment_token') === '' || strlen(input('payment_token')) > 512) { http_response_code(422); $error = 'Confirm the billing schedule and complete the secure payment fields.'; }
        else {
            $date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            // Commit the attempt claim BEFORE any network call. Concurrent/replayed
            // requests cannot submit a second charge. Unknown outcomes stay locked.
            $claim = $connection->prepare("UPDATE billing_signup_attempts SET status = 'processing', next_billing_date = ? WHERE id = ? AND status = 'ready' AND EXISTS (SELECT 1 FROM subscriptions s JOIN tenants t ON t.id = s.tenant_id WHERE s.tenant_id = billing_signup_attempts.tenant_id AND s.plan_id = billing_signup_attempts.plan_id AND t.lifecycle_status = 'pending_review' AND s.provider_subscription_reference IS NULL)");
            $claim->execute([NmiGateway::nextDate($date)->format('Y-m-d'), $order['id']]);
            if ($claim->rowCount() !== 1) { http_response_code(409); $error = 'This payment attempt already has a result or is being reviewed. Do not submit another payment.'; }
            else {
                try { $result = (new NmiGateway(configValue('NMI_SECURITY_KEY')))->signup($order, input('payment_token'), $date); }
                catch (Throwable $exception) { $result = ['status' => 'review_required', 'transaction' => null, 'subscription' => null]; }
                $connection->beginTransaction();
                $connection->prepare('UPDATE billing_signup_attempts SET status = ?, provider_transaction_reference = ?, provider_subscription_reference = ? WHERE id = ?')->execute([$result['status'], $result['transaction'], $result['subscription'], $order['id']]);
                if ($result['subscription'] !== null) $connection->prepare("UPDATE subscriptions SET provider_subscription_reference = ?, billing_status = 'pending' WHERE tenant_id = ?")->execute([$result['subscription'], $order['tenant_id']]);
                audit($connection, 'signup', 'billing.' . $result['status'], (int) $order['tenant_id']);
                $connection->commit();
                header('Location: /checkout.php', true, 303); exit;
            }
        }
    }
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    http_response_code(503); $error = 'Checkout is unavailable or its result requires review. Please contact support before attempting another payment.';
}
pageStart('Registration & payment');
if ($error !== '') echo '<p class="notice error" role="alert">' . escape($error) . '</p>';
if (!$order) { pageEnd(); exit; }
echo '<section class="tenant-panel"><h2>' . escape($order['plan_name']) . '</h2><dl><dt>First month</dt><dd>' . escape($order['currency']) . ' ' . number_format((int) $order['monthly_amount_minor'] / 100, 2) . '</dd><dt>One-time setup</dt><dd>' . escape($order['currency']) . ' ' . number_format((int) $order['setup_amount_minor'] / 100, 2) . '</dd><dt>Initial payment</dt><dd><strong>' . escape($order['currency']) . ' ' . number_format(((int) $order['monthly_amount_minor'] + (int) $order['setup_amount_minor']) / 100, 2) . '</strong></dd><dt>Then monthly</dt><dd>' . escape($order['currency']) . ' ' . number_format((int) $order['monthly_amount_minor'] / 100, 2) . ', starting one month after signup</dd></dl></section>';
if ($order['status'] !== 'ready') {
    echo '<p class="notice">' . ($order['status'] === 'approved_pending_review' ? 'The gateway approved the initial request. Subscription and payment reconciliation are pending before activation.' : ($order['status'] === 'declined' ? 'The payment was declined. Contact support to arrange another attempt.' : 'The payment outcome requires review. Please do not submit another payment.')) . '</p>';
} elseif (!$enabled) {
    echo '<p class="notice">Your registration details and selected plan have been saved. Payment collection is not yet available; no payment has been taken.</p>';
} else {
    // Card-only Collect.js sandbox prototype. No raw card input enters PHP.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://secure.nmi.com; style-src 'self' https://secure.nmi.com; connect-src 'self' https://secure.nmi.com; frame-src https://secure.nmi.com; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    echo '<p class="notice">Sandbox checkout — use test payment details only.</p><form method="post" class="admin-form"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><label class="consent"><input type="checkbox" name="billing_consent" value="yes" required> I authorize the displayed setup plus first-month charge and the separate monthly subscription beginning one month later.</label><p>Card number</p><div id="ccnumber"></div><p>Expiration</p><div id="ccexp"></div><p>Security code</p><div id="cvv"></div><button id="payButton" type="submit" class="button">Pay & set up subscription</button></form><script src="https://secure.nmi.com/token/Collect.js" data-tokenization-key="' . escape(configValue('NMI_TOKENIZATION_KEY')) . '" data-variant="inline"></script>';
}
pageEnd();
