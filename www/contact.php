<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
use PTL\Settings;
page_header(['title' => 'Contact', 'area' => 'public']);
$m = (string)env('SUPPORT_EMAIL', 'support@' . base_domain());
echo '<main class="wrap narrow"><h1>Contact us</h1><p class="lede">For booking problems, include the order reference (it starts with PTL-). Email <a href="mailto:' . e($m) . '">' . e($m) . '</a>.</p><p class="muted">Paramount Digital Services, Uyo, Akwa Ibom State, Nigeria.</p></main>';
page_footer();
