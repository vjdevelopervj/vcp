<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../includes/images.php';

const UPLOAD_MAX_BYTES = 6 * 1024 * 1024;
const UPLOAD_TEXT_MAX_BYTES = 400 * 1024;
const UPLOAD_MAX_DIM = 1400;

const UPLOAD_IMAGE_MIMES = ['image/png' => true, 'image/jpeg' => true, 'image/webp' => true, 'image/gif' => true];
const UPLOAD_TEXT_EXT = ['txt' => true, 'md' => true, 'markdown' => true, 'csv' => true, 'log' => true, 'json' => true];
const UPLOAD_DOC_MIMES = [
    'application/pdf' => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    // Word lama / zip container kadang dilaporkan finfo begini; tetap disimpan dengan ekstensi whitelist.
    'application/zip' => 'docx',
    'application/msword' => 'docx',
];

const UPLOAD_ERRORS = [
    UPLOAD_ERR_INI_SIZE => 'File melebihi batas ukuran server (upload_max_filesize).',
    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar.',
    UPLOAD_ERR_PARTIAL => 'Upload terpotong, coba lagi.',
    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
    UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak ada.',
    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file di server.',
];

/** Nama file hanya untuk tampilan/prompt: buang path, karakter kontrol, dan dipotong 80 karakter. */
function upload_display_name(mixed $raw): string
{
    $name = is_string($raw) ? trim($raw) : '';
    $name = (string) preg_replace('#.*[\\\\/]#', '', $name);
    $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    // Kurung siku + kurung tajam dibuang: nama ini masuk ke prompt AI dan ke HTML chat,
    // jadi tidak boleh menyisakan sisa tag walau keluarannya sudah di-escape.
    $name = trim(str_replace(['[', ']', '(', ')', '<', '>', '`'], ' ', $name));
    if ($name === '') {
        return 'file';
    }

    return mb_strlen($name) > 80 ? mb_substr($name, 0, 77) . '...' : $name;
}

function upload_dir(int $uid): string
{
    $dir = __DIR__ . '/../uploads/' . $uid;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        json_out(['error' => 'Folder uploads tidak bisa dibuat (cek izin tulis).'], 500);
    }
    return $dir;
}

$user = api_user();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Method not allowed'], 405);
}
if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null))) {
    json_out(['error' => 'Token CSRF tidak valid, muat ulang halaman.'], 403);
}

$file = $_FILES['file'] ?? null;
$code = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
if ($code !== UPLOAD_ERR_OK) {
    json_out(['error' => UPLOAD_ERRORS[$code] ?? 'Upload gagal.'], 422);
}

$display = upload_display_name($file['name'] ?? 'file');
$ext = strtolower((string) pathinfo($display, PATHINFO_EXTENSION));
$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$bytes = (int) $file['size'];

// --- Gambar: dibaca ulang lewat GD lalu disimpan sebagai WebP.
// Sekaligus validasi bahwa file benar-benar gambar, bukan payload yang menyamar.
if (isset(UPLOAD_IMAGE_MIMES[$mime])) {
    if ($bytes > UPLOAD_MAX_BYTES) {
        json_out(['error' => 'Maksimal 6 MB per gambar.'], 422);
    }
    $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$src) {
        json_out(['error' => 'File gambar rusak dan tidak bisa dibaca.'], 422);
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $scale = min(1.0, UPLOAD_MAX_DIM / max($srcW, $srcH));
    $dstW = max(1, (int) round($srcW * $scale));
    $dstH = max(1, (int) round($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);

    upload_dir($uid);
    $relPath = 'uploads/' . $uid . '/' . bin2hex(random_bytes(12)) . '.webp';
    if (!imagewebp($dst, __DIR__ . '/../' . $relPath, 82)) {
        imagedestroy($dst);
        json_out(['error' => 'Gagal menyimpan gambar di server.'], 500);
    }
    imagedestroy($dst);

    json_out([
        'kind' => 'image',
        'name' => $display,
        'url' => $relPath,
        'original' => $mime,
        'width' => $dstW,
        'height' => $dstH,
        'resized_from' => ($dstW !== $srcW || $dstH !== $srcH) ? $srcW . 'x' . $srcH : null,
        'facts' => image_facts(__DIR__ . '/../' . $relPath),
    ]);
}

// --- Teks: isinya nanti dibacakan ke AI. Disimpan sebagai .txt supaya ekstensi yang
// melayang di folder uploads selalu dari whitelist, bukan dari nama buatan user.
if (isset(UPLOAD_TEXT_EXT[$ext]) && (str_starts_with($mime, 'text/') || $mime === 'application/json')) {
    if ($bytes > UPLOAD_TEXT_MAX_BYTES) {
        json_out(['error' => 'File teks maksimal 400 KB — potong yang relevan saja ya.'], 422);
    }
    $content = (string) file_get_contents($file['tmp_name']);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = (string) @iconv('UTF-8', 'UTF-8//IGNORE', (string) @iconv('ISO-8859-1', 'UTF-8', $content));
    }
    $content = (string) preg_replace('/\r\n|\r/', "\n", $content);
    $content = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $content);
    if (trim($content) === '') {
        json_out(['error' => 'File teks itu kosong.'], 422);
    }

    upload_dir($uid);
    $relPath = 'uploads/' . $uid . '/' . bin2hex(random_bytes(12)) . '.txt';
    if (file_put_contents(__DIR__ . '/../' . $relPath, $content) === false) {
        json_out(['error' => 'Gagal menyimpan file di server.'], 500);
    }

    json_out([
        'kind' => 'text',
        'name' => $display,
        'url' => $relPath,
        'original' => $mime,
        'bytes' => strlen($content),
        'chars' => mb_strlen($content),
    ]);
}

// --- PDF / DOCX: cuma disimpan supaya bisa diunduh lagi dari chat. AI tidak bisa membacanya.
if (isset(UPLOAD_DOC_MIMES[$mime])) {
    if ($bytes > UPLOAD_MAX_BYTES) {
        json_out(['error' => 'Maksimal 6 MB per file PDF/DOCX.'], 422);
    }
    $storeExt = UPLOAD_DOC_MIMES[$mime];
    upload_dir($uid);
    $relPath = 'uploads/' . $uid . '/' . bin2hex(random_bytes(12)) . '.' . $storeExt;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $relPath)) {
        json_out(['error' => 'Gagal menyimpan file di server.'], 500);
    }

    json_out([
        'kind' => 'doc',
        'name' => $display,
        'url' => $relPath,
        'original' => $mime,
        'bytes' => $bytes,
    ]);
}

json_out(['error' => 'Format file belum didukung. Pakai gambar (PNG/JPEG/WebP/GIF), teks (.txt/.md/.csv/.json), PDF, atau DOCX.'], 422);
