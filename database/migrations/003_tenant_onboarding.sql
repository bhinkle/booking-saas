-- Import once after 001 and 002. Review/backup the database first.
-- Adds new tables only; existing customer records are preserved.
CREATE TABLE tenant_onboarding (
    tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    phone VARCHAR(32) NOT NULL,
    existing_website VARCHAR(2048) NULL,
    preferred_domain VARCHAR(253) NULL,
    billing_name VARCHAR(150) NOT NULL,
    billing_email VARCHAR(254) NOT NULL,
    address1 VARCHAR(150) NOT NULL,
    address2 VARCHAR(150) NULL,
    city VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country CHAR(2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_signup_attempts (
    id CHAR(32) NOT NULL PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    monthly_amount_minor INT UNSIGNED NOT NULL,
    setup_amount_minor INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'ready',
    next_billing_date DATE NULL,
    provider_transaction_reference VARCHAR(191) NULL,
    provider_subscription_reference VARCHAR(191) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY signup_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT signup_status CHECK (status IN ('ready', 'processing', 'declined', 'review_required', 'approved_pending_review'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
