<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (!in_array(getenv('APP_ENV'), ['local', 'staging'], true)) {
    http_response_code(503);
    pageStart('Registration is not open yet');
    echo '<p>Registration will open when launch terms and account verification are ready.</p>';
    pageEnd();
    exit;
}

$error = '';
$types = ['Jet skis', 'Boats / pontoons', 'Golf carts', 'ATVs', 'Multiple rental types', 'Another rental type'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        http_response_code(403);
        $error = 'Your form expired. Please try again.';
    } elseif (input('name') === '' || strlen(input('name')) > 100 || input('business') === '' || strlen(input('business')) > 150 || strlen(input('email')) > 254 || !filter_var(input('email'), FILTER_VALIDATE_EMAIL) || !in_array(input('industry'), $types, true)) {
        http_response_code(422);
        $error = 'Please provide your name, business, a valid email, and rental type within the field limits.';
    } else {
        $connection = null;
        try {
            $connection = db();
            if (!rateAllowed($connection, 'registration', 5)) {
                http_response_code(429);
                $error = 'Too many attempts. Please try again in 15 minutes.';
            } else {
                $connection->beginTransaction();
                $statement = $connection->prepare('INSERT INTO tenants (business_name, contact_name, contact_email, rental_type) VALUES (?, ?, ?, ?)');
                $statement->execute([input('business'), input('name'), strtolower(input('email')), input('industry')]);
                $id = (int) $connection->lastInsertId();
                $connection->prepare('INSERT INTO subscriptions (tenant_id) VALUES (?)')->execute([$id]);
                audit($connection, 'registration', 'tenant.registration_received', $id);
                $connection->commit();
                $_SESSION['registration_received'] = true;
                header('Location: /register.php?received=1', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
            // Do not reveal whether a business email is already registered.
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
                $_SESSION['registration_received'] = true;
                header('Location: /register.php?received=1', true, 303);
                exit;
            }
            http_response_code(503);
            $error = 'Registration is unavailable. Please try again later.';
        }
    }
}
pageStart('Register your interest');
if (isset($_GET['received'], $_SESSION['registration_received'])) {
    unset($_SESSION['registration_received']);
    echo '<div class="notice"><h2>Thank you for your interest.</h2><p>If these details were not already registered, they have been received for review. Registration does not activate software or start a paid subscription.</p></div>';
} else {
    echo '<p class="admin-intro">Register your business for owner review. No payment is collected, and no software account is activated. Launch dates and terms are still being developed.</p>';
    if ($error !== '') {
        echo '<p class="notice error" role="alert">' . escape($error) . '</p>';
    }
    echo '<form method="post" class="admin-form"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><label>Your name<input name="name" autocomplete="name" required maxlength="100" value="' . escape(input('name')) . '"></label><label>Business name<input name="business" autocomplete="organization" required maxlength="150" value="' . escape(input('business')) . '"></label><label>Email<input name="email" type="email" autocomplete="email" required maxlength="254" value="' . escape(input('email')) . '"></label><label>Rental type<select name="industry" required><option value="">Choose your rental type</option>';
    foreach ($types as $type) {
        echo '<option' . (input('industry') === $type ? ' selected' : '') . '>' . escape($type) . '</option>';
    }
    echo '</select></label><p class="form-note">Submitting stores these business contact details for owner review. This local/staging registration must remain unpublished until the privacy policy and operating terms are approved.</p><button class="button">Register interest ↗</button></form>';
}
pageEnd();
