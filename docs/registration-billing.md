# Registration and platform billing

## Install

Back up the database and review/import `003_tenant_onboarding.sql` once after 001 and 002. It creates `tenant_onboarding` and `billing_signup_attempts`; no SQL has been executed by Codex. No old customer records are deleted. Keep a backup and, on failed DDL, inspect the existing tables rather than blindly rerunning or dropping them. The runtime user needs SELECT/INSERT on tenant_onboarding and SELECT/INSERT/UPDATE on billing_signup_attempts.

Marketing plan buttons open `/register.php?plan=starter`, `professional`, or `growth`. Registration captures business/contact information, phone, existing website, preferred domain, timezone, selected plan, and billing contact/address. Domain requests are not verified, purchased, or provisioned. Records are committed atomically with a pending tenant, subscription, price snapshot, and signup attempt. Duplicate emails cannot take over an existing customer's checkout. Payment summary access requires the browser session that created the registration. No account password/login or email verification is implemented for customers yet.

The owner's tenant detail page shows the captured fields and signup payment state.

## Approved billing model

Initial charge: setup plus first month. Then a separate recurring monthly subscription. Base amounts in the current USD catalog:

| Plan | Initial | Subsequent monthly |
| --- | --- | --- |
| Starter | $798 | $299 |
| Professional | $1,498 | $499 |
| Growth | $2,398 | $899 |

Monthly anniversary scheduling clamps the first renewal to the last day of a shorter month. No rental-transaction surcharge is applied to platform subscription charges. Taxes, cancellation/refund policy, customer terms, and email verification remain launch decisions.

## NMI prototype

This implements the documented Classic Payment API recurring module with Collect.js tokenized fields, without adding a frontend build system. It is a sandbox prototype: the backend endpoint is fixed to `https://sandbox.nmi.com/api/transact.php` and no production switch exists. No gateway requests were sent during development. NMI's newer recommendation is its Payment Component and v5 REST API; review the account's enabled API version before production and decide whether to migrate this adapter.

Private configuration slots: `NMI_SANDBOX_ENABLED` (default 0), `NMI_SECURITY_KEY`, `NMI_TOKENIZATION_KEY`. Use platform-billing sandbox keys only. Supply keys privately outside the public web root; do not commit them. PHP cURL is required. The checkout page does not load gateway scripts until explicitly enabled and configured. Register first with payments disabled to verify record capture. `plans.purchasing_enabled` remains a production catalog flag; this sandbox harness never enables real purchasing.

The request combines an immediate sale with custom recurring setup, a monthly-only recurring amount, and a first recurring date one month later. The adapter consumes a one-use payment token in memory and persists only provider references. It never stores raw card data, a reusable payment token, or raw gateway responses.

Before a gateway call, the attempt atomically changes from ready to processing. Duplicate/replayed submits cannot create another charge. Timeouts, malformed results, missing provider references, and crashes remain blocked for manual provider review. There is no automatic charge retry or automatic refund. Declined attempts also require support recovery. Approved requests are recorded as approved_pending_review; billing is not marked verified and tenants remain pending. No activation from redirects.

## Required before public payment launch

PHP/MySQL execution and hosted-field browser behavior are unverified here. Test registration validation, plan preselection, CSRF, duplicate signup, transaction rollback, checkout session ownership, double submits, decline/timeout recovery, and monthly end-of-month boundaries with sandbox credentials. Confirm the provider response reference fields and the combined sale/subscription behavior against your account. Verify CSP and secure HTTPS cookies.

Implement verified webhook handling, provider reconciliation, failed-payment policy, invoice/receipt delivery, email verification and tenant provisioning before activating customers. A sandbox approval is not payment settlement. Both registration and checkout remain local/staging restricted. MFA remains a gate for production owner access.

Sources reviewed: [NMI recurring module](https://support.nmi.com/hc/en-gb/articles/14525725002385-API-Recurring-Payments-and-Subscriptions), [Payment API transaction fields](https://docs.nmi.com/reference/transactions-processing), [Collect.js](https://docs.nmi.com/docs/quick-start-tutorial), and [current Payment Component recommendation](https://docs.nmi.com/docs/payment-component).
