<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * @return array<string, array<string, string>>
 */
function oauth_providers(): array
{
    return [
        'google' => [
            'label' => 'Google',
            'client_id' => (string) (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''),
            'client_secret' => (string) (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : ''),
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scopes' => 'openid email profile',
            'pkce' => 'S256',
        ],
        'github' => [
            'label' => 'GitHub',
            'client_id' => (string) (defined('GITHUB_CLIENT_ID') ? GITHUB_CLIENT_ID : ''),
            'client_secret' => (string) (defined('GITHUB_CLIENT_SECRET') ? GITHUB_CLIENT_SECRET : ''),
            'authorize_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'scopes' => 'read:user user:email',
            'pkce' => '',
        ],
    ];
}

function oauth_configured(string $provider): bool
{
    $p = oauth_providers()[$provider] ?? null;
    return $p !== null && $p['client_id'] !== '' && $p['client_secret'] !== '';
}

/** @return string[] provider yang credensialnya sudah diisi */
function oauth_available(): array
{
    return array_values(array_filter(array_keys(oauth_providers()), 'oauth_configured'));
}

/**
 * Base URL aplikasi, mengikuti host yang dipakai user (Laragon vhost, localhost,
 * atau reverse proxy yang mengirim X-Forwarded-*). Fallback ke vhost dev kalau
 * header Host aneh/di-spoof — jangan sampai jadi open redirect.
 */
function oauth_base_url(): string
{
    if (defined('APP_URL') && trim((string) APP_URL) !== '') {
        return rtrim(trim((string) APP_URL), '/');
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $https = $forwardedProto === 'https' || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off');

    $host = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''))[0]);
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{2,5})?$/', $host)) {
        $host = 'vcp.test';
    }

    return ($https ? 'https' : 'http') . '://' . $host;
}

function oauth_url(string $path): string
{
    return oauth_base_url() . '/' . ltrim($path, '/');
}

function oauth_redirect_uri(string $provider): string
{
    return oauth_url('auth/callback.php') . '?provider=' . $provider;
}

function oauth_pkce_challenge(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

function oauth_authorize_url(string $provider, string $state, string $verifier = ''): string
{
    $p = oauth_providers()[$provider];
    $params = [
        'client_id' => $p['client_id'],
        'redirect_uri' => oauth_redirect_uri($provider),
        'response_type' => 'code',
        'scope' => $p['scopes'],
        'state' => $state,
    ];
    if ($p['pkce'] !== '') {
        $params['code_challenge'] = oauth_pkce_challenge($verifier);
        $params['code_challenge_method'] = $p['pkce'];
    }
    if ($provider === 'google') {
        $params['prompt'] = 'select_account';
    }

    return $p['authorize_url'] . '?' . http_build_query($params);
}

/**
 * @param array<string, string> $fields
 * @return array<string, mixed>
 */
function oauth_post_form(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: vcp'],
        CURLOPT_POSTFIELDS => http_build_query($fields),
    ]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === '') {
        throw new RuntimeException('Gagal menghubungi ' . parse_url($url, PHP_URL_HOST) . ': ' . $error);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        parse_str($raw, $data); // sebagian provider membalas form-urlencoded
    }
    if ($status >= 400) {
        $detail = (string) ($data['error_description'] ?? $data['error'] ?? 'tanpa keterangan');
        throw new RuntimeException('Provider menolak pertukaran token (HTTP ' . $status . '): ' . $detail);
    }

    return $data;
}

/** @return array<string, mixed>|list<array<string, mixed>> */
function oauth_get_json(string $url, string $accessToken): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Accept: application/json', 'User-Agent: vcp'],
    ]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Gagal membaca profil dari provider (HTTP ' . $status . ').');
    }

    return $data;
}

/**
 * @return array{provider_id: string, email: ?string, name: string}
 */
function oauth_fetch_identity(string $provider, string $code, string $verifier = ''): array
{
    return $provider === 'google' ? oauth_google_identity($code, $verifier) : oauth_github_identity($code);
}

