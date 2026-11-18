<?php

require_once __DIR__ . '/../config.php';

$forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isHttps = $forwardedProto === 'https'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps,
]);
session_start();

const DOC_TYPES = [
    'planning' => 'Planning',
    'design' => 'Design',
    'prd' => 'PRD',
];

function valid_type(mixed $type): ?string
{
    return is_string($type) && array_key_exists($type, DOC_TYPES) ? $type : null;
}

function db(bool $forceReconnect = false): PDO
{
    static $pdo = null;
    if ($pdo === null || $forceReconnect) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, avatar FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

// Only allow app.php (with a rebuilt query string) to prevent open redirects and header injection.
function safe_next(mixed $next): string
{
    if (!is_string($next) || !preg_match('#^app\.php(?:\?(.*))?$#s', $next, $m)) {
        return 'app.php';
    }
    parse_str($m[1] ?? '', $query);
    return $query ? 'app.php?' . http_build_query($query) : 'app.php';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        redirect('login.php?next=' . urlencode('app.php' . ($query !== '' ? '?' . $query : '')));
    }
    return $user;
}
