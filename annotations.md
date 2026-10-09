# Code annotation archive

This file preserves the source comments and docblocks removed from the codebase at the owner's request. They include developer explanations, contracts/type notes, security and operational cautions, test guidance, and interface/style notes. Original file paths are listed below. The application behavior was not intentionally changed by this transformation.

## `admin/account.php`

```text
// the admin must be able to reach this page to turn 2FA on
```

## `admin/settings.php`

```text
// key => [label, kind]  kind: bps | naira | int | bool | csv
```

## `admin/withdrawals.php`

```text
// verified in the Paystack dashboard
```

## `api/cron.php`

```text
/**
 * Run every 5 minutes.
 *   cPanel cron (best): every 5 minutes, command: /usr/local/bin/php /home/USER/plugthelist/api/cron.php
 *   or HTTP:             curl -s -H "X-Cron-Token: SECRET" https://api.plugthelist.com/cron
 */
```

```text
// Stuck unpaid payments older than 3 days are just noise.
```

## `api/paystack-webhook.php`

```text
// ignored events are still acknowledged
```

```text
// Paystack retries
```

## `app/callback.php`

```text
// Paystack sends the buyer back here. We never trust the query string: we ask Paystack, then settle (the webhook may have won already).
```

## `auth/resend.php`

```text
// same answer whether or not the account exists
```

## `auth/verify.php`

```text
// A GET that changes state is acceptable here only because the token is single-use, secret and unguessable.
```

## `bin/check.php`

```text
/** php bin/check.php — pre-flight check for a new server. */
```

## `bin/create_admin.php`

```text
/** php bin/create_admin.php you@example.com "Full Name"   — prints a one-time password. Turn on 2FA at first sign-in (required). */
```

## `bin/migrate.php`

```text
/** php bin/migrate.php — creates tables, adds new columns, seeds settings. Safe to run again after every update. */
```

## `curator/wallet.php`

```text
// Money-moving settings need a fresh password check.
```

## `database/schema.php`

```text
/**
 * Portable schema (MySQL/InnoDB in production, SQLite in tests).
 * {PK} = primary key auto-increment, {ENGINE} = table options. Statuses are VARCHAR (no ENUM) so new values never break inserts.
 * Add a column later by adding it here too: Migrator::ensureColumn() retrofits existing databases.
 * @return array{tables:array<string,string>,indexes:array<int,array{0:string,1:string,2:string,3:bool}>,columns:array<string,array<string,string>>}
 */
```

```text
// [name, table, columns, unique]
```

```text
// Columns added after first release go here so old databases get them.
```

## `shared/bootstrap.php`

```text
/**
 * PlugTheList root-level "server" layer. Every subdomain (www, auth, curator, app, admin, api)
 * starts with:   require __DIR__ . '/../shared/bootstrap.php';
 * This folder, /secrets and /storage live OUTSIDE every web root.
 *
 * Define PTL_NO_SESSION before requiring this file for webhooks/cron (no cookies, no session lookup).
 * Define PTL_API for JSON endpoints so errors come back as JSON.
 */
```

```text
// Maintenance switch (admin can still reach admin.*)
```

## `shared/lib/Audit.php`

```text
/** Append-only trail of security-relevant and money-relevant events. Never log passwords, tokens or full account numbers. */
```

```text
/** @param array<string, mixed> $meta */
```

## `shared/lib/Auth.php`

```text
/**
     * @param array<string,string> $in
     * @return array{0:?int,1:array<string,string>} [userId, errors]
     */
```

```text
// Duplicate email. Do not reveal it: act as if sign-up worked, and tell the owner by email.
```

```text
/** Returns user id for a valid unused token (does not consume). */
```

```text
/**
     * @return array{0:string,1:string} [state, message]; state = ok | mfa | error
     */
```

```text
// Always burn the same CPU whether or not the account exists (no timing oracle).
```

```text
/** @return array{0:string,1:array<int,string>} [secret, backupCodes] */
```

