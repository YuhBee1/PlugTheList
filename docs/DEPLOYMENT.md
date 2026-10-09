# Deploying PlugTheList on cPanel (shared hosting)

## 1. Domain and DNS
1. Register `plugthelist.com` (checked available; it is not bought for you).
2. Point the domain's nameservers (or A records) at your host.
3. DNS records (all to the same server IP): `@`, `www`, `auth`, `curator`, `app`, `admin`, `api`. A wildcard `*` also works.
4. Optional but recommended: put the domain behind Cloudflare (free). Then set `TRUST_CLOUDFLARE=1` only if you restrict the origin to Cloudflare IPs.

## 2. Upload
Upload and extract the zip into your **home folder** (`/home/USER/plugthelist`), next to `public_html`, not inside it.

## 3. Subdomains (cPanel → Domains)
Create each with its document root **exactly** as below, and remove the default `public_html` pointing for the main domain:

| Host | Document root |
|---|---|
| plugthelist.com and www.plugthelist.com | `/home/USER/plugthelist/www` |
| auth.plugthelist.com | `/home/USER/plugthelist/auth` |
| curator.plugthelist.com | `/home/USER/plugthelist/curator` |
| app.plugthelist.com | `/home/USER/plugthelist/app` |
| admin.plugthelist.com | `/home/USER/plugthelist/admin` |
| api.plugthelist.com | `/home/USER/plugthelist/api` |

Enable **AutoSSL** (Let's Encrypt) for every one. Choose PHP **8.1 or newer** (8.3 recommended) with extensions `pdo_mysql, curl, mbstring, sodium, gd, openssl`.

Not on cPanel? Use the same mapping in nginx: one `server` block per host with `root` set as above and `try_files $uri $uri.php /index.php?$args;` plus `fastcgi_pass` to PHP-FPM. Copy the rewrite rules from each `.htaccess`.

## 4. Database
cPanel → MySQL Databases: create a database and user, give it ALL privileges. Collation `utf8mb4_unicode_ci`.

## 5. Settings
```
cd ~/plugthelist
cp secrets/.env.example secrets/.env
chmod 600 secrets/.env
php -r "echo base64_encode(random_bytes(32)),PHP_EOL;"     # paste into APP_KEY
```
Fill in database, Paystack live secret key, SMTP details, `CRON_TOKEN`. **Never change APP_KEY after launch** (it protects 2FA secrets and bank numbers). Back up `.env` somewhere safe.

## 6. Create tables and the first admin
```
php bin/migrate.php
php bin/create_admin.php you@example.com "Your Name"     # prints a one-time password
php bin/check.php                                          # tells you what is still wrong
```
Sign in at `https://auth.plugthelist.com/login`, open Account, turn on two-step verification. The admin area stays locked until you do.

## 7. Paystack
- Dashboard → Settings → API Keys & Webhooks: set **Webhook URL** `https://api.plugthelist.com/webhook`.
- Use the **live** secret key in `.env`. Webhook signatures are verified with that same key.
- Enable Transfers on your Paystack account (needed for curator payouts) and fund the transfer balance. Turn off OTP for transfers or use the API-friendly setting, otherwise payouts will wait for an OTP.
- Optional: set `PAYSTACK_IPS` to Paystack's published webhook IPs.
- Test with Paystack test keys first, then switch. Never set `PAYSTACK_MOCK` in production (it is ignored when `APP_ENV=production`).

## 8. Cron (cPanel → Cron Jobs), every 5 minutes
```
*/5 * * * * /usr/local/bin/php /home/USER/plugthelist/api/cron.php >/dev/null 2>&1
```
It expires unpaid orders, auto-refunds unaccepted or undelivered orders, auto-releases approved-by-silence deliveries and cleans old data. **If this does not run, escrow timers do not work.**

## 9. Email
Create `no-reply@plugthelist.com` in cPanel and put its SMTP details in `.env`. Add SPF, DKIM and DMARC records (cPanel → Email Deliverability) or verification emails will land in spam.

## 10. Go-live checklist
- [ ] `php bin/check.php` shows no FIX lines
- [ ] A Paystack **test** booking completes end to end, then switch to live keys and repeat with a real ₦10,000 listing of your own
- [ ] Webhook shows "200" in the Paystack dashboard
- [ ] Cron runs (Admin overview shows no stuck orders)
- [ ] Backups: daily database dump and a copy of `secrets/.env` and `storage/uploads`
- [ ] Legal pages reviewed by a Nigerian lawyer, then `LEGAL_DRAFT=0`
- [ ] Read `docs/LEGAL-CHECKLIST.md`

## Updating later
Upload new files over the old ones (never overwrite `secrets/` or `storage/`), then run `php bin/migrate.php`.
