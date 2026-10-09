<p>This notice explains how <?= e($v['company']) ?> ("we") handles personal data of people who use PlugTheList, in line with the Nigeria Data Protection Act 2023 (NDPA).</p>
<h2>What we collect</h2>
<ul>
<li>Account data: name, display name, email, phone number, account type, password (stored only as a salted hash).</li>
<li>Booking data: tracks and links you submit, messages, delivery proof, ratings.</li>
<li>Payment data: Paystack payment references and amounts. We never see or store your full card number. For payouts we store your bank name, account name, a protected copy of your account number and its last four digits.</li>
<li>Security data: IP address, browser details, sign-in times and an audit trail of important actions.</li>
<li>Images you upload. We re-encode them and remove hidden metadata such as location.</li>
</ul>
<h2>Why, and on what basis</h2>
<p>To create your account and run bookings, escrow and payouts (contract). To prevent fraud, keep the Platform secure and keep financial records (legitimate interest and legal obligation). To email you about orders and security (contract). To send product news only if you ticked the box (consent, which you can withdraw).</p>
<h2>Who receives it</h2>
<p>Paystack (payments and transfers), our hosting and email providers, and the other party to a booking (they see your display name, your track and your messages). We do not sell personal data. We may disclose data where the law or a court requires it.</p>
<h2>Transfers abroad</h2>
<p>Some providers may process data outside Nigeria. Where that happens we rely on appropriate safeguards required by the NDPA.</p>
<h2>How long we keep it</h2>
<p>Account data while your account is open. Financial records (orders, payments, ledger) for at least six years because the law expects it. When you delete your account we remove or anonymise personal details but keep those records without your name attached where possible. Sign-in and audit logs are kept for up to 24 months.</p>
<h2>Your rights</h2>
<p>You can ask to access, correct, port or erase your data, object to processing, or withdraw consent. Signed-in users can download their data and delete their account from the Account page. You may also complain to the Nigeria Data Protection Commission.</p>
<h2>Security</h2>
<p>Passwords are hashed with Argon2id, sessions are server-side, two-step verification is available, sensitive values are encrypted and access is logged. No system is perfectly secure; tell us at once if you suspect a problem.</p>
<h2>Cookies and contact</h2>
<p>See the Cookie notice. Contact our data protection contact at <a href="mailto:<?= e(env('PRIVACY_EMAIL', $v['support'])) ?>"><?= e(env('PRIVACY_EMAIL', $v['support'])) ?></a>.</p>