```text
/** @return array<int,string>|null backup codes shown once, or null on wrong code */
```

```text
/** @return array<int,string> problems */
```

```text
/** @return array<int,string> problems */
```

```text
/** Gate for a page. Redirects to sign in, or aborts when the role is wrong. */
```

## `shared/lib/Catalog.php`

```text
/** Fixed vocabularies: platforms, services, genres. Validate every submitted value against these. */
```

```text
/** slug => [label, kind, isDsp] ; isDsp = a streaming service whose rules forbid selling guaranteed placement */
```

```text
/** @return array<string, array{0:string,1:string,2:bool}> */
```

```text
/** @return array<string, string> */
```

```text
/**
     * Streaming-service playlists (Spotify, Apple Music) can only sell a review/consideration, never a guaranteed add.
     * Other platforms follow the admin setting `guaranteed_placement_platforms`.
     * @return array<int, string>
     */
```

## `shared/lib/Crypto.php`

```text
/** All keys derive from APP_KEY (base64, 32 random bytes) in secrets/.env. Generate with: php -r "echo base64_encode(random_bytes(32));" */
```

```text
/** Authenticated encryption (libsodium secretbox). Used for 2FA secrets. */
```

```text
/** Short random token for URLs/emails; only its sha256 is stored. */
```

## `shared/lib/Csrf.php`

```text
/**
 * Logged-in pages: token stored on the server-side session row.
 * Guest pages (login, sign-up): double-submit cookie, token = HMAC(APP_KEY, random cookie value).
 */
```

## `shared/lib/DB.php`

```text
/**
 * Thin PDO wrapper. Rules:
 *  - Every value goes through bound parameters. Table/column names only ever come from code and are validated.
 *  - Never use SQL NOW(); pass PHP now() so the whole app shares one clock (Africa/Lagos).
 *  - Do not swallow exceptions inside tx(): the outermost transaction owns commit/rollback.
 */
```

```text
/** @var array<int, callable> */
```

```text
/** Row lock for read-modify-write inside tx(). MySQL/InnoDB only (SQLite locks the whole file in tests). */
```

```text
/**
     * Insert that silently does nothing on a duplicate key. Returns true when a row was inserted.
     * @param array<string, mixed> $d
     */
```

```text
/** Test helper. */
```

```text
/** @param array<int|string, mixed> $p */
```

```text
/** @param array<int|string, mixed> $p */
```

```text
/** @param array<int|string, mixed> $p */
```

```text
/** @param array<int|string, mixed> $p */
```

```text
/** @param array<int|string, mixed> $p */
```

```text
/** @param array<string, mixed> $d */
```

```text
/**
     * @param array<string, mixed> $d
     * @param array<int|string, mixed> $params
     */
```

```text
/** Run once the outermost transaction commits (use for HTTP calls and emails). Runs now if no transaction. */
```

## `shared/lib/Env.php`

```text
/** Reads secrets/.env (kept OUTSIDE every web root). Real environment variables win as a fallback. */
```

```text
/** Test helper only. */
```

## `shared/lib/Escrow.php`

```text
/**
 * The escrow engine. Each transition runs in one DB transaction with the order row locked,
 * checks OrderState, posts balanced ledger entries, and logs an event. Emails/HTTP happen after commit.
 */
```

```text
/** @return int new order id */
```

```text
/** Starts (or restarts) a Paystack payment. @return string checkout URL */
```

```text
/**
     * Called from the webhook AND the callback page (whichever arrives first wins; the other is a no-op).
     * @return string result: paid | already | mismatch | unknown | failed
     */
```

```text
// Paid after the order was cancelled/expired: put the money straight back.
```

```text
/** Ledger + status for a refund. Call inside a transaction with the order locked. */
```

```text
/** Admin decision. $curatorShare = kobo of the booking price awarded to the curator (0..price). */
```

```text
/** Cron (every 5-10 minutes). @return array<string,int> counts */
```

