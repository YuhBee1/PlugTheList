# PlugTheList

PlugTheList is a Nigerian marketplace for playlist, channel, and community owners (**curators**) to list promotional services, and for artists, labels, and businesses (**creatives**) to book them. Payments are handled in Nigerian naira through an escrow workflow: funds are held until the work is approved, with support for refunds, disputes, curator balances, and withdrawals.

The project is implemented in plain **PHP 8.1+** and **MySQL**. It has no application framework and no frontend build step. It is designed to run on a PHP-enabled host with a relational database, HTTPS, writable private storage, email delivery, and Paystack credentials.

> **Status:** This repository contains application source and deployment guidance. A public Git repository does not mean the hosted app is configured or production-ready. No live credentials or production database are included. Review the security and launch checklists before operating a real marketplace.

## Features

- Separate public, authentication, curator, creative, administrator, and API areas.
- Curator and creative account registration, email verification, password reset, and two-factor authentication for administrators.
- Curator listings with moderation and public browsing.
- Booking, Paystack checkout and webhook handling, escrow state transitions, approvals, refunds, disputes, and ledger accounting.
- Curator wallet, bank details, and administrator-reviewed withdrawals.
- CSRF protection, request validation, rate limiting, secure sessions, audit records, and privacy export/erasure tools.
- PHP engine tests and HTTP end-to-end tests using a mock payment flow.

## Architecture

The default hosting layout uses one shared private application directory and six separately configured web roots/hostnames. For Vercel, the included FrankenPHP container instead routes the same areas through one hostname using path prefixes such as `/auth/`, `/curator/`, `/app/`, `/admin/`, and `/api/`.

```text
plugthelist/
├── shared/       Common bootstrap, libraries, views, and legal content
├── secrets/      Private runtime environment file (not committed)
├── storage/      Private logs, rate-limit data, uploads, and runtime files
├── database/     Portable schema and migrations
├── bin/          Command-line setup, migration, and health-check utilities
├── www/          Public site, browsing, listings, legal pages, and assets
├── auth/         Sign-in, registration, verification, reset, and 2FA
├── curator/      Curator dashboard, listings, orders, wallet, withdrawals
├── app/          Creative dashboard, bookings, and payments
├── admin/        Moderation, disputes, withdrawals, users, and audit tools
├── api/          Webhooks, cron endpoint, and health endpoint
├── tests/        Engine and HTTP end-to-end tests
└── docs/         Deployment, security, legal, and virtual-host guidance
```

The included `docs/DEPLOYMENT.md` describes the intended multi-subdomain hosting layout, Apache/Nginx configuration, database setup, Paystack webhook, and cron job. Read it together with `docs/SECURITY.md` before deployment.

## Requirements

- PHP 8.1 or newer, with the extensions required by the deployment guide (including PDO, `pdo_mysql`, cURL, mbstring, sodium, GD, and OpenSSL).
- MySQL with a database and user dedicated to this application.
- Apache with rewrite support, or Nginx/PHP-FPM configured to reproduce the documented routes.
- HTTPS and working DNS for the six application hostnames.
- An SMTP provider for production email delivery.
- Paystack account and API credentials for payment and transfers. Use test keys during integration testing.
- A scheduler/cron service for expiry, refund, release, and cleanup jobs.

## Installation overview

A production deployment requires server-level configuration and credentials. Do not expose `shared/`, `secrets/`, `storage/`, `database/`, or `bin/` as public web roots. In particular, do not serve the project root directly.

1. Place the project in a private directory outside the web server's public document root.
2. Configure six hostnames to point to the `www/`, `auth/`, `curator/`, `app/`, `admin/`, and `api/` directories as described in `docs/DEPLOYMENT.md`.
3. Create a MySQL database and a least-privilege database account.
4. Copy `secrets/.env.example` to `secrets/.env`, restrict its permissions (for example, `chmod 600`), and fill in values for the environment. Generate an `APP_KEY` as described in the deployment guide. Never commit the populated `.env` file.
5. Run the schema migration and create an initial administrator from the command line:

   ```sh
   php bin/migrate.php
   php bin/create_admin.php you@example.com "Your Name"
   php bin/check.php
   ```

6. Configure SMTP, the Paystack webhook URL, and the scheduled cron command. Enable and verify administrator two-factor authentication before opening admin access.
7. Exercise the full flow with Paystack test credentials and a staging database before using any live keys or real payments.

These steps are an overview, not a substitute for the complete deployment guide. Never copy production credentials into issue reports, pull requests, chat, or source control.

## Configuration and secrets

`secrets/.env.example` documents the available settings. It is a template, not a working configuration. Important values include:

