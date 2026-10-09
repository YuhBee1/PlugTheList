<?php
declare(strict_types=1);
require __DIR__ . '/../shared/bootstrap.php';
require PTL_SHARED . '/views/listing_form.php';
listing_form_page((int)qs('id'));