## `shared/lib/Flash.php`

```text
/** One-shot messages carried across a redirect (and across subdomains) in a signed, short-lived cookie. */
```

```text
/** @var array<int, array{0:string,1:string}>|null */
```

```text
/** @return array<int, array{0:string,1:string}> */
```

```text
/** Returns and clears the messages. Call once while rendering. */
```

## `shared/lib/Ledger.php`

```text
/**
 * Double-entry ledger. Every transaction's lines sum to zero; idempotency key prevents double posting.
 * Accounts: ext:paystack_in, ext:paystack_refund, ext:bank_out, escrow, platform, user:<id>, hold:withdrawal
 * Positive = money held by that account. Money entering escrow: ext:paystack_in -total, escrow +total.
 */
```

```text
/**
     * @param array<string,int> $lines account => signed kobo (must sum to 0)
     * @return bool false if this idempotency key was already posted
     */
```

```text
/** Sum of every line must be zero. Returns the drift (0 = healthy). */
```

```text
/** Escrow account must equal the sum of live order totals. Returns [ledger, expected]. */
```

## `shared/lib/Listings.php`

```text
/**
     * Reads offer fields from a form: offer_<service>_on, offer_<service>_price (naira), offer_<service>_days.
     * @param array<string,mixed> $in
     * @return array{0:array<int,array{service:string,price_kobo:int,turnaround_days:int,details:?string}>,1:array<int,string>}
     */
```

```text
/** @param array<string,mixed> $in @return array{0:?int,1:array<int,string>} */
```

```text
/** @param array<string,mixed> $in @return array<int,string> errors */
```

```text
/** @param array<int,array{service:string,price_kobo:int,turnaround_days:int,details:?string}> $offers */
```

```text
// Offers referenced by orders are never deleted; they are switched off instead (orders keep their own price snapshot).
```

```text
/** @param array<string,mixed> $in @return array{0:?array<string,mixed>,1:array<int,string>} */
```

```text
/**
     * Public browse. @param array<string,string> $f platform, genre, q, max_budget (naira), min_followers, sort
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
```

```text
// MySQL has no || concat by default; use CONCAT there.
```

## `shared/lib/Mailer.php`

```text
/**
 * MAIL_DRIVER = log (default; writes storage/logs/mail.log, for local/test) | smtp | mail
 * SMTP: MAIL_HOST, MAIL_PORT (465 = SSL, 587 = STARTTLS), MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME.
 */
```

## `shared/lib/Migrator.php`

```text
/** @return array<int, string> log lines */
```

```text
// already exists
```

## `shared/lib/Money.php`

```text
/** Every amount in the system is an integer number of kobo (1 naira = 100 kobo). No floats touch money. */
```

```text
/** Round-half-up percentage in basis points (1000 = 10%). */
```

```text
/**
     * @return array{price:int,buyer_fee:int,curator_fee:int,total:int,curator_net:int,platform:int}
     */
```

```text
// what the creative pays into escrow
```

```text
// what the curator receives
```

```text
// what PlugTheList keeps
```

```text
/**
     * Dispute split: the curator is awarded $share (<= price) of the booking price.
     * Platform fees are charged pro rata on the awarded share; the rest goes back to the creative.
     * Invariant: curator + platform + refund == total.
     * @param array{price:int,buyer_fee:int,curator_fee:int,total:int} $o
     * @return array{curator:int,platform:int,refund:int}
     */
```

```text
/** "10,000" / "10000.50" / "₦10 000" -> kobo, or null if not a clean amount. */
```

## `shared/lib/Notifier.php`

```text
/** In-app notification plus (after the DB transaction commits) an email. */
```

## `shared/lib/OrderState.php`

```text
/** The only legal order status moves. Escrow checks this before every transition. */
```

```text
// money is in escrow, curator has not accepted yet
```

```text
// curator accepted
```

```text
// curator uploaded proof, review window running
```

```text
// escrow released to curator
```

```text
// never paid
```