- Application environment, base domain, cookie domain, and URL scheme.
- A strong `APP_KEY` used to protect sensitive stored values. Keep it stable after deployment and store backups securely.
- Database connection settings.
- Paystack secret and optional webhook source-IP restrictions.
- A random cron token for the HTTP cron fallback (not needed for CLI cron).
- SMTP credentials and sender identity.
- Support/privacy contact addresses and legal-page draft setting.

The real `secrets/.env` is ignored by Git. Keep all runtime secrets in the host's protected environment or private secret store. Rotate any credential that was accidentally committed or disclosed.

## Tests

Run the self-contained engine suite and the HTTP end-to-end suite in a development environment:

```sh
php tests/run.php
bash tests/e2e.sh
```

The included suites currently exercise 78 engine checks and 82 HTTP checks, including escrow/ledger invariants, authentication, access control, CSRF, listings, payment callbacks/webhooks, withdrawals, privacy, Vercel path URLs, and bearer-token cron authentication. The end-to-end suite uses an isolated local test setup and mock payment behavior; it does not validate live Paystack, SMTP, DNS, or production-host settings.

## Deployment compatibility

The repository has two distinct Vercel deployments. The existing `plugthelist` project uses `Dockerfile.vercel` and FrankenPHP; Caddy sends `/auth/`, `/curator/`, `/app/`, `/admin/`, and `/api/` to their PHP roots, while the repository-root `index.html` provides a database-independent test page. Keep this PHP project protected by Vercel SSO. The root test page contains no live listings, fee settings, or payment flow.

The separate public design preview is at [plugthelist-preview.vercel.app](https://plugthelist-preview.vercel.app/), with its source in `preview-static/`. Its Vercel project uses this same GitHub repository with **Root Directory** set to `preview-static`. It contains only a static landing page and public assets; it does not package the PHP application, private routes, secrets, uploads, or database. Deployment protection is disabled only for this static-only preview project; do not disable SSO for the PHP marketplace project. The preview uses `noindex` so it can be shared for review without being treated as the production search listing.

For the PHP project, set these environment variables in Vercel (never commit them):

- `APP_ROUTING_MODE=path`
- `APP_BASE_URL=https://plugthelist.vercel.app` (or the stable domain actually assigned to this project; do not include a path)
- `URL_SCHEME=https`
- `APP_KEY`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `PAYSTACK_SECRET`, `CRON_TOKEN`, and SMTP values (`MAIL_DRIVER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, and sender settings)

Use a durable, externally reachable MySQL service and run `php bin/migrate.php` against it before enabling marketplace workflows. Keep `COOKIE_DOMAIN` unset for Vercel's single-host routing; the app switches to a host-only cookie in path mode. Configure the Paystack webhook at `/api/webhook` and a scheduler to call `/api/cron` with either `X-Cron-Token: <CRON_TOKEN>` or `Authorization: Bearer <CRON_TOKEN>`.

**The container is a deployment adapter, not a complete production provisioning step.** Vercel's container filesystem is ephemeral, while uploaded listing/proof/delivery images currently use local `storage/uploads`. Do not rely on those uploads persisting across container restarts or scaling. A durable object-storage adapter and credentials are required before using uploads in production. Database access, SMTP, Paystack, and cron secrets also need to be configured and tested in the PHP project. No live credentials or external database are included in this repository.

## Security and responsible operation

- Never use real payment keys or customer data in tests.
- Keep environment files, uploads, logs, database files, and backups outside public web roots.
- Use HTTPS, strong unique administrator passwords, two-factor authentication, and least-privilege database credentials.
- Verify webhook signatures and test idempotency and failure handling before launch.
- Keep PHP and server dependencies patched; restrict administrative access and monitor audit logs.
- Review legal text and local regulatory obligations with qualified professionals before conducting business.
- See `docs/SECURITY.md`, `docs/DEPLOYMENT.md`, and `docs/LEGAL-CHECKLIST.md` for project-specific checklists.

## Contributing

Contributions are welcome. Before opening a pull request:

1. Open an issue for substantial changes so the approach can be discussed.
2. Avoid including credentials, personal data, production logs, or real payment information.
3. Keep changes focused and add/update tests for behavior changes, especially payment, ledger, privacy, and authorization paths.
4. Run both test commands and report their results in the pull request.
5. Document any new environment variables, migrations, scheduled jobs, or deployment requirements.

Please do not submit changes that bypass payment verification, authorization checks, auditability, or ledger invariants.

## License

This project is licensed under the **MIT License**. See [`LICENSE`](LICENSE) for the full license text and copyright notice.

The MIT License permits use, copying, modification, merging, publication, distribution, sublicensing, and sale of copies, provided the copyright and permission notice are included with substantial portions of the software. The software is provided **“as is,” without warranty or liability** as set out in the license text.

The project license does not replace the licenses or notices of third-party dependencies or externally supplied materials; review those terms separately before redistribution.
