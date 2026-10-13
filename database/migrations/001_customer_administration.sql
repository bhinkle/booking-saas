-- Apply to a NEW local/staging MySQL database only after reviewing the schema.
-- No destructive statements. These are platform customers, not rental customers.
CREATE TABLE tenants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    contact_email VARCHAR(254) NOT NULL,
    rental_type VARCHAR(50) NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
    lifecycle_status VARCHAR(32) NOT NULL DEFAULT 'pending_review',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tenants_contact_email (contact_email),
    CONSTRAINT tenants_lifecycle CHECK (lifecycle_status IN ('pending_review', 'active', 'suspended', 'canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    billing_status VARCHAR(32) NOT NULL DEFAULT 'not_configured',
    provider_subscription_reference VARCHAR(191) NULL,
    paid_through_at DATETIME NULL,
    last_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY subscriptions_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT subscriptions_status CHECK (billing_status IN ('not_configured', 'pending', 'trial', 'active', 'past_due', 'paused', 'canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor VARCHAR(254) NOT NULL,
    action VARCHAR(64) NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    KEY audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_limits (
    bucket CHAR(64) NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
