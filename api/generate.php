<?php

// Pastikan waktu eksekusi cukup untuk request AI tanpa timeout di server hosting
@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '256M');
ignore_user_abort(true);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../includes/ai.php';

const MAX_REFERENCE_IMAGES = 3;
const MAX_ATTACHMENTS = 5;

$user = api_user();
$uid = (int) $user['id'];
$input = api_input();

$message = trim((string) ($input['message'] ?? ''));
if (mb_strlen($message) > 8000) {
    json_out(['error' => 'Pesan terlalu panjang (maks 8000 karakter).'], 422);
}

// Lampiran harus berupa URL hasil api/upload.php milik user ini sendiri, supaya tidak ada
// path traversal atau URL pihak ketiga yang ikut dikirim ke AI. Ekstensinya dibatasi ke
// whitelist milik upload.php: .webp (gambar), .txt (teks), .pdf/.docx (dokumen simpan-saja).
$rawFiles = $input['attachments'] ?? [];
if (!is_array($rawFiles) || count($rawFiles) > MAX_ATTACHMENTS) {
    json_out(['error' => 'Maksimal ' . MAX_ATTACHMENTS . ' lampiran per pesan.'], 422);
}
$attachments = [];
foreach ($rawFiles as $att) {
    $url = is_array($att) ? trim((string) ($att['url'] ?? '')) : '';
    if ($url === '') {
        continue;
    }
    if (!preg_match('#^uploads/(\d+)/([a-f0-9]{24})\.(webp|txt|pdf|docx)$#', $url, $m) || (int) $m[1] !== $uid) {
        json_out(['error' => 'Lampiran tidak valid.'], 422);
    }
    if (!is_file(__DIR__ . '/../' . $url)) {
        json_out(['error' => 'Lampiran sudah tidak ada di server, unggah ulang ya.'], 422);
    }
    $kind = $m[3] === 'webp' ? 'image' : ($m[3] === 'txt' ? 'text' : 'doc');
    $name = is_string($att['name'] ?? null) ? trim((string) $att['name']) : '';
    $attachments[] = [
        'url' => $url,
        'kind' => $kind,
        'name' => $name === '' ? basename($url) : mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name), 0, 80),
    ];
}
// Prompt boleh kosong selama ada lampiran: tinggal seret file, lalu kirim.
if ($message === '' && !$attachments) {
    json_out(['error' => 'Tulis pesan atau lampirkan file dulu, ya.'], 422);
}
$images = array_values(array_map(
    static fn (array $a): string => $a['url'],
    array_filter($attachments, static fn (array $a): bool => $a['kind'] === 'image')
));
if (count($images) > MAX_REFERENCE_IMAGES) {
    json_out(['error' => 'Maksimal ' . MAX_REFERENCE_IMAGES . ' gambar referensi per pesan.'], 422);
}

// Chat baru sengaja belum disimpan di database: barisnya baru dibuat di sini,
// dan hanya kalau AI berhasil menjawab, supaya chat kosong tidak menumpuk di Recent.
$docId = (int) ($input['document_id'] ?? 0);
if ($docId > 0) {
    $doc = find_document($uid, $docId);
} else {
    $type = valid_type($input['type'] ?? null);
    if (!$type) {
        json_out(['error' => 'Tipe dokumen tidak valid.'], 422);
    }
    // Chat yang dibuka lewat lampiran tanpa prompt: pakai nama file sebagai judul, bukan 'Chat baru'.
    // Pesan hasil wizard PRD diawali template baku: judulnya diambil dari baris "Ide:" saja.
    $titleSrc = $message;
    if (preg_match('/\n\nIde:\s*(.+)\n/u', $message, $im)) {
        $titleSrc = trim($im[1]);
    }
    $doc = ['id' => 0, 'title' => title_from_message($titleSrc !== '' ? $titleSrc : (string) ($attachments[0]['name'] ?? '')), 'type' => $type, 'content' => ''];
}

if ($doc['id']) {
    $stmt = db()->prepare('SELECT role, content, attachments FROM messages WHERE document_id = ? ORDER BY id');
    $stmt->execute([$doc['id']]);
    $history = $stmt->fetchAll();
} else {
    $history = [];
}

// Release the session lock so other requests aren't blocked while the AI responds.
session_write_close();

try {
    $result = ai_generate($doc, $history, $message, $images, $attachments);
} catch (Throwable $ex) {
    json_out(['error' => $ex->getMessage()], 502);
}

try {
    $pdo = db();
    // Jika koneksi MySQL terputus selama menunggu AI, buat koneksi baru
    try {
        $pdo->query('SELECT 1');
    } catch (Throwable) {
        $pdo = db(true);
    }

    $pdo->beginTransaction();

    if (!$doc['id']) {
        $pdo->prepare('INSERT INTO documents (user_id, title, type, content) VALUES (?, ?, ?, ?)')
            ->execute([$uid, $doc['title'], $doc['type'], '']);
        $doc['id'] = (int) $pdo->lastInsertId();
    }

    $insert = $pdo->prepare('INSERT INTO messages (document_id, role, content, attachments) VALUES (?, ?, ?, ?)');
    $insert->execute([
        $doc['id'],
        'user',
        $message,
        $attachments ? json_encode($attachments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);
    $insert->execute([$doc['id'], 'assistant', $result['reply'], null]);

    if ($result['content'] !== null) {
        $title = $doc['title'];
        $newTitle = ai_title_from_markdown($result['content'], (string) ($doc['type'] ?? ''));
        if ($newTitle !== null) {
            if (
                str_starts_with($title, 'Untitled ')
                || str_starts_with($title, 'Ide:')
                || str_starts_with($title, 'Ide ')
                || str_starts_with($title, 'Chat baru')
                || ($doc['type'] === 'prd' && str_starts_with($newTitle, 'PRD: '))
                || ($doc['type'] === 'design' && str_starts_with($newTitle, 'Design: '))
                || ($doc['type'] === 'planning' && (str_starts_with($newTitle, 'Plan: ') || str_starts_with($newTitle, 'Project Plan: ')))
            ) {
                $title = $newTitle;
            }
        }
        $pdo->prepare('UPDATE documents SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$title, $result['content'], $doc['id']]);
    } else {
        $pdo->prepare('UPDATE documents SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$doc['id']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['error' => 'Gagal menyimpan riwayat ke database: ' . $e->getMessage()], 500);
}

json_out([
    'reply' => $result['reply'],
    'document' => find_document($uid, (int) $doc['id']),
]);
