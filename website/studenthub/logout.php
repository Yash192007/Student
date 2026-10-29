<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the Log out button to end your session.');
}

verify_csrf();
logout_user();
redirect(portal_url('login.php?logged_out=1'));
