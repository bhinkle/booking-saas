# Marketing website and customer administration

## Scope

The first deliverable is a public advertising website. The owner also needs a backend to manage businesses subscribing to the software: registration, customer accounts, payment setup, verified billing status, and provisioning. End renters are a separate customer domain belonging to each tenant.

The initial repository contained no legacy application, schema, Composer dependencies, or backend. The marketing website uses dependency-free HTML, CSS, and JavaScript in `public/`. Branding is temporary and three monthly plans and setup fees are defined. Product capabilities are presented as planned, not shipped. The illustrative calendar uses fictional data. The marketing inquiry form downloads a local draft; it does not collect leads or register an account. A separate PHP/MySQL foundation now adds pending-interest registration and an authenticated owner customer list. See `backend-setup.md` for its setup, limitations, and outstanding verification.

## Next phase: owner backend

Use supported PHP 8.x, Composer PSR-4 loading, PDO, and MySQL. Expose only `public/`; owner screens and `/api/v1` routes must run through public entry points rather than expose private repository directories. Do not select or install dependencies until the backend implementation scope is reviewed.

1. Establish configuration, migrations, authentication, CSRF protection, session security, owner roles, MFA, and audit logs.
2. Add pending business registration with email verification, unique account/domain validation, rate limits, and owner customer search/detail screens. Do not activate accounts from registration alone.
3. Add plans, subscriptions, entitlement and provisioning records. Keep business lifecycle, billing lifecycle, and provisioning status separate.
4. Implement NMI through a `BillingGateway` interface, initially using a deterministic fake gateway for local testing. Verify the specific NMI product's tokenization, webhook authenticity, and recurring billing contract before its real adapter.
5. Collect hosted/tokenized payment information, create the provider subscription, verify its state server-side, and provision the tenant idempotently. Never store PAN/CVV or activate from a browser success redirect.
6. Process authenticated provider events idempotently and periodically reconcile provider records. An accepted subscription request is not necessarily a settled payment. Record payment/invoice outcomes and paid-through dates so the owner can distinguish pending, failed, paid, past-due, and canceled accounts.
7. Add permissioned owner operations, invitations, retry recovery, and explicit suspension rules. Suspending service must retain reservation history.

## Proposed platform entities

- `tenants`: stable ID, business name, lifecycle status, business timezone.
- `users`, `tenant_users`, `roles`, `permissions`: platform owners and tenant members with explicit role checks.
- `tenant_domains`: globally unique normalized hostname, verification and provisioning status.
- `plans`, `plan_versions`: approved pricing, currency, billing interval, versioned entitlements.
- `subscriptions`: tenant and plan references, provider reference, billing state, period dates, cancellation policy fields.
- `billing_payment_methods`: provider token references and permitted masked metadata only.
- `subscription_events`: deduplicated provider events and processing outcomes; avoid retaining sensitive payloads.
- `invoices`, `subscription_payment_transactions`: amounts in minor units, currency, provider references, verified outcomes and timestamps.
- `tenant_features`, `provisioning_jobs`: entitlement state and retryable provisioning steps.
- `audit_logs`: actor, authorized operation, target, timestamp, and redacted context.

Enforce tenant ownership through services/repositories and composite foreign keys where appropriate. UTC timestamps, explicit business timezones, foreign keys, unique constraints, and indexes are required. Rental payment transactions and merchant credentials must remain separate from platform subscription accounting.

## Decisions before live billing

Confirm product name, currency, trial policy, billing intervals, refund/cancellation rules, payment-failure grace period, supported NMI configuration, sender identity, and privacy/terms content. No production, AWS, domain, merchant, or database actions are part of this phase.

## Validation gates

- Marketing: responsive layouts, keyboard navigation, valid links, form validation, local download, and accurate development disclosures.
- Registration/authentication: verification expiry, duplicate/racing signup, CSRF, rate limits, secure sessions, permission checks, and MFA.
- Billing: pending versus paid outcomes, fake gateway failures, retries, duplicate/out-of-order events, subscription transitions, reconciliation, and no activation from unverified redirects.
- Isolation: cross-tenant read/write attempts and owner-only billing operations.
- Operations: failed provisioning recovery without duplicate side effects, secret redaction, staging checks, and tested backup restoration.

## Defined subscription plans

| Plan | Monthly subscription | Setup fee | Includes |
| --- | --- | --- | --- |
| Starter | $199 | $499 | Website, booking engine, hosting, admin dashboard, basic email notifications |
| Professional | $399 | $999 | Everything in Starter, plus iPhone app, SMS alerts, calendar, reports, priority support |
| Growth | $799 | $1,499 | Everything in Professional, plus managed Google Ads, SEO basics, monthly marketing reports |

These prices and inclusions were supplied by the owner. Currency, taxes, setup-fee collection timing, service limits, and cancellation terms remain to be confirmed before configuring billing. Google Ads spend is separate from the Growth subscription fee. Features are planned while the platform is in development; the iPhone app must not be advertised as already available. No NMI plans or charges have been created.

## Proposed merchant processing offer

The owner requested a program intended to eliminate eligible merchant credit-card processing costs through a 3% consumer surcharge. Marketing presents this as an optional, proposed program subject to processor/acquirer approval, applicable law, and card-network limits. It does not guarantee zero total merchant costs; debit/prepaid processing and other charges may remain. A lower rate may be required, and debit/prepaid transactions must never be surcharged. No surcharge calculation or payment configuration has been implemented.

Before enabling: confirm jurisdictions, processor pricing and approval, supported card-type identification, permitted rates, consumer disclosures, receipts, and refund treatment. Review the specific network contracts and obtain legal review. Keep this rental merchant program separate from platform subscription billing.

Reference checked for marketing qualification: [Visa U.S. Merchant Surcharge Q&A](https://usa.visa.com/content/dam/VCOM/global/support-legal/documents/merchant-surcharging-qa-for-web.pdf). This is not an approval of this program.
