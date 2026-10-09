# PlugTheList

Nigerian marketplace where playlist, channel and community owners (**curators**) list what they sell and set their own prices, and artists, labels and businesses (**creatives**) book them. Every booking is paid into **escrow** and released only when the work is approved. Naira only. Plain PHP 8.1+ and MySQL. No framework, no build step.

## Layout (one shared root, six subdomains)

```
plugthelist/               <- upload to your home folder, NOT inside public_html
  shared/                  core ("the server"): security, auth, escrow, ledger, Paystack, views, legal text
  secrets/.env             your private settings (outside every web root)
  storage/                 logs, uploads, caches (outside every web root, must be writable)
  database/schema.php      portable schema
  bin/                     migrate.php, create_admin.php, check.php (command line only)
  www/    -> plugthelist.com           public site, browse, listings, legal pages, static assets
  auth/   -> auth.plugthelist.com      sign in, both sign-up pages, email verify, reset, 2FA
  curator/-> curator.plugthelist.com   playlist owners: listings, orders, wallet, withdrawals
  app/    -> app.plugthelist.com       artists and businesses: bookings, payments
  admin/  -> admin.plugthelist.com     listing review, disputes, withdrawals, users, settings, audit
  api/    -> api.plugthelist.com       Paystack webhook, cron, health
  tests/                   run.php (engine checks), e2e.sh (full HTTP run). Not needed on the server.
  docs/                    deployment, security, legal checklist
```

One login on `auth.` works on every subdomain (a single secure cookie on `.plugthelist.com`; each subdomain re-checks the session in the database). Curators and creatives can only open their own area; admin needs two-step verification.

## Quick start

See `docs/DEPLOYMENT.md`. In short: create 6 subdomains pointing at the 6 folders, create a MySQL database, copy `secrets/.env.example` to `secrets/.env` and fill it in, run `php bin/migrate.php`, `php bin/create_admin.php you@email "Your Name"`, `php bin/check.php`, add the Paystack webhook and the cron job.

## Tests

```
php tests/run.php     # 75 engine checks: money, fees, escrow, disputes, refunds, ledger, withdrawals, auth, privacy
bash tests/e2e.sh     # 81 checks over real HTTP across all six subdomains, with a mock Paystack
```
