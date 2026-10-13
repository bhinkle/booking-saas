-- Select your newly created database before running these read-only checks.
SELECT DATABASE() AS selected_database, VERSION() AS server_version;

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('tenants', 'subscriptions', 'audit_logs', 'request_limits', 'plans')
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('tenants', 'subscriptions', 'audit_logs', 'request_limits', 'plans')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

SELECT code, name, currency, monthly_amount_minor, setup_amount_minor, purchasing_enabled
FROM plans ORDER BY monthly_amount_minor;
