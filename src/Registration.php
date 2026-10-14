<?php
declare(strict_types=1);
require_once __DIR__ . '/TenantManagement.php';

final class Registration
{
    public const FIELDS = [
        'phone' => ['Phone', 32], 'existing_website' => ['Existing website (optional)', 2048],
        'preferred_domain' => ['Preferred domain (optional)', 253],
        'billing_name' => ['Billing name / company', 150], 'billing_email' => ['Billing email', 254],
        'address1' => ['Billing street address', 150], 'address2' => ['Suite / unit (optional)', 150],
        'city' => ['City', 100], 'region' => ['State / province / region', 100],
        'postal_code' => ['Postal code', 20], 'country' => ['Country code (e.g. US)', 2],
    ];

    public static function validate(array $data): array
    {
        $data = TenantManagement::validate($data);
        if ($data['plan_id'] === null) throw new InvalidArgumentException('Choose a subscription plan.');
        foreach (self::FIELDS as $key => [$label, $limit]) {
            $value = $data[$key] ?? '';
            if (!is_string($value) || strlen($value) > $limit) throw new InvalidArgumentException('Check ' . $label . '.');
            $data[$key] = trim($value);
            if ($data[$key] === '' && !in_array($key, ['existing_website', 'preferred_domain', 'address2'], true)) throw new InvalidArgumentException($label . ' is required.');
        }
        if (!filter_var($data['billing_email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid billing email.');
        if (!preg_match('/^[+0-9(). -]{7,32}$/', $data['phone']) || strlen(preg_replace('/\D/', '', $data['phone'])) < 7) throw new InvalidArgumentException('Enter a valid phone number.');
        if ($data['existing_website'] !== '' && (!filter_var($data['existing_website'], FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($data['existing_website'], PHP_URL_SCHEME) ?: ''), ['https', 'http'], true))) throw new InvalidArgumentException('Enter a website URL beginning with https:// or http://.');
        $domain = strtolower($data['preferred_domain']);
        if ($domain !== '' && (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !str_contains($domain, '.') || filter_var($domain, FILTER_VALIDATE_IP))) throw new InvalidArgumentException('Enter a domain such as example.com without a URL or path.');
        $data['preferred_domain'] = $domain;
        $data['country'] = strtoupper($data['country']);
        if (!preg_match('/^[A-Z]{2}$/', $data['country'])) throw new InvalidArgumentException('Enter a two-letter country code.');
        return $data;
    }

    public static function create(PDO $db, array $data): array
    {
        $data = self::validate($data);
        $db->beginTransaction();
        try {
            $statement = $db->prepare('SELECT * FROM plans WHERE id = ?');
            $statement->execute([$data['plan_id']]);
            $plan = $statement->fetch();
            if (!$plan) throw new InvalidArgumentException('The selected plan is unavailable.');
            $statement = $db->prepare('INSERT INTO tenants (business_name, contact_name, contact_email, rental_type, timezone) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$data['business_name'], $data['contact_name'], $data['contact_email'], $data['rental_type'], $data['timezone']]);
            $tenantId = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO subscriptions (tenant_id, plan_id) VALUES (?, ?)')->execute([$tenantId, $data['plan_id']]);
            $keys = array_keys(self::FIELDS);
            $statement = $db->prepare('INSERT INTO tenant_onboarding (tenant_id, ' . implode(', ', $keys) . ') VALUES (' . implode(', ', array_fill(0, count($keys) + 1, '?')) . ')');
            $statement->execute(array_merge([$tenantId], array_map(static fn($key) => $data[$key], $keys)));
            $attempt = bin2hex(random_bytes(16));
            $db->prepare('INSERT INTO billing_signup_attempts (id, tenant_id, plan_id, currency, monthly_amount_minor, setup_amount_minor) VALUES (?, ?, ?, ?, ?, ?)')->execute([$attempt, $tenantId, $plan['id'], $plan['currency'], $plan['monthly_amount_minor'], $plan['setup_amount_minor']]);
            audit($db, 'registration', 'tenant.registration_received', $tenantId);
            $db->commit();
            return ['tenant_id' => $tenantId, 'attempt' => $attempt];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }
}
