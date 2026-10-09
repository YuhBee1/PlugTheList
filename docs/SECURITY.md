# Security design

| Area | What is in place |
|---|---|
| Passwords | Argon2id (bcrypt fallback), 10+ chars, common-password and email-name checks, rehash on login |
| Sessions | 256-bit random token in an HttpOnly, Secure, SameSite=Lax, `__Secure-` cookie; only its SHA-256 is stored; idle (12h, admin 30 min) and absolute (7 days, admin 12h) expiry; revoke all on password change/reset; token and CSRF secret rotate after 2FA |
| 2FA | TOTP (RFC 6238) with replay protection, 8 hashed single-use backup codes, secrets encrypted at rest (libsodium); mandatory for admins |
| CSRF | Per-session token on every POST, double-submit HMAC for signed-out forms, plus Origin/Referer allow-list |
| Brute force | DB rate limits per IP and per account on login, 2FA, reset, resend, sign-up, bank, withdrawals, uploads, API; generic error messages; no account enumeration; constant-time dummy hash |
| Headers | Strict CSP (no inline script or style, `form-action` limited to our hosts and Paystack), HSTS, X-Frame-Options DENY, nosniff, COOP/CORP, Referrer-Policy, Permissions-Policy, no-store on private pages |
| Injection | PDO prepared statements everywhere, identifier whitelist, output escaped, URLs restricted to https |
| Open redirect | `next` accepted only for our own origins |
| Money | Integer kobo only; double-entry ledger that must sum to zero; row locks and state machine on every transition; idempotency keys on every posting; Paystack amount and currency verified; webhook HMAC-SHA512 + replay table; late or mismatched payments flagged |
| Payouts | Bank account must resolve to a name matching the account holder; password re-check to change; account numbers encrypted; admin approval by default; failures return funds; uncertain transfers are never auto-reversed |
| Uploads | Images only, size and dimension caps, re-encoded with GD (strips metadata and payloads), random names, stored outside web root, served only to authorised viewers |
| Privacy | NDPA export and erasure, audit log with retention, minimal data |
| Isolation | Secrets, code and storage live outside every web root; each subdomain is a separate document root |

## Known limits (be honest with yourself)
- No CAPTCHA. Rate limits slow bots; if sign-up spam appears, add Cloudflare Turnstile to the two sign-up forms.
- Playlist ownership is checked by a code in the playlist description plus admin review, not by OAuth with each platform.
- 2FA setup shows a typed key, not a QR code (no third-party code was bundled).
- Mail is sent synchronously after commit; at high volume add a queue.
- MySQL-specific behaviour (row locks) was written for InnoDB but only SQLite could be run in the build environment. Run the Paystack test-mode booking before real money.
