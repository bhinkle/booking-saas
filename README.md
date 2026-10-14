# Booking Software Platform

Public marketing website for a recreational rental booking platform in development.

## Local preview

From the repository root:

```sh
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080. Serve only `public/`, never the repository root.

The marketing website has no build step or third-party dependencies. Branding is provisional, three monthly plans and setup fees are defined, and the product calendar is illustrative. The marketing inquiry form downloads a text draft locally; it does not send or retain customer information.

A first PHP/MySQL backend slice provides pending business registration at `/register.php` and owner tenant search at `/admin.php`, with creation, editing, and plan assignment at `/tenant.php`, plus a read-only plan catalog at `/plans.php`. See [backend setup and verification](docs/backend-setup.md) before using it. It requires PHP and MySQL, environment configuration, and a reviewed migration. Payment collection, verified billing, email verification, and tenant provisioning are not implemented yet. Both PHP routes are restricted to explicitly configured local/staging environments. No migration has been executed.

For a marketing-only preview without PHP, open `public/index.html` directly in a browser. Do not serve PHP files through a static server.

See [the phased architecture](docs/architecture.md) for customer registration, owner administration, and verified NMI subscription billing. Read [AGENTS.md](AGENTS.md) before making changes. `legacy-booking/` is currently empty; no existing booking logic has been changed.

Private cPanel configuration is supported through `config.local.php` (copy `config.example.php` outside the public document root and replace dummy values privately). Server environment values override the file. Tenant filters and overview counts are available in the owner directory. No new migration is required beyond 001 and 002.

Expanded signup captures billing contacts, phone, website, requested domain, and a selected plan. Plan buttons lead to registration and a payment summary. Review [registration and billing setup](docs/registration-billing.md); import migration 003 after review/backup. NMI checkout is sandbox-only and disabled until test keys are supplied privately. Customer activation and production payment processing are not enabled.
