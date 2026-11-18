<?php

/**
 * Membaca file .env jika ada dan memasukkan nilainya ke getenv(), $_ENV, dan $_SERVER.
 */
function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        // Hapus tanda kutip ganda ("...") atau tunggal ('...') di awal dan akhir
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

/**
 * Mengambil nilai konfigurasi dari environment variables dengan fallback default.
 */
function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        return $default;
    }

    if (is_string($val)) {
        $lower = strtolower($val);
        if ($lower === 'true' || $lower === '(true)') {
            return true;
        }
        if ($lower === 'false' || $lower === '(false)') {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }
        if ($lower === 'empty' || $lower === '(empty)') {
            return '';
        }
        if (is_numeric($val) && is_int($default)) {
            return (int) $val;
        }
    }

    return $val;
}

// Muat variabel dari .env
load_env(__DIR__ . '/.env');

// --- Konfigurasi Aplikasi ----------------------------------------------------
define('APP_NAME', (string) env('APP_NAME', 'vcp'));
define('APP_URL', (string) env('APP_URL', 'http://localhost/vcp'));
define('APP_ENV', (string) env('APP_ENV', 'local'));

// --- Database MySQL ----------------------------------------------------------
define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', (string) env('DB_NAME', 'vcp'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));

// --- AI Configuration (OpenAI-compatible) -------------------------------------
// Kosongkan AI_API_KEY di .env untuk menjalankan dalam mode demo dengan template bawaan.
define('AI_API_KEY', (string) env('AI_API_KEY', ''));
define('AI_BASE_URL', (string) env('AI_BASE_URL', 'https://integrate.api.nvidia.com/v1'));
define('AI_MODEL', (string) env('AI_MODEL', 'nvidia/nemotron-3.5-lightning-30b-a3b'));
define('AI_MAX_TOKENS', (int) env('AI_MAX_TOKENS', 8192));
define('AI_THINKING', (bool) env('AI_THINKING', false));
define('AI_TIMEOUT', (int) env('AI_TIMEOUT', 120));

// --- Login dengan Google / GitHub OAuth ---------------------------------------
// Kosongkan = tombol provider tidak muncul. Diisi di file .env.
define('GOOGLE_CLIENT_ID', (string) env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', (string) env('GOOGLE_CLIENT_SECRET', ''));
define('GITHUB_CLIENT_ID', (string) env('GITHUB_CLIENT_ID', ''));
define('GITHUB_CLIENT_SECRET', (string) env('GITHUB_CLIENT_SECRET', ''));
