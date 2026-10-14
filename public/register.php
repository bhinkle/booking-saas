<?php
 declare(strict_types=1);
 require dirname(__DIR__) . '/src/bootstrap.php';
 require dirname(__DIR__) . '/src/Registration.php';
 if (!in_array(configValue('APP_ENV'), ['local', 'staging'], true)) {
     http_response_code(503); pageStart('Registration is not open yet');
     echo '<p>Registration will open when launch terms and account verification are ready.</p>'; pageEnd(); exit;
 }
 $error = ''; $plans = [];
 try {
     $connection = db();
     $plans = $connection->query('SELECT * FROM plans ORDER BY monthly_amount_minor')->fetchAll();
     if ($_SERVER['REQUEST_METHOD'] === 'POST') {
         if (!csrfValid()) { http_response_code(403); $error = 'Your form expired. Please try again.'; }
         elseif (!rateAllowed($connection, 'registration', 5)) { http_response_code(429); $error = 'Too many attempts. Try again in 15 minutes.'; }
         else {
             $data = ['lifecycle_status' => 'pending_review'];
             foreach (array_merge(['business_name', 'contact_name', 'contact_email', 'rental_type', 'timezone', 'plan_id'], array_keys(Registration::FIELDS)) as $key) $data[$key] = input($key);
             if (input('capture_consent') !== 'yes') throw new InvalidArgumentException('Please confirm we can store your registration details.');
             $registration = Registration::create($connection, $data);
             $_SESSION['signup_attempt'] = $registration['attempt'];
             header('Location: /checkout.php', true, 303); exit;
         }
     }
 } catch (InvalidArgumentException $exception) { http_response_code(422); $error = $exception->getMessage(); }
 catch (PDOException $exception) {
     http_response_code(($exception->errorInfo[1] ?? null) === 1062 ? 409 : 503);
     $error = ($exception->errorInfo[1] ?? null) === 1062 ? 'Unable to continue with these details. If you previously registered, contact support before submitting again.' : 'Registration is unavailable. Please try again later.';
 } catch (Throwable $exception) { http_response_code(503); $error = 'Registration is unavailable. Please try again later.'; }
 pageStart('Register your business');
 echo '<p class="admin-intro">Choose your plan and provide your business and billing details. Next, review setup plus your first month. Monthly recurring billing begins one month after successful signup. The platform is in development.</p>';
 if ($error !== '') echo '<p class="notice error" role="alert">' . escape($error) . '</p>';
 if ($plans === []) { echo '<p>Registration is temporarily unavailable.</p>'; pageEnd(); exit; }
 echo '<form method="post" class="registration-form"><input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '"><fieldset><legend>Your plan</legend><label>Subscription plan<select name="plan_id" required><option value="">Choose a plan</option>';
 $code = is_string($_GET['plan'] ?? null) ? $_GET['plan'] : '';
 foreach ($plans as $plan) {
     $selected = $_SERVER['REQUEST_METHOD'] === 'POST' ? input('plan_id') === (string) $plan['id'] : $code === $plan['code'];
     $label = $plan['name'] . ' — ' . $plan['currency'] . ' ' . number_format((int) $plan['monthly_amount_minor'] / 100, 2) . '/month + ' . number_format((int) $plan['setup_amount_minor'] / 100, 2) . ' setup';
     echo '<option value="' . (int) $plan['id'] . '"' . ($selected ? ' selected' : '') . '>' . escape($label) . '</option>';
 }
 echo '</select></label></fieldset><fieldset><legend>Business details</legend><div class="form-row">';
 foreach (['business_name' => ['Business name',150,'text'], 'contact_name' => ['Your name',100,'text'], 'contact_email' => ['Contact email',254,'email']] as $key => [$label,$length,$type]) echo '<label>' . $label . '<input name="' . $key . '" type="' . $type . '" maxlength="' . $length . '" required value="' . escape(input($key)) . '"></label>';
 foreach (['phone','existing_website','preferred_domain'] as $key) {
     [$label,$length] = Registration::FIELDS[$key];
     echo '<label>' . escape($label) . '<input name="' . $key . '" type="' . ($key === 'existing_website' ? 'url' : ($key === 'phone' ? 'tel' : 'text')) . '" maxlength="' . $length . '"' . ($key === 'phone' ? ' required' : '') . ' value="' . escape(input($key)) . '"></label>';
 }
 echo '</div><p class="form-note">A preferred domain is a request only. Availability and ownership must be verified before it is connected or registered.</p><label>Rental type<select name="rental_type" required><option value="">Choose a rental type</option>';
 foreach (TenantManagement::TYPES as $type) echo '<option' . (input('rental_type') === $type ? ' selected' : '') . '>' . escape($type) . '</option>';
 echo '</select></label><label>Business timezone<select name="timezone">';
 foreach (DateTimeZone::listIdentifiers() as $timezone) echo '<option' . ((input('timezone') ?: 'America/New_York') === $timezone ? ' selected' : '') . '>' . escape($timezone) . '</option>';
 echo '</select></label></fieldset><fieldset><legend>Billing contact and address</legend><div class="form-row">';
 foreach (array_diff(array_keys(Registration::FIELDS), ['phone','existing_website','preferred_domain']) as $key) {
     [$label,$length] = Registration::FIELDS[$key];
     echo '<label>' . escape($label) . '<input name="' . $key . '" type="' . ($key === 'billing_email' ? 'email' : 'text') . '" maxlength="' . $length . '"' . ($key !== 'address2' ? ' required' : '') . ' value="' . escape(input($key)) . '"></label>';
 }
 echo '</div></fieldset><label class="consent"><input name="capture_consent" type="checkbox" value="yes" required' . (input('capture_consent') === 'yes' ? ' checked' : '') . '> I agree to have these business and billing contact details stored for registration and account review.</label><p class="form-note">Do not enter card numbers or security codes in this form. Payment collection is disabled until gateway setup and launch terms are complete.</p><button class="button">Continue to payment summary ↗</button></form>';
 pageEnd();
