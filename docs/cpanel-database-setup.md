# Create the customer-management database

The current schema manages businesses subscribing to the software. It is separate from future rental customer/booking records. Use a new, empty database for private staging first. No SQL has been executed by Codex.

## 1. Create the database and user in cPanel

1. Open **MySQL Database Wizard** or **Manage My Databases** (the label depends on your host).
2. Create a database with suffix `booking_saas`. Record the full name cPanel shows, such as `youraccount_booking_saas`.
3. Create a separate database user with a strong generated password. Store it privately, outside Git and the public web directory.
4. Associate the user with the new database. Use a setup/migration user with the privileges required to create tables, foreign keys, and indexes. A separate runtime user needs only the permissions listed below.

Do not import into an existing booking database. cPanel usually requires database creation through its interface; `database/create-local-database.sql` is for local MySQL only.

## 2. Import the tables

Open **phpMyAdmin**, select the full database name in the sidebar, choose **Import**, and upload:

`database/migrations/001_customer_administration.sql`

Then import `database/migrations/002_subscription_plans.sql` to create and seed the three plans and add the nullable subscription plan reference. Run each migration once, in this order. Together they create five InnoDB tables using utf8mb4:

| Table | Purpose |
| --- | --- |
| `tenants` | Business details, contact details, rental type, timezone, account status |
| `subscriptions` | Separate billing status and provider-verification fields |
| `audit_logs` | Owner activity and tenant lifecycle changes |
| `request_limits` | Registration and owner-login rate limits |
| `plans` | Starter, Professional, and Growth prices and setup fees |

The SQL creates no sample customers, passwords, or payment credentials. Plan monthly prices are $299/$499/$899, with $499/$999/$1,499 setup fees, stored as integer minor units. USD is provisional and needs confirmation before billing. All plans start with purchasing disabled. The admin tenant editor can assign plans without charging or activating an account. No NMI subscriptions are created.

MySQL DDL can commit table creation independently. If import fails partway through, note the error and which tables exist; do not blindly rerun the entire script or delete existing tables. Review the failure before continuing. There are no DROP, DELETE, or TRUNCATE statements.

## 3. Verify the import

With the new database selected, run `database/verify-customer-tables.sql` in phpMyAdmin's SQL tab. Expect five tables with InnoDB and utf8mb4, three disabled plan records, plus primary keys, unique constraints, and foreign keys. Record the server version: some cPanel hosts use MariaDB rather than MySQL, so compatibility must be checked on that specific server.

## 4. Configure PHP privately

The PHP runtime must have PDO MySQL enabled. Configure these environment variables using the full cPanel database/user names:

```text
APP_ENV=staging
DB_DSN=mysql:host=localhost;port=3306;dbname=youraccount_booking_saas;charset=utf8mb4
DB_USER=youraccount_booking_app
DB_PASSWORD=<your privately stored database password>
OWNER_EMAIL=<your owner email>
OWNER_PASSWORD_HASH=<a PHP password_hash result>
SESSION_SECURE=1
```

Use the database host supplied by your hosting provider if it differs from `localhost`. Environment variables must be available to PHP itself, not only to cPanel deployment commands. `.env.example` is a reference; copying it to `.env` will not load it automatically in the current application. Do not paste credentials into chat or put them in SQL, public files, or Git. A private configuration loader is now included: copy `config.example.php` to `config.local.php` at the repository root, outside the public document root, and replace the placeholders privately. PHP server environment settings take precedence.

The runtime database user needs:

- `tenants`: SELECT, INSERT, UPDATE
- `subscriptions`: SELECT, INSERT, UPDATE
- `audit_logs`: SELECT, INSERT
- `request_limits`: SELECT, INSERT, UPDATE
- `plans`: SELECT

Keep SQL files and `src/` outside the public document root. Only `public/` should be served. Leave the admin backend behind staging access controls while its MFA and runtime testing are incomplete.

## 5. Check customer management

Visit `/admin.php` on the configured private staging site, sign in, and add a fictional test tenant. Confirm it has account status **pending review** and billing status **not configured**. Test editing and searching, then verify activity entries. The console must reject activation without verified billing. No cards are collected or charged.

PHP/MySQL execution remains unverified in the development workspace. Importing the tables alone does not configure server credentials, deploy private PHP source, or complete authentication/billing launch requirements. See `backend-setup.md` for the remaining checks.
