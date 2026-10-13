# Booking SaaS — Codex Project Instructions

## Mission
Build a commercial, subscription-based, multi-tenant booking platform. Launch for jet ski, boat, pontoon, golf cart, ATV, and other recreational rental operators, while keeping inventory and scheduling flexible enough to support additional booking industries later.

The product includes:
- A public marketing website that sells the platform and explains plans.
- A super-admin console for the SaaS owner to manage tenants, plans, subscriptions, provisioning, domains, support, and operational reporting.
- Branded customer-facing booking websites and checkout.
- A tenant-admin dashboard with an availability calendar, reservations, resources, customers, staff, payments, and reporting.
- Versioned APIs for a future shared iPhone operator app (Expo / React Native).
- Optional merchant processing onboarding and configurable, compliant payment pricing programs.
- Optional services such as hosting, email, and Google Ads management.

## Ground rules
1. **Inspect first.** Preserve and assess existing jet ski booking code in `legacy-booking/`; do not rewrite working logic without a clear reason and approval.
2. **Do not touch production** systems, live customer data, merchant accounts, domains, or AWS resources unless explicitly asked and authorized.
3. **Protect secrets.** Never add credentials, `.env`, API keys, token values, customer payment data, or production database dumps to Git. Use `.env.example` with dummy values. Keep real secrets outside the repo.
4. **Small, reviewable changes.** Before substantial changes, provide a short plan. Afterward, report changed files, tests run, and any follow-up or migration steps.
5. **No surprise dependencies or rewrites.** Prefer minimal, maintained Composer packages; ask before adding a full-stack framework, replacing the stack, or introducing substantial infrastructure.
6. **No claims of compliance by default.** Implement payment-related features only to the approved processor/card-network/legal requirements. Escalate uncertain requirements for review.
7. **Never execute irreversible schema changes** or destructive scripts without explicit approval, a backup strategy, and a rollback plan.

## Preferred technology
- Backend: supported PHP 8.x, modern object-oriented PHP, Composer, PSR-4 autoloading and applicable PSR conventions.
- Database: MySQL 8.x, InnoDB, utf8mb4, PDO prepared statements. Target a currently supported MySQL version at deployment time.
- Web: server-rendered PHP where practical, HTML5, modern JavaScript, and Bootstrap; avoid unnecessarily complex front-end tooling.
- Calendar: an actively maintained calendar UI library, subject to license review.
- API: JSON REST endpoints under `/api/v1` with a documented OpenAPI contract.
- Mobile (later): Expo / React Native consuming the same APIs.
- Infrastructure: AWS, HTTPS, isolated staging and production environments, scheduled backups and logs.
- Billing: NMI recurring subscription integration for billing SaaS tenants; keep rental-transaction payments separate.
- Testing: PHPUnit (or another agreed maintained runner), plus integration tests for booking concurrency, NMI sync, permissions, and isolation.

**Lifecycle principle:** Plain PHP avoids dependency on a single full-stack framework but does not eliminate upgrades. Keep PHP, MySQL, packages, and mobile SDKs current through incremental updates, CI tests, and staging deployments.

## Proposed repository organization
Adapt this structure after analyzing the existing app; don't relocate working files prematurely.

```text
booking-saas/
  AGENTS.md
  README.md
  .env.example
  composer.json
  public/              # web entry points, marketing site assets
  src/
    Controllers/
    Services/
    Repositories/
    Domain/
    Middleware/
    Integrations/NMI/
  templates/           # tenant site themes and server-rendered views
  admin/               # owner / super-admin UI
  merchant/            # tenant administration UI
  api/v1/              # API routing / endpoints
  database/migrations/
  database/seeds/
  tests/
  docs/
  legacy-booking/      # copied existing code for assessment
  mobile/              # future Expo app, not required for MVP
```

Web-facing servers must expose only the intended public directory. Never expose `.env`, `vendor/`, `src/`, `database/`, `legacy-booking/`, or private storage publicly.

## Architecture & tenancy
- Model **tenant/business** as a first-class entity; distinguish the SaaS platform owner, tenant business, staff user, and end renter.
- Use stable primary keys and explicit tenant scoping (`tenant_id`) on tenant-owned records. Enforce authorization and tenant checks in services/repositories, not only in UI or request parameters.
- Start with a shared MySQL database and tenant-scoped rows only if isolation controls and tests are robust. Reassess per-tenant databases if security/operational needs require them.
- Support custom domains/subdomains and tenant branding; validate hostname-to-tenant resolution and prevent domain takeover.
- Keep bookings, resources, pricing, business hours, buffers, blackout dates, taxes, and policies configurable per tenant.
- Keep billing status, features/entitlements, and provisioning independent from booking records. Suspending SaaS service must not destroy booking history.
- All sensitive actions require authenticated, role-checked access and audit logging; never rely on client-supplied `tenant_id` alone.
- Use database transactions and row-level locking or equivalent concurrency controls to prevent overlapping bookings. Account for time zones and daylight-saving changes.

