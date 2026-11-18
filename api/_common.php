<?php

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    // JSON_HEX_TAG/AMP: konten user (judul, isi dokumen, nama lampiran) dikirim keluar
    // apa adanya, jadi "<" ditulis \u003C supaya tidak pernah bisa dibaca sebagai tag
    // oleh klien yang menempelkan respons JSON ke HTML.
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

function api_user(): array
{
    $user = current_user();
    if (!$user) {
        json_out(['error' => 'Sesi berakhir, silakan login kembali.'], 401);
    }
    return $user;
}

function api_input(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['error' => 'Method not allowed'], 405);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_out(['error' => 'Invalid JSON body'], 400);
    }
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        // 403, bukan 419: kode non-standar diterjemahkan Apache jadi 500 sehingga
        // sesi kedaluwarsa terlihat di log/monitoring seperti server crash.
        json_out(['error' => 'Token CSRF tidak valid, muat ulang halaman.'], 403);
    }
    return $input;
}

function find_document(int $userId, int $docId): array
{
    $stmt = db()->prepare('SELECT id, title, type, content, pinned, created_at, updated_at FROM documents WHERE id = ? AND user_id = ?');
    $stmt->execute([$docId, $userId]);
    $doc = $stmt->fetch();
    if (!$doc) {
        json_out(['error' => 'Dokumen tidak ditemukan.'], 404);
    }
    return $doc;
}

// Prompt gaul biasanya diawali kata pengisi. Judul chat diambil dari intinya saja, jadi
// "gua pengen bikin aplikasi kasir buat warung" cukup jadi "Aplikasi kasir buat warung".
const TITLE_FILLERS = '^(ya|oh|oi|ok|oke|hai|halo|hi|hello|assalamu.?alaikum|tolong|bantu|bantuan|coba|langsung|mau|pengen|pingin|ingin|bikinin|bikin|buatkan|bikinkan|buat|gw|guwe|gua|gue|gueh|saya|aku|sy|aq|kami|dong|deh|sih|nih|tu)\s+';

function title_from_message(string $message): string
{
    // Karakter kontrol (tempel dari PDF/Excel, atau payload iseng) bukan whitespace bagi \s,
    // jadi dihapus duluan: judul masuk ke HTML dan JSON, tak boleh menyimpannya.
    $message = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
    $text = preg_replace('/^\/\s*/u', '', trim(preg_replace('/\s+/u', ' ', $message)));
    if ($text === '') {
        return 'Chat baru';
    }
    for ($i = 0; $i < 4; $i++) {
        $next = trim((string) preg_replace('/' . TITLE_FILLERS . '/iu', '', $text, 1));
        if ($next === '' || mb_strlen($next) < 3 || $next === $text) {
            break;
        }
        $text = $next;
    }
    // Partikel yang nyangkut di tengah kalimat juga tidak berguna di judul.
    $clean = trim((string) preg_replace(['/^[\s,.;:!-]+/u', '/\s+/u'], [' ', ' '], preg_replace('/\b(dong|deh|doang|sih|nih|gan|bro|brot|sis|coh)\b/iu', ' ', $text)));
    $clean = ltrim($clean);
    if (mb_strlen($clean) >= 3) {
        $text = $clean;
    }
    if (mb_strlen($text) > 60) {
        $text = rtrim(mb_substr($text, 0, 60), " \t,.;:!?") . '…';
    }
    return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}