/** @return array{provider_id: string, email: ?string, name: string} */
function oauth_google_identity(string $code, string $verifier): array
{
    $p = oauth_providers()['google'];
    $token = oauth_post_form($p['token_url'], [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $p['client_id'],
        'client_secret' => $p['client_secret'],
        'redirect_uri' => oauth_redirect_uri('google'),
        'code_verifier' => $verifier,
    ]);
    if (empty($token['access_token'])) {
        throw new RuntimeException('Google tidak mengirim access token.');
    }

    $me = oauth_get_json('https://openidconnect.googleapis.com/v1/userinfo', (string) $token['access_token']);
    if (empty($me['sub'])) {
        throw new RuntimeException('Profil Google tidak punya identifier (sub).');
    }
    $verified = !isset($me['email_verified']) || $me['email_verified'] === true || $me['email_verified'] === 'true';

    return [
        'provider_id' => (string) $me['sub'],
        'email' => ($verified && !empty($me['email'])) ? (string) $me['email'] : null,
        'name' => (string) ($me['name'] ?? $me['given_name'] ?? 'Pengguna Google'),
    ];
}

/** @return array{provider_id: string, email: ?string, name: string} */
function oauth_github_identity(string $code): array
{
    $p = oauth_providers()['github'];
    $token = oauth_post_form($p['token_url'], [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $p['client_id'],
        'client_secret' => $p['client_secret'],
        'redirect_uri' => oauth_redirect_uri('github'),
    ]);
    if (empty($token['access_token'])) {
        throw new RuntimeException('GitHub tidak mengirim access token (' . (string) ($token['error'] ?? 'errorunknown') . ').');
    }
    $accessToken = (string) $token['access_token'];

    $me = oauth_get_json('https://api.github.com/user', $accessToken);
    if (empty($me['id'])) {
        throw new RuntimeException('Profil GitHub tidak punya identifier (id).');
    }
    $id = (string) $me['id'];
    $login = (string) ($me['login'] ?? 'pengguna-github');
    $email = !empty($me['email']) ? (string) $me['email'] : null;

    if ($email === null) {
        // Email profil bisa disembunyikan; minta daftar email terverifikasi.
        foreach (oauth_get_json('https://api.github.com/user/emails', $accessToken) as $row) {
            if (is_array($row) && !empty($row['verified']) && !empty($row['email'])) {
                $email = (string) $row['email'];
                break;
            }
        }
    }

    return [
        'provider_id' => $id,
        // Kalau semua email privat, pakai alamat noreply resmi GitHub agar tetap unik.
        'email' => $email ?? ($id . '+' . $login . '@users.noreply.github.com'),
        'name' => (string) ($me['name'] ?? $login),
    ];
}

/**
 * Identity -> user_id: pakai akun yang sudah tertaut, lalu coba menautkan lewat
 * email, kalau belum ada buat akun baru (password acak, login hanya via provider).
 *
 * @param array{provider_id: string, email: ?string, name: string} $identity
 */
function oauth_resolve_user(string $provider, array $identity): int
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT user_id FROM oauth_identities WHERE provider = ? AND provider_id = ?');
    $stmt->execute([$provider, $identity['provider_id']]);
    $userId = (int) $stmt->fetchColumn();
    if ($userId > 0) {
        return $userId;
    }

    $email = $identity['email'] ?? ($provider . '.' . $identity['provider_id'] . '@' . $provider . '.local');
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $userId = (int) $stmt->fetchColumn();

    if ($userId === 0) {
        try {
            $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$identity['name'], $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            // Kompetitor request: email baru saja dibuat, pakai akun itu.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $stmt->execute([$email]);
            $userId = (int) $stmt->fetchColumn();
            if ($userId === 0) {
                throw $e;
            }
        }
    }

    try {
        $pdo->prepare('INSERT INTO oauth_identities (user_id, provider, provider_id, email) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $provider, $identity['provider_id'], $email]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
    }

    return $userId;
}

function oauth_icon(string $provider): string
{
    if ($provider === 'google') {
        return '<svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47a5.54 5.54 0 0 1-2.4 3.58v3h3.86c2.26-2.08 3.56-5.16 3.56-8.82z"/>'
            . '<path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.93-2.91l-3.86-3c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A11.99 11.99 0 0 0 12 24z"/>'
            . '<path fill="#FBBC05" d="M5.27 14.28a7.2 7.2 0 0 1 0-4.56V6.63H1.29a11.98 11.98 0 0 0 0 10.74l3.98-3.09z"/>'
            . '<path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.29 6.63l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/>'
            . '</svg>';
    }

    return '<svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.3-3.4-1.3-.4-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .9.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.7 1a9.4 9.4 0 0 1 5 0c1.9-1.3 2.7-1 2.7-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.4 4.7-4.6 5 .4.3.7.9.7 1.9V21c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/>'
        . '</svg>';
}
