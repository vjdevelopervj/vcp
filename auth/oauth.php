<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/oauth.php';

$provider = (string) ($_GET['provider'] ?? '');

if (!oauth_configured($provider)) {
    redirect(oauth_url('login.php?oauth_error=' . urlencode('Login dengan provider itu belum diaktifkan.')));
}

if (current_user()) {
    redirect(oauth_url(safe_next($_GET['next'] ?? null)));
}

$state = bin2hex(random_bytes(16));
$verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

$_SESSION['oauth'] = [
    'provider' => $provider,
    'state' => $state,
    'verifier' => $verifier,
    'next' => safe_next($_GET['next'] ?? null),
];

redirect(oauth_authorize_url($provider, $state, $verifier));