```text
/** Statuses that hold money in escrow. */
```

## `shared/lib/Paystack.php`

```text
/**
 * Paystack client. PAYSTACK_SECRET in secrets/.env. PAYSTACK_MOCK=1 simulates Paystack but ONLY when APP_ENV is not "production".
 */
```

```text
/** @param array<string,mixed>|null $body @return array<string,mixed> */
```

```text
/** @return array{url:string,reference:string} */
```

```text
/** @return array{status:string,amount:int,currency:string,channel:string}|null */
```

```text
/** @return array<int, array{name:string,code:string}> */
```

```text
/** @return array{ok:bool,code:string,status:string} */
```

## `shared/lib/Privacy.php`

```text
/** NDPA 2023 data-subject tools: access/portability (export) and erasure (anonymise, keeping legally required financial records). */
```

```text
/** @return array<string,mixed> */
```

```text
/** @return string|null reason it cannot be done yet */
```

## `shared/lib/RateLimiter.php`

```text
/**
 * Fixed-window counters in the database (works on shared hosting, no Redis needed).
 * Keys are hashed, so no raw emails/IPs are stored in the table.
 */
```

```text
/** Count one hit. Returns [hitsInWindow, secondsUntilReset]. */
```

```text
// 1) still inside the current window
```

```text
// 2) an expired window exists: start a new one
```

```text
// 3) first ever hit (a concurrent request may win the insert; then loop and count normally)
```

```text
/** Non-counting check: true when the bucket is already over its limit. Pair with hit() on the failure path. */
```

```text
/** Soft check for forms: true = allowed, false = over the limit. */
```

```text
/** Hard check for endpoints: sends 429 + Retry-After and stops. */
```

## `shared/lib/Security.php`

```text
/** Called by bootstrap on every web request (not for webhooks/cron). */
```

```text
// guarantees the guest CSRF cookie exists before any output
```

```text
// Only trust the Cloudflare header when you really are behind Cloudflare AND the origin only accepts its traffic.
```

```text
/** Headers for JSON/webhook endpoints. */
```

```text
/** Only allow post-login redirects back to our own subdomains. */
```

```text
/** Guard for every state-changing request. */
```

## `shared/lib/Session.php`

```text
/**
 * Server-side sessions. Cookie holds a random 256-bit token; the DB stores only its SHA-256.
 * The cookie is scoped to the parent domain (COOKIE_DOMAIN=.plugthelist.com) so one login on
 * auth.plugthelist.com works on curator., app. and admin. - each of them re-validates against the DB.
 */
```

```text
/** The logged-in user, only once any required 2FA step is complete. */
```

```text
// New token + new CSRF secret once the second factor is proven (prevents fixation).
```

## `shared/lib/Settings.php`

```text
/** Admin-editable business rules. Stored in the `settings` table; defaults below are seeded by the migration. */
```

```text
// 10% taken from the curator's price
```

```text
// 5% service fee added on top for the creative
```

```text
// ₦10,000
```

```text
// ₦5,000,000
```

```text
// ₦10,000
```

```text
// curator must accept after payment, else auto-refund
```

```text
// creative has this long to approve/dispute after delivery
```

```text
// after due date, auto-refund if nothing delivered
```

## `shared/lib/Totp.php`

```text
/** RFC 6238 TOTP (SHA-1, 6 digits, 30 s) - compatible with Google Authenticator, Authy, 1Password, etc. */
```

```text
/**
     * Verify with +/-1 step tolerance. $lastStep blocks replay of an already-used code.
     * On success returns the time-step that matched, otherwise null.
     */
```

## `shared/lib/Upload.php`

```text
/**
 * Image uploads (cover art, proof of past work, proof of delivery). Files are re-encoded with GD
 * (kills embedded payloads, strips EXIF/GPS), stored OUTSIDE the web root and served only through an authorised script.
 */
```

```text
/** @return array{0:?int,1:?string} [fileId, error] */
```

