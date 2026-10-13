<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

// Fail closed outside explicitly configured development/staging environments.
if (!in_array(getenv('APP_ENV'), ['local', 'staging'], true)) {
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
            $ownerEmail = getenv('OWNER_EMAIL') ?: '';
            $passwordHash = getenv('OWNER_PASSWORD_HASH') ?: '';
            $allowed = rateAllowed($connection, 'owner_login', 5);
            if (!$allowed) {
                http_response_code(429);
                $error = 'Too many attempts. Try again in 15 minutes.';
            } else {
                // Perform password work even when the submitted email is wrong.
                $validPassword = $passwordHash !== '' && password_verify(input('password'), $passwordHash);
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
pageStart(isset($_SESSION['owner']) ? 'Your customers' : 'Owner sign-in');
if ($error !== '') {
    echo '<p class="notice error" role="alert">' . escape($error) . '</p>';
}
if (!isset($_SESSION['owner'])) {
    echo '<p class="admin-intro">Owner-only access for the local/staging customer administration foundation.</p><form class="admin-form" method="post"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><input type="hidden" name="action" value="login"><label>Email<input name="email" type="email" autocomplete="username" required maxlength="254"></label><label>Password<input name="password" type="password" autocomplete="current-password" required maxlength="1024"></label><button class="button">Sign in ↗</button></form>';
    pageEnd();
    exit;
}
echo '<div class="admin-toolbar"><p>Businesses registered for the platform</p><form method="post"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><input type="hidden" name="action" value="logout"><button class="button small">Sign out</button></form></div><p class="notice">NMI is not configured. Registrations are pending review. Payment status is unverified until a gateway integration verifies provider records; no account can be marked paid from this screen.</p>';
$query = $_GET['q'] ?? '';
$query = is_string($query) ? substr(trim($query), 0, 150) : '';
$page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
echo '<form method="get" class="search-form"><label>Find a customer<input name="q" value="' . escape($query) . '" placeholder="Business name or email" maxlength="150"></label><button class="button small">Search</button></form>';
try {
    $connection = db();
    audit($connection, $_SESSION['owner'], 'owner.customers_viewed');
    $term = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $statement = $connection->prepare("SELECT t.*, s.billing_status, s.paid_through_at, s.last_verified_at FROM tenants t JOIN subscriptions s ON s.tenant_id = t.id WHERE t.business_name LIKE ? ESCAPE '!' OR t.contact_email LIKE ? ESCAPE '!' ORDER BY t.id DESC LIMIT 26 OFFSET " . (($page - 1) * 25));
    $statement->execute([$term, $term]);
    $rows = $statement->fetchAll();
    $hasNext = count($rows) > 25;
    echo '<div class="table-scroll"><table><thead><tr><th>Business</th><th>Contact</th><th>Rentals</th><th>Account</th><th>Billing</th><th>Paid through (UTC)</th></tr></thead><tbody>';
    foreach (array_slice($rows, 0, 25) as $row) {
        echo '<tr><td><strong>' . escape($row['business_name']) . '</strong><br><small>Registered ' . escape($row['created_at']) . ' UTC</small></td><td>' . escape($row['contact_name']) . '<br>' . escape($row['contact_email']) . '</td><td>' . escape($row['rental_type']) . '</td><td>' . escape(str_replace('_', ' ', $row['lifecycle_status'])) . '</td><td>' . escape(str_replace('_', ' ', $row['billing_status'])) . '<br><small>' . ($row['last_verified_at'] ? 'Verified ' . escape($row['last_verified_at']) . ' UTC' : 'Not verified') . '</small></td><td>' . escape($row['paid_through_at'] ?? 'Not available') . '</td></tr>';
    }
    if ($rows === []) {
        echo '<tr><td colspan="6">No customers found.</td></tr>';
    }
    echo '</tbody></table></div><nav class="pagination" aria-label="Customer pages">';
    if ($page > 1) {
        echo '<a href="?q=' . escape(rawurlencode($query)) . '&amp;page=' . ($page - 1) . '">← Previous</a>';
    }
    echo '<span>Page ' . $page . '</span>';
    if ($hasNext) {
        echo '<a href="?q=' . escape(rawurlencode($query)) . '&amp;page=' . ($page + 1) . '">Next →</a>';
    }
    echo '</nav>';
} catch (Throwable $exception) {
    http_response_code(503);
    echo '<p class="notice error">Customer records are unavailable. Check MySQL configuration and the reviewed migration.</p>';
}
pageEnd();
