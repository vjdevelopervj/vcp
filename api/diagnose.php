<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../includes/oauth.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json; charset=utf-8');

$googleRedirect = oauth_redirect_uri('google');
$githubRedirect = oauth_redirect_uri('github');
$detectedBase = oauth_base_url();

// Test cURL ke API AI (timeout 6 detik)
$aiTest = [
    'configured' => (AI_API_KEY !== ''),
    'base_url' => AI_BASE_URL,
    'model' => AI_MODEL,
    'status' => 'not_tested',
    'latency_ms' => 0,
    'error' => null,
];

if (AI_API_KEY !== '') {
    if (!function_exists('curl_init')) {
        $aiTest['status'] = 'failed';
        $aiTest['error'] = 'Ekstensi PHP cURL belum aktif di server.';
    } else {
        $start = microtime(true);
        $ch = curl_init(rtrim(AI_BASE_URL, '/') . '/models');
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . AI_API_KEY,
                'Accept: application/json',
            ],
        ];
        if (defined('AI_SSL_VERIFY') && !AI_SSL_VERIFY) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        $aiTest['latency_ms'] = round((microtime(true) - $start) * 1000);
        if ($raw === false) {
            $aiTest['status'] = 'failed';
            $aiTest['error'] = 'cURL error ' . $errno . ': ' . $err;
        } else {
            $aiTest['status'] = ($httpCode >= 200 && $httpCode < 400) ? 'connected' : ('http_' . $httpCode);
            if ($httpCode >= 400) {
                $aiTest['error'] = 'API merespon HTTP ' . $httpCode;
            }
        }
    }
}

// Test Database
$dbTest = [
    'status' => 'connected',
    'error' => null,
];
try {
    db()->query('SELECT 1');
} catch (Throwable $e) {
    $dbTest['status'] = 'failed';
    $dbTest['error'] = $e->getMessage();
}

echo json_encode([
    'diagnostics' => 'vcp_system_check',
    'detected_scheme' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
    'detected_base_url' => $detectedBase,
    'oauth_urls_to_register' => [
        'google_authorized_redirect_uri' => $googleRedirect,
        'github_authorization_callback_url' => $githubRedirect,
    ],
    'ai_connection' => $aiTest,
    'database_connection' => $dbTest,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
