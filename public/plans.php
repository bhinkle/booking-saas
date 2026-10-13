<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (!in_array(configValue('APP_ENV'), ['local', 'staging'], true)) {
    http_response_code(503);
    pageStart('Plan catalog unavailable');
    pageEnd();
    exit;
}
if (!isset($_SESSION['owner'])) {
    header('Location: /admin.php', true, 303);
    exit;
}
pageStart('Subscription plans');
echo '<p class="admin-intro">Your packages and current assignment counts. Plan assignments do not represent paid subscriptions.</p>';
try {
    $connection = db();
    audit($connection, $_SESSION['owner'], 'owner.plans_viewed');
    $plans = $connection->query('SELECT p.*, (SELECT COUNT(*) FROM subscriptions s WHERE s.plan_id = p.id) AS assigned_count FROM plans p ORDER BY p.monthly_amount_minor')->fetchAll();
    echo '<div class="plans">';
    foreach ($plans as $plan) {
        echo '<article class="plan"><p class="eyebrow">' . (int) $plan['assigned_count'] . ' TENANTS ASSIGNED</p><h2>' . escape($plan['name']) . '</h2><p class="pricing">' . escape($plan['currency']) . ' ' . number_format((int) $plan['monthly_amount_minor'] / 100, 2) . '/month</p><p>' . escape($plan['currency']) . ' ' . number_format((int) $plan['setup_amount_minor'] / 100, 2) . ' setup</p><p>' . escape($plan['description']) . '</p><p class="form-note">Purchasing ' . ($plan['purchasing_enabled'] ? 'enabled in the catalog; gateway setup still required' : 'disabled') . '</p></article>';
    }
    echo '</div>';
    if ($plans === []) echo '<p class="notice">No plans found. Verify the subscription plan migration.</p>';
} catch (Throwable $exception) {
    http_response_code(503);
    echo '<p class="notice error">Unable to load plans. Check the database configuration and subscription plan migration.</p>';
}
pageEnd();
