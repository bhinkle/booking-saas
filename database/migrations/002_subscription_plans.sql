-- Review and import once AFTER 001_customer_administration.sql.
-- USD is the provisional currency; confirm it before enabling billing.
-- Plan records describe the intended offer. Purchasing remains disabled.
CREATE TABLE plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(100) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    monthly_amount_minor INT UNSIGNED NOT NULL,
    setup_amount_minor INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    purchasing_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (code, name, monthly_amount_minor, setup_amount_minor, description) VALUES
('starter', 'Starter', 29900, 49900, 'Website, booking engine, hosting, admin dashboard and basic email notifications.'),
('professional', 'Professional', 49900, 99900, 'Everything in Starter, plus iPhone app, SMS alerts, calendar, reports and priority support.'),
('growth', 'Growth', 89900, 149900, 'Everything in Professional, plus managed Google Ads, SEO basics and monthly marketing reports. Google Ads spend is separate.');

ALTER TABLE subscriptions
    ADD COLUMN plan_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT subscriptions_plan_fk FOREIGN KEY (plan_id) REFERENCES plans(id);

-- Existing/new subscriptions keep plan_id NULL until explicit assignment.
-- Assigning a plan must not change verified payment or activation status.