## Key domains and initial entities
The exact schema must follow the legacy assessment. Likely entities:
- `tenants`, `tenant_domains`, `tenant_settings`, `tenant_features`
- `users`, `tenant_users`, `roles`, `permissions`, `audit_logs`
- `plans`, `subscriptions`, `subscription_events`, `invoices`, `billing_payment_methods` (token references only)
- `resources`, `resource_types`, `availability_rules`, `blackout_periods`, `rate_plans`
- `bookings`, `booking_items`, `booking_status_history`, `customers`, `waivers`
- `payment_transactions`, `refunds`, `merchant_processing_accounts`
- `notification_templates`, `notification_events`, `support_requests`

Use versioned migrations, foreign keys, indexes, unique constraints, and UTC timestamps in storage with explicit business time zones for display and scheduling.

## SaaS billing via NMI
- Distinguish **platform subscription charges** collected from tenants from **rental charges** each tenant collects from its customers. Separate credentials, accounting, gateway configurations, and reconciliation.
- Encapsulate NMI interactions behind a `BillingGateway` interface and a dedicated integration service. Do not scatter NMI calls through controllers.
- Use NMI-hosted/tokenized payment collection when available; **never store PAN, CVV, or raw payment credentials**.
- Store only necessary customer/subscription/transaction references and masked payment metadata where permitted.
- Verify webhook authenticity using the mechanisms supported by the specific NMI product/configuration, store event IDs, process idempotently, and reconcile against provider records periodically.
- Define explicit state transitions for trial, active, past due, paused, canceled, and failed provisioning. Never activate a tenant based solely on an unverified browser redirect.
- Support plan upgrades/downgrades, cancellation policy, retry/dunning, receipts and invoices, and owner-side audit trails after business rules are approved.
- Log provider errors without exposing secrets or payment details.

## Merchant processing, cash discount, and fees
- Merchant processing is an optional, separately onboarded service; each tenant must have the correct processor/gateway account and underwriting approval.
- The proposed **3% cash discount / card pricing program** must **not** be implemented as a universal 3% card fee. Cash discounts, credit surcharges, convenience fees, service fees, debit treatment, and online payment rules differ.
- Build a configuration and disclosure layer, not hard-coded fee assumptions. Require processor/acquirer approval and review of applicable law and network rules before enabling a program.
- Ensure displayed prices, checkout totals, receipts, refunds and reporting reflect the approved program. Never impose a credit surcharge on debit or prepaid transactions.
- Respect PCI scope reduction, tokenization, least privilege, fraud safeguards, and appropriate handling of refunds/chargebacks.

## Website and customer onboarding
- Public marketing site: features, plans, comparison, demo/contact, FAQ, legal/policy pages, and secure signup.
- Signup flow: validate account and domain; create pending tenant; collect tokenized billing details; verify successful subscription setup; provision tenant; invite initial admin; maintain an auditable recovery path for failures.
- Provide a small number of configurable rental-industry themes; avoid building bespoke sites for each customer.
- Hosting, business email and managed Google Ads are provisioned/tracked as service entitlements, with any third-party charges and customer-owned accounts clearly separated.
- Google Ads spend is distinct from any management fee. Never create campaigns or spend ad budgets without explicit authorization.

## Operator dashboard and future mobile app
- Tenant admin: day/week/month calendar, create/edit/cancel bookings, check-in/out, resource blocking, customer lookup, refunds (permissioned), operational notifications, and reporting.
- API design must anticipate role-aware mobile access, paginated endpoints, consistent validation, token/session expiry, and safe background notifications.
- Build the iPhone app after stable web workflows and API contracts; one shared operator app should support multiple businesses securely.

## Security and reliability standards
- Password hashing via supported PHP password APIs; MFA for platform admins; secure, rotating sessions/cookies and CSRF protection for browser actions.
- Server-side validation, output escaping, prepared SQL statements, rate limiting, least-privilege database accounts, safe file uploads and protected secrets.
- Authorization for every object and action; test cross-tenant access attempts.
- Booking/payment writes must be idempotent where possible, especially under retries and webhooks.
- Background jobs for notifications, provisioning, and reconciliation. Capture failures and retries without duplicate side effects.
- Daily backups with tested restoration, monitoring/alerts, structured logs, staging deployment, and documented disaster recovery.
- Add tests for price calculations, tax treatment, timezone boundaries, resource conflicts, failed payments, refunds, and tenant access.

## Development workflow
1. Read this file and `README.md` before proposing changes.
2. Inventory current files and dependencies, and inspect `legacy-booking/` if populated.
3. Document existing booking flows, schema, integrations, and known risk areas in `docs/legacy-assessment.md`.
4. Write a phased architecture/migration proposal in `docs/architecture.md` before any rewrite.
5. Implement one feature at a time, with migrations and tests, on feature branches where practical.
6. Run targeted tests/linting and identify anything not verified.
7. Never commit generated secrets, sensitive test fixtures, live databases, or payment credentials.

## Immediate assignment for Codex
**Read-only assessment first.** Inspect the repository and existing booking application, if present. Report:
- Current PHP/MySQL structure and dependencies.
- Reservation, inventory, pricing, checkout and payment workflows.
- Components that can be reused unchanged.
- Changes needed for tenant isolation and business configuration.
- Recommended SaaS master-admin schema and NMI subscription flow.
- A prioritized MVP plan with risks and test coverage.

Do not edit application code, install dependencies, alter databases, or contact live services in this first task unless explicitly authorized.
