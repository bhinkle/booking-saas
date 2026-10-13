# Booking SaaS Platform

Public marketing website for a recreational rental booking platform in development.

## Local preview

From the repository root:

```sh
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080. Serve only `public/`, never the repository root.

The marketing website has no build step or third-party dependencies. Branding is provisional, three monthly plans and setup fees are defined, and the product calendar is illustrative. The marketing inquiry form downloads a text draft locally; it does not send or retain customer information.

A first PHP/MySQL backend slice provides pending business registration at `/register.php` and owner tenant search at `/admin.php`, with creation and editing at `/tenant.php`. See [backend setup and verification](docs/backend-setup.md) before using it. It requires PHP and MySQL, environment configuration, and a reviewed migration. Payment collection, verified billing, email verification, and tenant provisioning are not implemented yet. Both PHP routes are restricted to explicitly configured local/staging environments. No migration has been executed.

For a marketing-only preview without PHP, open `public/index.html` directly in a browser. Do not serve PHP files through a static server.

See [the phased architecture](docs/architecture.md) for customer registration, owner administration, and verified NMI subscription billing. Read [AGENTS.md](AGENTS.md) before making changes. `legacy-booking/` is currently empty; no existing booking logic has been changed.
