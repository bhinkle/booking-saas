# Local/staging customer administration

This first backend slice includes business-interest registration, an owner login, paginated customer search, and separate account and billing status displays. Registration creates a pending tenant and an unconfigured subscription atomically. It does not create an operator login, send email, provision a booking site, or charge a card.

## Requirements and setup

1. Use supported PHP 8.x with PDO MySQL and a local MySQL 8.x database. No PHP or database packages have been installed by this change.
2. Review `database/migrations/001_customer_administration.sql` before manually applying it to a **new local or staging database**. No migration has been executed. If existing tables have matching names, stop and adapt the migration rather than overwriting them.
3. Supply the variables in `.env.example` through the PHP server environment. `.env` files are deliberately not auto-loaded. Use separate migration credentials; runtime access needs SELECT/INSERT on customer, subscription, and audit tables and SELECT/INSERT/UPDATE on request_limits.
4. Generate the owner password hash with PHP's `password_hash()` using an interactive password prompt or a trusted password tool; do not put the password or resulting hash in Git or command history. Supply the hash as `OWNER_PASSWORD_HASH` and the owner address as `OWNER_EMAIL`.
5. Set `APP_ENV=local` or `staging`. For HTTPS set `SESSION_SECURE=1`; only local HTTP uses `0`.
6. Start from the repository root with `php -S 127.0.0.1:8080 -t public`. Open `/register.php` to register interest and `/admin.php` to sign in as the configured owner. Python's static server previews only the marketing page and must not be used to serve PHP backend files.

The owner console fails closed for environments other than local/staging. Registration must also remain private until privacy/terms, email verification, and launch gates have been completed. Use network-restricted staging, not a publicly accessible production deployment.

## Verification required before use

The implementation environment does not have PHP or a running MySQL instance, so backend execution and SQL compatibility remain unverified. Run PHP syntax checks and verify these behaviors with a disposable MySQL database:

- Registration creates exactly one pending tenant and unconfigured subscription; duplicate emails do not reveal account existence or create duplicates. Database failures roll back both writes.
- Invalid inputs and CSRF tokens are rejected; repeated registration/login attempts receive 429.
- Owner authentication rejects invalid credentials, regenerates session IDs, expires after 30 minutes idle, and protects the customer list.
- Search escapes wildcard characters, paginates consistently, and safely renders names containing HTML.
- Logout requires POST and CSRF; secret values and SQL errors are not displayed.
- Billing stays unverified; paid-through dates are never invented or set from a browser redirect.

## Remaining backend work

This is a foundation, not a complete customer/payment management system. Add authenticated owner roles with MFA, email verification and invitations, editable customer records with audits, approved plans, and NMI hosted/tokenized collection. Verify the selected NMI API and webhook mechanism before implementing billing events, reconciliation, verified payments, dunning, and provisioning. Owner identity is currently a single configured credential, not a full users/roles implementation.

Add automated PHP/MySQL integration tests before expanding behavior or deploying. Define trial, currency, pricing, cancellation, refund, and grace-period rules with the owner. Separate platform subscriptions from tenants' rental payments. Add bounded cleanup for rate-limit/audit retention, environment-aware structured logging, alerts, backups, and restoration verification.
