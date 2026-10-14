<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/TenantManagement.php';

if (!in_array(configValue('APP_ENV'), ['local', 'staging'], true)) {
    http_response_code(503);
    pageStart('Tenant management unavailable');
    echo '<p>Owner administration is restricted to local/staging until launch gates are complete.</p>';
    pageEnd();
    exit;
}
if (!isset($_SESSION['owner'])) {
    header('Location: /admin.php', true, 303);
    exit;
}
$rawId = $_GET['id'] ?? 'new';
$id = $rawId === 'new' ? null : filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(404);
    pageStart('Tenant not found');
    pageEnd();
    exit;
}
$error = '';
$row = ['business_name' => '', 'contact_name' => '', 'contact_email' => '', 'rental_type' => '', 'timezone' => 'America/New_York', 'lifecycle_status' => 'pending_review', 'plan_id' => null];
$history = [];
try {
    $connection = db();
    $plans = $connection->query('SELECT * FROM plans ORDER BY monthly_amount_minor')->fetchAll();
    if ($id !== null) {
        $statement = $connection->prepare('SELECT t.*, s.billing_status, s.paid_through_at, s.last_verified_at, s.plan_id FROM tenants t JOIN subscriptions s ON s.tenant_id = t.id WHERE t.id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            http_response_code(404);
            pageStart('Tenant not found');
            pageEnd();
            exit;
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfValid()) {
            http_response_code(403);
            $error = 'Your form expired. Reload the page and try again.';
        } else {
            $data = [];
            foreach (['business_name', 'contact_name', 'contact_email', 'rental_type', 'timezone', 'lifecycle_status', 'plan_id'] as $field) {
                $data[$field] = input($field);
            }
            try {
                $savedId = TenantManagement::save($connection, $_SESSION['owner'], $id, $data, input('revision'));
                $_SESSION['tenant_saved'] = $savedId;
                header('Location: /tenant.php?id=' . $savedId, true, 303);
                exit;
            } catch (InvalidArgumentException $exception) {
                http_response_code(422);
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                http_response_code(($exception->errorInfo[1] ?? null) === 1062 ? 422 : 503);
                $error = ($exception->errorInfo[1] ?? null) === 1062 ? 'That contact email is already assigned to another business.' : 'The update could not be saved. Please try again later.';
            }
        }
    }
    if ($id !== null) {
        $statement = $connection->prepare('SELECT o.*, a.status AS signup_status, a.next_billing_date FROM tenant_onboarding o LEFT JOIN billing_signup_attempts a ON a.tenant_id = o.tenant_id WHERE o.tenant_id = ?');
        $statement->execute([$id]);
        $onboarding = $statement->fetch();
        audit($connection, $_SESSION['owner'], 'owner.tenant_viewed', $id);
        $statement = $connection->prepare('SELECT actor, action, created_at FROM audit_logs WHERE tenant_id = ? ORDER BY id DESC LIMIT 20');
        $statement->execute([$id]);
        $history = $statement->fetchAll();
    }
} catch (Throwable $exception) {
    http_response_code(503);
    pageStart('Tenant management unavailable');
    echo '<p class="notice error">Unable to load tenant records. Check the server database configuration.</p>';
    pageEnd();
    exit;
}
pageStart($id === null ? 'Add a tenant' : $row['business_name']);
echo '<p><a class="text-link" href="/admin.php">← All tenants</a></p>';
if (isset($_SESSION['tenant_saved']) && $_SESSION['tenant_saved'] === $id) {
    unset($_SESSION['tenant_saved']);
    echo '<p class="notice" role="status">Tenant saved.</p>';
}
if ($error !== '') {
    echo '<p class="notice error" role="alert">' . escape($error) . ' Your changes were not saved; the form shows current stored details.</p>';
}
echo '<div class="tenant-layout"><section><h2>Business details</h2><form method="post" class="admin-form"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><input type="hidden" name="revision" value="' . escape(TenantManagement::revision($row)) . '">';
foreach (['business_name' => ['Business name', 150, 'text'], 'contact_name' => ['Contact name', 100, 'text'], 'contact_email' => ['Contact email', 254, 'email']] as $field => [$label, $limit, $type]) {
    echo '<label>' . $label . '<input name="' . $field . '" type="' . $type . '" maxlength="' . $limit . '" required value="' . escape($row[$field]) . '"></label>';
}
foreach (['rental_type' => ['Rental type', TenantManagement::TYPES], 'timezone' => ['Business timezone', DateTimeZone::listIdentifiers()], 'lifecycle_status' => ['Account status', $id === null ? ['pending_review'] : TenantManagement::STATUSES]] as $field => [$label, $options]) {
    echo '<label>' . $label . '<select name="' . $field . '" required>';
    if ($field === 'rental_type' && $row[$field] === '') {
        echo '<option value="">Choose a rental type</option>';
    }
    foreach ($options as $value) {
        echo '<option value="' . escape($value) . '"' . ($row[$field] === $value ? ' selected' : '') . '>' . escape(str_replace('_', ' ', $value)) . '</option>';
    }
    echo '</select></label>';
}
echo '<label>Subscription plan<select name="plan_id"><option value="">Not assigned</option>';
foreach ($plans as $plan) {
    $label = $plan['name'] . ' — ' . $plan['currency'] . ' ' . number_format((int) $plan['monthly_amount_minor'] / 100, 2) . '/month + ' . number_format((int) $plan['setup_amount_minor'] / 100, 2) . ' setup';
    echo '<option value="' . (int) $plan['id'] . '"' . ((int) ($row['plan_id'] ?? 0) === (int) $plan['id'] ? ' selected' : '') . '>' . escape($label) . '</option>';
}
echo '</select></label><p class="form-note">Plan assignment records the intended package. It does not collect payment or enable features.</p>';
echo '<p class="form-note">Suspending or canceling updates the business account record and preserves history. It does not cancel an NMI subscription. Activation requires verified billing; provisioning is not connected yet.</p><button class="button">Save tenant</button></form></section><aside><section class="tenant-panel"><h2>Subscription</h2><p><strong>' . escape(str_replace('_', ' ', $row['billing_status'] ?? 'not_configured')) . '</strong></p><dl><dt>Paid through (UTC)</dt><dd>' . escape($row['paid_through_at'] ?? 'Not available') . '</dd><dt>Last verified (UTC)</dt><dd>' . escape($row['last_verified_at'] ?? 'Not verified') . '</dd></dl><p class="form-note">Payment setup will be added with NMI integration. No payment is collected by this screen.</p></section><section class="tenant-panel"><h2>Recent activity</h2>';
if ($history === []) {
    echo '<p>No activity yet.</p>';
}
foreach ($history as $event) {
    echo '<p class="activity"><strong>' . escape($event['action']) . '</strong><br>' . escape($event['actor']) . '<br><small>' . escape($event['created_at']) . ' UTC</small></p>';
}
echo '</section>';
if (!empty($onboarding)) {
    echo '<section class="tenant-panel"><h2>Registration & billing contact</h2><dl>';
    foreach (['phone' => 'Phone', 'existing_website' => 'Existing website', 'preferred_domain' => 'Requested domain', 'billing_name' => 'Billing name', 'billing_email' => 'Billing email', 'address1' => 'Street', 'address2' => 'Suite / unit', 'city' => 'City', 'region' => 'Region', 'postal_code' => 'Postal code', 'country' => 'Country', 'signup_status' => 'Signup payment state', 'next_billing_date' => 'Scheduled billing date'] as $key => $label) {
        echo '<dt>' . $label . '</dt><dd>' . escape($onboarding[$key] ?: 'Not provided') . '</dd>';
    }
    echo '</dl><p class="form-note">Requested domains are unverified. An approved signup request is not proof of settlement or completed provisioning.</p></section>';
}
echo '</aside></div>';
pageEnd();