```text
/** May this viewer see this file? Covers: public listing art, own files, order participants, admins. */
```

## `shared/lib/Validator.php`

```text
/** Input checks. Each returns the cleaned value or null; callers add the user-facing message. */
```

```text
/** Nigerian mobile numbers, stored as +234XXXXXXXXXX. Accepts 080…, 234…, +234…. */
```

```text
/** @return array<int, string> problems (empty = fine) */
```

```text
/** https URLs only, public host, no credentials. */
```

```text
/** @param array<int|string, mixed> $options */
```

## `shared/lib/Wallet.php`

```text
/** Curator bank account and withdrawals. Money flow: user:ID -> hold:withdrawal -> ext:bank_out (+platform fee). */
```

```text
/** @return string|null error */
```

```text
// Account holder must match the registered name loosely (anti-fraud): at least one name part in common.
```

```text
/** @return string|null error */
```

```text
// serialise per user
```

```text
/** Admin approves: sends the transfer. */
```

```text
/** Starts the Paystack transfer. @return string|null error */
```

```text
// Outcome unknown: leave as processing so an admin checks Paystack. Never auto-return funds on uncertainty.
```

## `shared/lib/helpers.php`

```text
/* Global helper functions. Loaded by shared/bootstrap.php before anything else. */
```

```text
/** URL on one of our subdomains: www | auth | curator | app | admin | api. */
```

```text
// local testing: URL_AUTH=http://127.0.0.1:8081
```

```text
/** @return array<int, string> */
```

```text
/** Trimmed string from $_POST (arrays are ignored). */
```

```text
/** External link that is safe to render. */
```

## `shared/views/layout.php`

```text
/* Shared page chrome. Every page: page_header([...]); ...content...; page_footer(); */
```

```text
/** @return array<int, array{0:string,1:string}> */
```

```text
/** @param array{title?:string,area?:string,description?:string,noindex?:bool,canonical?:string,body?:string} $o */
```

```text
/** One labelled form control with its error and hint. @param array<string,string|int|bool> $attr */
```

```text
/** @param array<int,string> $errs */
```

```text
/** One dotted-leader "price board" row for a listing. @param array<string,mixed> $l */
```

## `shared/views/listing_form.php`

```text
/** Create (id = 0) or edit a listing. */
```

## `shared/views/lists.php`

```text
/** @param array<int,string> $statuses */
```

## `shared/views/order.php`

```text
/** Order detail for both sides. $role = creative | curator. */
```

```text
// Actions
```

```text
// Conversation
```

## `shared/views/register.php`

```text
/** Shared sign-up page for both account types. */
```

## `tests/e2e.php`

```text
// Drives the real pages over HTTP. Run via tests/e2e.sh
```

```text
// the page cannot show the secret without POST result; run begin again and parse the result of that POST
```

```text
// new login must now ask for a code
```

```text
// the test process uses the same sqlite db
```

## `tests/e2e.sh`

```text
# Boots all six subdomains on localhost ports with a throwaway SQLite DB and runs tests/e2e.php against them.
```

## `tests/router.php`

```text
// Router for `php -S` during local testing only: mimics the Apache rewrites.
```

## `tests/run.php`

```text
/** php tests/run.php  — self-contained checks on a throwaway SQLite database + mock Paystack. */
```

## `www/assets/ptl.css`

```text
/* PlugTheList. Lagos price-board: concrete, danfo yellow, ink. No external fonts or scripts. */
```

```text
/* The danfo stripe: one bold device, used once per page at the very top */
```

```text
/* Hero + price board */
```

```text
/* Forms */
```

```text
/* Dashboards, tables, detail */
```

## `www/assets/ptl.js`

```text
// Mobile menu
```

```text
// Theme toggle (light / dark), remembered in this browser only
```

```text
// Stop double submits (double payments, double bookings)
```

```text
// Listing form: Spotify and Apple Music playlists can only sell a review
```

```text
// Limit genre picks to 4
```

