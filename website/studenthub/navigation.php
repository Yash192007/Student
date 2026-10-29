<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$user = current_user(false);
echo json_encode([
    'user' => $user ? [
        'name' => $user['name'],
        'role' => $user['role'],
        'dashboard' => dashboard_for_role($user['role']),
        'logout_url' => portal_url('logout.php'),
        'csrf_token' => csrf_token(),
    ] : null,
], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
