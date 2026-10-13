<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

// Fail closed outside explicitly configured development/staging environments.
if (!in_array(configValue('APP_ENV'), ['local', 'staging'], true)) {
    http_response_code(503);
    pageStart('Owner console unavailable');
    echo '<p>Production access requires the remaining authentication and MFA launch gates.</p>';
    pageEnd();
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        http_response_code(403);
        $error = 'Your form expired. Please try again.';
    } elseif (input('action') === 'logout' && isset($_SESSION['owner'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: /admin.php', true, 303);
        exit;
    } elseif (input('action') === 'login' && !isset($_SESSION['owner'])) {
        try {
            $connection = db();
            $ownerEmail = configValue('OWNER_EMAIL');
            $passwordHash = configValue('OWNER_PASSWORD_HASH');
            $allowed = rateAllowed($connection, 'owner_login', 5);
            if (!$allowed) {
                http_response_code(429);
                $error = 'Too many attempts. Try again in 15 minutes.';
            } else {
                // Perform password work even when the submitted email is wrong.
                $validPassword = $passwordHash !== '' && password_verify(is_string($_POST['password'] ?? null) ? $_POST['password'] : '', $passwordHash);
                if ($ownerEmail !== '' && hash_equals(strtolower($ownerEmail), strtolower(input('email'))) && $validPassword) {
                    audit($connection, $ownerEmail, 'owner.login');
                    session_regenerate_id(true);
                    $_SESSION['owner'] = $ownerEmail;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    header('Location: /admin.php', true, 303);
                    exit;
                }
                audit($connection, 'anonymous', 'owner.login_failed');
                $error = 'Unable to sign in with those credentials.';
            }
        } catch (Throwable $exception) {
            http_response_code(503);
            $error = 'Owner sign-in is unavailable. Check the server configuration.';
        }
    }
}
pageStart(isset($_SESSION['owner']) ? 'Your tenants' : 'Owner sign-in');
if ($error !== '') {
    echo '<p class="notice error" role="alert">' . escape($error) . '</p>';
}
if (!isset($_SESSION['owner'])) {
    echo '<p class="admin-intro">Owner-only access for the local/staging customer administration foundation.</p><form class="admin-form" method="post"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><input type="hidden" name="action" value="login"><label>Email<input name="email" type="email" autocomplete="username" required maxlength="254"></label><label>Password<input name="password" type="password" autocomplete="current-password" required maxlength="1024"></label><button class="button">Sign in ↗</button></form>';
    pageEnd();
    exit;
}
echo '<div class="admin-toolbar"><p>Businesses registered for the platform</p><a class="button small" href="/tenant.php?id=new">Add tenant ↗</a><form method="post"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><input type="hidden" name="action" value="logout"><button class="button small">Sign out</button></form></div><p class="notice">NMI is not configured. Registrations are pending review. Payment status is unverified until a gateway integration verifies provider records; no account can be marked paid from this screen.</p>';
$query = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 150) : '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$billing = is_string($_GET['billing'] ?? null) ? $_GET['billing'] : '';
$statuses = ['pending_review', 'active', 'suspended', 'canceled'];
$billingStatuses = ['not_configured', 'pending', 'trial', 'active', 'past_due', 'paused', 'canceled'];
if (!in_array($status, $statuses, true)) $status = '';
if (!in_array($billing, $billingStatuses, true)) $billing = '';
$pageValue = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = max(1, min(100000, $pageValue ?: 1));
try {
    $connection = db();
    audit($connection, $_SESSION['owner'], 'owner.customers_viewed');
    $counts = $connection->query("SELECT COUNT(*) total, SUM(lifecycle_status = 'pending_review') pending, SUM(lifecycle_status = 'active') active, SUM(lifecycle_status = 'suspended') suspended FROM tenants")->fetch();
    echo '<section class="admin-stats" aria-label="Tenant overview">';
    foreach (['total' => 'Total tenants', 'pending' => 'Pending review', 'active' => 'Active accounts', 'suspended' => 'Suspended'] as $key => $label) {
        echo '<div><span>' . $label . '</span><strong>' . (int) $counts[$key] . '</strong></div>';
    }
    echo '</section><form method="get" class="filter-form"><label>Search tenants<input name="q" value="' . escape($query) . '" placeholder="Business name or email" maxlength="150"></label>';
    foreach (['status' => ['Account status', $statuses, $status], 'billing' => ['Billing status', $billingStatuses, $billing]] as $field => [$label, $options, $selected]) {
        echo '<label>' . $label . '<select name="' . $field . '"><option value="">All statuses</option>';
        foreach ($options as $option) {
            echo '<option value="' . $option . '"' . ($selected === $option ? ' selected' : '') . '>' . escape(ucwords(str_replace('_', ' ', $option))) . '</option>';
        }
        echo '</select></label>';
    }
    echo '<button class="button small">Filter</button><a class="text-link" href="/admin.php">Reset</a></form>';
    $term = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where = "(t.business_name LIKE ? ESCAPE '!' OR t.contact_email LIKE ? ESCAPE '!')";
    $parameters = [$term, $term];
    if ($status !== '') { $where .= ' AND t.lifecycle_status = ?'; $parameters[] = $status; }
    if ($billing !== '') { $where .= ' AND s.billing_status = ?'; $parameters[] = $billing; }
    $statement = $connection->prepare('SELECT t.*, s.billing_status, s.paid_through_at, s.last_verified_at, p.name AS plan_name FROM tenants t JOIN subscriptions s ON s.tenant_id = t.id LEFT JOIN plans p ON p.id = s.plan_id WHERE ' . $where . ' ORDER BY t.id DESC LIMIT 26 OFFSET ' . (($page - 1) * 25));
    $statement->execute($parameters);
    $rows = $statement->fetchAll();
    $hasNext = count($rows) > 25;
    echo '<div class="table-scroll"><table><caption class="table-caption">Tenant directory</caption><thead><tr><th>Business</th><th>Contact</th><th>Plan</th><th>Account</th><th>Billing</th><th>Paid through (UTC)</th></tr></thead><tbody>';
    foreach (array_slice($rows, 0, 25) as $row) {
        echo '<tr><td><strong><a class="text-link" href="/tenant.php?id=' . (int) $row['id'] . '">' . escape($row['business_name']) . '</a></strong><br><small>' . escape($row['rental_type']) . '</small></td><td>' . escape($row['contact_name']) . '<br>' . escape($row['contact_email']) . '</td><td>' . escape($row['plan_name'] ?? 'Not assigned') . '</td><td><span class="status-badge">' . escape(str_replace('_', ' ', $row['lifecycle_status'])) . '</span></td><td>' . escape(str_replace('_', ' ', $row['billing_status'])) . '<br><small>' . ($row['last_verified_at'] ? 'Verified ' . escape($row['last_verified_at']) . ' UTC' : 'Not verified') . '</small></td><td>' . escape($row['paid_through_at'] ?? 'Not available') . '</td></tr>';
    }
    if ($rows === []) echo '<tr><td colspan="6">No tenants match these filters. Reset filters or add a tenant.</td></tr>';
    echo '</tbody></table></div><nav class="pagination" aria-label="Tenant pages">';
    $pagination = ['q' => $query, 'status' => $status, 'billing' => $billing];
    if ($page > 1) echo '<a href="?' . escape(http_build_query($pagination + ['page' => $page - 1])) . '">← Previous</a>';
    echo '<span>Page ' . $page . '</span>';
    if ($hasNext) echo '<a href="?' . escape(http_build_query($pagination + ['page' => $page + 1])) . '">Next →</a>';
    echo '</nav>';
} catch (Throwable $exception) {
    http_response_code(503);
    echo '<p class="notice error">Tenant records are unavailable. Check private database settings and both imported migrations.</p>';
}
pageEnd();
