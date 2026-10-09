# Legal and compliance checklist (not legal advice)

The software includes draft Terms, Privacy notice (NDPA 2023), Acceptable Use, Escrow and Refund policy and Cookie notice. They are drafts. Have a Nigerian lawyer review them, then set `LEGAL_DRAFT=0`.

Things to settle before real money flows:

1. **Escrow and regulation.** Holding customers' money on a platform can attract CBN and payment-services rules. The design keeps funds inside Paystack's settlement flow and a ledger, but get written advice on whether you need a licence or a partner (for example a licensed escrow or payment provider) at your volume.
2. **Company registration.** Trade as a registered business (Paramount Digital Services / a CAC-registered entity) and put the registered name and address in `.env`. Check the name "PlugTheList" against CAC and the trademark registry before spending on branding.
3. **NDPC.** Check whether you must register as a data controller or processor of major importance with the Nigeria Data Protection Commission, and appoint a data protection contact.
4. **Consumer protection.** FCCPC rules apply to online sellers. Keep prices, fees and refund rules visible (they are).
5. **Tax.** VAT on your fees and income tax on your earnings. Curators are responsible for their own tax; consider whether you must report or withhold.
6. **KYC and AML.** Payouts require an account in the user's own name (enforced). Decide on extra identity checks above a payout threshold.
7. **Platform rules.** Spotify and Apple Music forbid paid placement. The software restricts those two to "review and consideration" and bans guaranteed streams on every platform (Acceptable Use). Do not relax this.
8. **Copyright.** Artists upload links, not files. Keep the takedown contact (`SUPPORT_EMAIL`) monitored.
9. **Disputes.** The decision process is yours; document it and stay consistent.
10. **Minors.** Sign-up requires confirming age 18+.
