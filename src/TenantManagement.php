<?php
declare(strict_types=1);

final class TenantManagement
{
    public const TYPES = ['Jet skis', 'Boats / pontoons', 'Golf carts', 'ATVs', 'Multiple rental types', 'Another rental type'];
    public const STATUSES = ['pending_review', 'active', 'suspended', 'canceled'];

    public static function validate(array $data): array
    {
        foreach (['business_name' => 150, 'contact_name' => 100, 'contact_email' => 254] as $field => $limit) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '' || strlen($data[$field]) > $limit) {
                throw new InvalidArgumentException('Provide a business name, contact name, and email within the field limits.');
            }
            $data[$field] = trim($data[$field]);
        }
        $data['contact_email'] = strtolower($data['contact_email']);
        if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (!in_array($data['rental_type'] ?? '', self::TYPES, true)) {
            throw new InvalidArgumentException('Choose a supported rental type.');
        }
        if (!in_array($data['timezone'] ?? '', DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Choose a valid business timezone.');
        }
        if (!in_array($data['lifecycle_status'] ?? '', self::STATUSES, true)) {
            throw new InvalidArgumentException('Choose a valid account status.');
        }
        $plan = $data['plan_id'] ?? '';
        if ($plan === '' || $plan === null) {
            $data['plan_id'] = null;
        } elseif (!is_scalar($plan) || filter_var($plan, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException('Choose a valid subscription plan.');
        } else {
            $data['plan_id'] = (int) $plan;
        }
        return $data;
    }

    public static function revision(array $row): string
    {
        $values = [];
        foreach (['business_name', 'contact_name', 'contact_email', 'rental_type', 'timezone', 'lifecycle_status'] as $key) {
            $values[] = $row[$key];
        }
        $values[] = isset($row['plan_id']) ? (int) $row['plan_id'] : null;
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function canActivate(array $row, DateTimeImmutable $now): bool
    {
        if (($row['billing_status'] ?? '') !== 'active' || empty($row['last_verified_at']) || empty($row['paid_through_at'])) {
            return false;
        }
        return new DateTimeImmutable($row['paid_through_at'], new DateTimeZone('UTC')) > $now;
    }

    public static function save(PDO $db, string $actor, ?int $id, array $data, string $revision): int
    {
        if ($actor === '' || !isset($_SESSION['owner']) || !hash_equals($_SESSION['owner'], $actor)) {
            throw new RuntimeException('Owner access is required.');
        }
        $data = self::validate($data);
        $db->beginTransaction();
        try {
            if ($data['plan_id'] !== null) {
                $plan = $db->prepare('SELECT id FROM plans WHERE id = ?');
                $plan->execute([$data['plan_id']]);
                if (!$plan->fetch()) {
                    throw new InvalidArgumentException('The selected plan no longer exists.');
                }
            }
            if ($id !== null) {
                $statement = $db->prepare('SELECT t.*, s.billing_status, s.paid_through_at, s.last_verified_at, s.plan_id, s.provider_subscription_reference FROM tenants t JOIN subscriptions s ON s.tenant_id = t.id WHERE t.id = ? FOR UPDATE');
                $statement->execute([$id]);
                $row = $statement->fetch();
                if (!$row) {
                    throw new InvalidArgumentException('Tenant not found.');
                }
                if (!hash_equals(self::revision($row), $revision)) {
                    throw new InvalidArgumentException('Another update changed this tenant. Reload the page before editing again.');
                }
                if ($data['lifecycle_status'] === 'active' && $row['lifecycle_status'] !== 'active' && !self::canActivate($row, new DateTimeImmutable('now', new DateTimeZone('UTC')))) {
                    throw new InvalidArgumentException('Activation requires an active, provider-verified subscription with a future paid-through date.');
                }
                $oldPlan = isset($row['plan_id']) ? (int) $row['plan_id'] : null;
                if ($oldPlan !== $data['plan_id'] && !empty($row['provider_subscription_reference'])) {
                    throw new InvalidArgumentException('A gateway subscription is linked. Plan changes must be synchronized through billing before saving.');
                }
                $statement = $db->prepare('UPDATE tenants SET business_name = ?, contact_name = ?, contact_email = ?, rental_type = ?, timezone = ?, lifecycle_status = ? WHERE id = ?');
                $statement->execute([$data['business_name'], $data['contact_name'], $data['contact_email'], $data['rental_type'], $data['timezone'], $data['lifecycle_status'], $id]);
                if ($oldPlan !== $data['plan_id']) {
                    $db->prepare('UPDATE subscriptions SET plan_id = ? WHERE tenant_id = ?')->execute([$data['plan_id'], $id]);
                    audit($db, $actor, 'plan.' . ($oldPlan ?? 'none') . '.to.' . ($data['plan_id'] ?? 'none'), $id);
                }
                audit($db, $actor, 'owner.tenant_updated', $id);
                if ($row['lifecycle_status'] !== $data['lifecycle_status']) {
                    audit($db, $actor, 'status.' . $row['lifecycle_status'] . '.to.' . $data['lifecycle_status'], $id);
                }
            } else {
                if ($data['lifecycle_status'] !== 'pending_review') {
                    throw new InvalidArgumentException('New tenants must start pending review.');
                }
                $statement = $db->prepare('INSERT INTO tenants (business_name, contact_name, contact_email, rental_type, timezone) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$data['business_name'], $data['contact_name'], $data['contact_email'], $data['rental_type'], $data['timezone']]);
                $id = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO subscriptions (tenant_id, plan_id) VALUES (?, ?)')->execute([$id, $data['plan_id']]);
                audit($db, $actor, 'owner.tenant_created', $id);
            }
            $db->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
}
