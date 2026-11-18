<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/oauth.php';

function oauth_fail(string $message): never
{
    redirect(oauth_url('login.php?oauth_error=' . urlencode($message)));
}

$pending = $_SESSION['oauth'] ?? null;
unset($_SESSION['oauth']);

if (!is_array($pending) || ($pending['provider'] ?? '') !== ($_GET['provider'] ?? '')) {
    oauth_fail('Permintaan login sudah tidak berlaku, silakan mulai lagi dari halaman sign in.');
}
if (!hash_equals((string) $pending['state'], (string) ($_GET['state'] ?? ''))) {
    oauth_fail('Verifikasi state gagal (kemungkinan permintaan tidak berasal dari browser ini).');
}
if (isset($_GET['error'])) {
    oauth_fail('Kamu menolak izin login di provider, atau provider mengirim error: ' . (string) $_GET['error']);
}
if (!is_string($_GET['code'] ?? null) || $_GET['code'] === '') {
    oauth_fail('Provider tidak mengirim kode otorisasi.');
}

try {
    $identity = oauth_fetch_identity((string) $pending['provider'], (string) $_GET['code'], (string) $pending['verifier']);
    $userId = oauth_resolve_user((string) $pending['provider'], $identity);
} catch (Throwable $e) {
    oauth_fail('Login provider gagal: ' . mb_substr($e->getMessage(), 0, 300));
}

login_user($userId);
redirect(oauth_url($pending['next'] ?? 'app.php'));
