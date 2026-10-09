<h2>How escrow works</h2>
<p>When a creative pays, the money is recorded in escrow and tied to that booking. The curator cannot withdraw it until the booking is completed. Payments are processed by Paystack.</p>
<h2>Timeline</h2>
<ol>
<li><strong>Payment.</strong> Unpaid bookings expire after <?= e((string)\PTL\Settings::int('unpaid_expiry_hours')) ?> hours.</li>
<li><strong>Acceptance.</strong> The curator has <?= e($v['accept_h']) ?> hours to accept. If they decline or do not respond, the creative is refunded in full, including the service fee.</li>
<li><strong>Delivery.</strong> The curator delivers within the days stated on the listing. If nothing is delivered <?= e($v['grace_h']) ?> hours after the due date, the creative is refunded in full.</li>
<li><strong>Approval.</strong> After delivery the creative has <?= e($v['review_h']) ?> hours to approve or open a dispute. If they do neither, the money is released to the curator.</li>
</ol>
<h2>Disputes</h2>
<p>If a dispute is opened, the money stays in escrow. We read both sides and may award the curator all, part or none of the booking price. The service fee and commission on any amount awarded to the curator are kept by us; on any amount returned to the creative, they are returned too.</p>
<h2>What is not a reason for a refund</h2>
<p>A song not performing as hoped is not a failure to deliver. Curators promise to deliver a service (for example a review, a post or a feature for a set period), not a result. Streaming-service playlist bookings are a review and consideration only.</p>
<h2>Refund method</h2>
<p>Refunds go back to the original payment method through Paystack. Timing depends on the card issuer or bank, usually several working days.</p>
<h2>Regulatory note</h2>
<p>Escrow here is a contractual arrangement operated by us. Customer money is not a bank deposit and is not covered by NDIC deposit insurance.</p>
