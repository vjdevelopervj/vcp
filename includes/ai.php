<?php

const DOC_START = '<<<DOCUMENT';
const DOC_END = 'DOCUMENT>>>';
// Isi file teks dipotong di sini supaya satu lampiran tidak memakan seluruh konteks.
const MAX_TEXT_PROMPT_CHARS = 12000;

function ai_system_prompt(string $type): string
{
    $focus = [
        'planning' => 'a project PLANNING document: overview, goals, scope (in/out), milestones & timeline, task breakdown, team/roles, risks & mitigations, success metrics.',
        'design' => 'a DESIGN document: design principles, user personas, user flows, information architecture, page/screen inventory, UI components, design tokens (colors, typography, spacing), accessibility notes, and technical architecture where relevant.',
        'prd' => 'a PRODUCT REQUIREMENTS DOCUMENT (PRD).',
    ][$type];

    $base = <<<PROMPT
You are VCP, an expert product manager, software architect and product designer.
You help the user write {$focus}

Rules:
- Reply in the same language the user writes in.
- Bahasa user boleh gaul, singkatan, atau campur Inggris. Terjemahkan dulu maksudnya, baru kerjakan. Jangan mengoreksi gaya bahasanya dan jangan menanyakan arti kata gaul.
  Kamus cepat (tulis user → maksud di dokumen): gua/gue/gw/aku → saya · lu/elo → pengguna target · pengen/pingin/mau → ingin · bikin → membuat · gak/ga/ndak/tak → tidak · udah/udh/sdh → sudah · gmn/gimana → bagaimana · bgt/parah → sangat · kek/kyk/kayak → seperti · utk/dpt/skrng/trs → untuk/dapat/sekarang/terus · notif/notip → notifikasi · app → aplikasi · web-an → situs web · cuan → keuntungan · boncos → rugi · ribet → rumit · gak ribet/gampang → sederhana & mudah dipakai · rame → trafik tinggi · lemot → performa lambat · crash/mati → error · login pake google → OAuth Google · dark mode → tema gelap · bisa bayar/checkout → pembayaran in-app · chat kayak WA → pesan instan · tracking lokasi → lokasi real-time · reminder → pengingat · to-do → daftar tugas · wishlist → daftar simpanan · deadline → tenggat · brief/scope → ruang lingkup · review/feedback → masukan · export/share → ekspor & berbagi
  - Kalau prompt user gaul/singkatan/campur Inggris: mulai balasan dengan SATU kalimat "Kupahami: <maksudnya dengan istilah yang benar>", mis. "Kupahami: aplikasi POS untuk warung makan dengan laporan harian." — supaya user langsung tahu terjemahannya benar. Kalau prompt sudah formal dan jelas (mis. daftar "Pilihan platform & teknologi"), kerjakan langsung tanpa kalimat itu.
  - Prompt pendek/parsial ("app kasir", "bikinin prd dong") TETAP dikerjakan: pakai konteks yang ada, isi sisanya dengan asumsi wajar, dan tulis asumsi itu di bab asumsi/TBD — bukan ditanya balik.
  - Minta klarifikasi hanya kalau maksudnya benar-benar tidak bisa ditebak (mis. tidak jelas dokumen apa yang diminta).
  - Nada balasan chat boleh santai mengikuti gaya user, tetap sopan dan ringkas. ISI DOKUMEN selalu bahasa Indonesia formal: istilah gaul diganti istilah teknis yang tepat — termasuk di dalam sel tabel, judul, dan glosarium (mis. sel "Gua/Pemilik Warung" ditulis "Pemilik warung"). Kutipan ucapan user pun ditulis ulang formal: jangan menulis aplikasi "lebih gak ribet", tulis aplikasi "lebih sederhana". Dokumen yang masih memuat satu saja kata gaul dari kamus di atas harus ditulis ulang dengan istilah formal sebelum dikirim.
- Always respond in exactly two parts:
  1. A short conversational reply (1-4 sentences) explaining what you did or asking clarifying questions.
  2. The COMPLETE, updated markdown document wrapped between a line containing only `<<<DOCUMENT` and a line containing only `DOCUMENT>>>`.
- HANYA dokumen yang boleh berada di antara kedua marker itu. Kalimat pembuka, ringkasan, atau bagian "Konteks Singkat" harus tetap di luar marker.
- The document must be valid GitHub-flavored markdown starting with a single `# Title` heading. Use headings, lists, tables and checklists where useful.
- When the user requests a change, rewrite the whole document with the change applied; never return only a diff.
- If the request is unrelated to the document, reply normally and return the current document unchanged.
- Treat the conversation as the source material (like meeting notes / MoM between developer and client).
- Kirim dokumen LENGKAP tepat satu kali. Jangan mengulang, menduplikasi, atau mencetak ulang dokumen di dalam balasan.
PROMPT;

    if ($type === 'prd' && is_readable(__DIR__ . '/../template/TEMPLATE_PRD.md')) {
        $template = file_get_contents(__DIR__ . '/../template/TEMPLATE_PRD.md');
        // Model menyalin struktur template secara persis, jadi mandat mermaid/ERD
        // disuntikkan ke skeleton-nya (instruksi di luar skeleton sering diabaikan).
        $template = str_replace(
            '## 7.1 {{NAMA_ALUR_1}}',
            "## 7.1 {{NAMA_ALUR_1}}\n\n<!-- WAJIB: setelah langkah-langkah 7.1, sertakan PERSIS SATU blok kode ```mermaid berisi flowchart TD alur aplikasi utama (happy path + jalur alternatif penting). Jangan menambah blok mermaid lain di Bab 7. Semua label node WAJIB diapit tanda kutip ganda: A[\"Cari tiket (asal, tujuan)\"] bukan A[Cari tiket (asal, tujuan)] karena kurung di label tanpa kutip membuat diagram error. -->",
            $template
        );
        // Blok mermaid dituangkan apa adanya ke skeleton (bukan cuma diminta): model menyalin
        // struktur yang sudah ada, tapi sering lupa menambah blok yang belum ada.
        $template = str_replace(
            "1. {{LANGKAH_1}}\n2. {{LANGKAH_2}}",
            "1. {{LANGKAH_1}}\n2. {{LANGKAH_2}}\n\n```mermaid\nflowchart TD\n    A[\"Mulai\"] --> B[\"Langkah 1\"]\n    B --> C{\"Pengecekan?\"}\n    C -->|\"Ya\"| D[\"Sukses\"]\n    C -->|\"Tidak\"| B\n```\n<!-- Blok flowchart di atas SUDAH ada di template dan TIDAK boleh dihapus: ganti isi kutipnya dengan alur nyata aplikasi ini, minimal 6 node, sertakan jalur alternatif dan status penting (mis. \"Menunggu Pembayaran\"). Baris pertama tetap `flowchart TD`. Setiap label node dan label panah diapit tanda kutip ganda. PRD tanpa blok flowchart di Bab 7 dianggap TIDAK LENGKAP.-->",
            $template
        );
        $template = str_replace(
            '| **{{ENTITAS_1}}** | {{FIELD_UTAMA}} | {{KETERANGAN}} |',
            "| **{{ENTITAS_1}}** | {{FIELD_UTAMA}} | {{KETERANGAN}} |\n\n<!-- Kolom Field Utama WAJIB memakai tipe field asli database yang dipilih user di 5.1, dan nama database itu disebut dalam satu kalimat sebelum tabel. MySQL/MariaDB: id INT AUTO_INCREMENT, name VARCHAR(100), created_at DATETIME. PostgreSQL: SERIAL, TEXT. SQLite: INTEGER PRIMARY KEY AUTOINCREMENT. MongoDB/Firestore: sebut koleksi dan dokumennya, bukan tabel relasional. Kalau 5.1 tidak ada database (client-only), jangan tulis skema: ganti tabel ini jadi entitas logis dan jelaskan datanya disimpan di mana (state aplikasi, localStorage, IndexedDB). -->",
            $template
        );
        $template = str_replace(
            '**Catatan:** field dalam [tanda kurung siku] merupakan bagian dari fitur usulan/Fase Lanjutan (Bab 11).',
            "**Catatan:** field dalam [tanda kurung siku] merupakan bagian dari fitur usulan/Fase Lanjutan (Bab 11).\n\n## 8.1 Diagram ERD\n\n<!-- WAJIB: sub-bab ini berisi PERSIS SATU blok kode ```mermaid yang baris pertamanya adalah kata erDiagram (BUKAN flowchart), memodelkan entitas pada tabel Bab 8 dengan relasi seperti ||--o{. Contoh bentuk:\n```mermaid\nerDiagram\n    PENGGUNA ||--o{ PESANAN : membuat\n    PRODUK }o--|| KATEGORI : termasuk\n``` -->",
            $template
        );
        // Sub-bab 5.1 ikut disuntikkan ke skeleton: instruksi "tambahkan sub-bab baru" saja
        // sering diabaikan karena model menyalin struktur template apa adanya.
        $template = str_replace(
            '- {{ASUMSI_2}}',
            "- {{ASUMSI_2}}\n\n## 5.1 Stack Teknologi\n\n<!-- WAJIB: satu baris per pilihan, LABEL-nya persis seperti daftar \"Pilihan platform & teknologi\" dari user, contoh format:\n- Kategori aplikasi: Web\n- Teknologi frontend: Laravel Blade + Alpine\n- Bahasa pemrograman: PHP\n- Perlu backend: Ya, butuh backend\n- Backend: Backend custom (dibangun sendiri)\n- Framework backend: Laravel\n- Perlu database: Ya, butuh database\n- Database: MySQL/MariaDB\nNilai pada contoh di atas BUKAN yang ditulis — pakai nilai dari user. Baris yang tidak ditanyakan user jangan dikarang.\nKalau user menjawab \"belum tahu (tolong rekomendasikan beserta alasannya)\", format barisnya persis: nama teknologi diikuti tanda kurung yang harus ditulis apa adanya, lalu alasannya setelah tanda strip. Contoh bentuk (jangan salin isinya):\n- Teknologi frontend: Flutter (rekomendasi, perlu dikonfirmasi) — alasan: satu codebase untuk Android dan iOS\n- Database: PostgreSQL (rekomendasi, perlu dikonfirmasi) — alasan: relasi transaksi dan produk banyak\nTulisan (rekomendasi, perlu dikonfirmasi) wajib muncul di SETIAP baris hasil rekomendasi, bukan hanya sebagian, dan tiap baris itu wajib diulang jadi satu butir Bab 12. Sub-bab yang rekomendasi-nya tidak ditandai persis begitu dianggap TIDAK LENGKAP.\nKalau percakapan belum membahas teknologi sama sekali, tulis \"- Belum dibahas (perlu dikonfirmasi user)\".-->",
            $template
        );
        $template = str_replace(
            '- {{PERTANYAAN_1}}',
            "- {{PERTANYAAN_1}}\n\n<!-- WAJIB: buatkan SATU butir untuk SETIAP baris 5.1 yang ditandai (rekomendasi, perlu dikonfirmasi) — jangan hanya satu. Contoh format: \"- Database: PostgreSQL — rekomendasi pengembang, belum dikonfirmasi user.\" Baru tambahkan hal lain yang belum diputuskan (biaya, kebijakan, timeline). -->",
            $template
        );
        $base .= <<<PROMPT


## PRD TEMPLATE (WAJIB DIKIRIMI)
Untuk dokumen PRD, struktur dokumen WAJIB mengikuti template di bawah ini secara persis:
- Bab dan penomoran sama, format tabel sama, placeholder {{...}} diisi dari isi percakapan.
- Komentar HTML <!-- ... --> di template hanyalah panduan untukmu dan TIDAK boleh muncul di output.
- Patuhi semua aturan di bagian instruksi template (ID kebutuhan PREFIX-N, prioritas Wajib/Penting/Fase 2, informasi yang belum diputuskan masuk Bab 12 / TBD, jangan mengarang, versi v0.1).
- Tambahan WAJIB agar PRD makin lengkap:
  1. Pada Bab 7 (Alur Pengguna Utama), SETELAH deskripsi alur 7.1, sertakan PERSIS SATU blok kode fenced ```mermaid berisi flowchart TD alur aplikasi utama (happy path + jalur alternatif penting). Jangan menambah blok mermaid lain di dalam Bab 7.
  2. WAJIB tambahkan sub-bab baru "## 8.1 Diagram ERD" tepat setelah tabel Bab 8 (Model Data), berisi PERSIS SATU blok kode fenced ```mermaid yang baris pertamanya adalah kata erDiagram, memodelkan entitas di Bab 8 dengan relasi ||--o{ atau }o--||. Blok di 8.1 BUKAN flowchart. Bentuknya seperti ini:
     ```mermaid
     erDiagram
         PENGGUNA ||--o{ PESANAN : membuat
         PRODUK }o--|| KATEGORI : termasuk
     ```
  Dokumen PRD tanpa blok flowchart di Bab 7 ATAU tanpa blok erDiagram di 8.1 dianggap TIDAK LENGKAP dan harus dilengkapi sebelum dikirim.
  3. Aturan SYNTAX MERMAID (dipelanggar = diagram error saat dirender, jadi cek ulang sebelum mengirim):
     - Baris pertama blok flowchart selalu deklarasi arah: flowchart TD atau flowchart LR.
     - SETIAP label node diapit tanda kutip ganda, termasuk yang terasa aman: A["Pengguna login"] --> B["Dashboard"], dan H{"Pembayaran sukses?"}.
     - Label di atas panah juga dikutip: A -->|"Ya (setelah verifikasi)"| B.
     - Penyebab error paling umum: kurung di label tanpa kutip. SALAH A[Parameter (Asal, Tujuan)] -> BENAR A["Parameter (Asal, Tujuan)"].
     - Jangan menaruh tag HTML, emoji, atau titik-koma di dalam label. Maksimal 6 kata per label.
- Jika pesan user memuat daftar "Pilihan platform & teknologi", itu KEPUTUSAN RESMI: pakai persis seperti ditulis, jangan mengganti teknologi, dan jangan menanyakan ulang hal yang sudah dijawab.
  1. Sub-bab "## 5.1 Stack Teknologi" sudah tersedia di template: pakai LABEL persis dari daftar user dan NILAI persis pilihan user, satu baris per item. Menulis "Laravel" saja tanpa baris "Bahasa pemrograman: ..." dan baris database dianggap mengabaikan pilihan user.
     Dokumen PRD yang sub-bab 5.1-nya tidak punya baris "Bahasa pemrograman: ..." dan baris database ("Database: ..." kalau user butuh, atau "Perlu database: Tidak..." kalau tidak) dianggap TIDAK LENGKAP dan harus dilengkapi sebelum dikirim.
  2. Bab 8 Model Data: kalau "Perlu database" = Ya, sebutkan nama database yang dipilih di kalimat pembuka, lalu pakai tipe field yang benar untuk database itu (MySQL/MariaDB: INT AUTO_INCREMENT, VARCHAR(n); PostgreSQL: SERIAL, TEXT; SQLite: INTEGER PRIMARY KEY AUTOINCREMENT; MongoDB/Firestore: koleksi/dokumen, bukan tabel relasional). Kalau "Perlu database" = Tidak, jangan bikin skema tabel: tulis Bab 8 sebagai model entitas logis dan jelaskan datanya disimpan di mana (state aplikasi, localStorage/IndexedDB, atau file).
  3. Baris "Perlu backend: Tidak, tanpa backend (client-only)" berarti tidak ada server: jangan tambahkan API endpoint, framework server, atau migrations di bab manapun. Kalau user tetap memilih butuh database, tulis sebagai penyimpanan sisi klien/BaaS dan catat sebagai hal yang perlu dikonfirmasi di Bab 12.
  4. Bab 12 (TBD) dan milestone/fitur lanjutan: tulis pekerjaan teknis sesuai stack itu juga (mis. "buat migrasi Laravel", bukan "buat migrasi Django").

=== TEMPLATE PRD (MULAI) ===
{$template}
=== TEMPLATE PRD (SELESAI) ===
PROMPT;
    }

    if ($type === 'design' && is_readable(__DIR__ . '/../template/UIUX_TECHNICAL_DICTIONARY.md')) {
        $dictionary = file_get_contents(__DIR__ . '/../template/UIUX_TECHNICAL_DICTIONARY.md');
        $base .= <<<PROMPT


## REFERENSI GAMBAR UI (Khusus dokumen Design)
Dokumen Design yang bagus berangkat dari referensi visual nyata, bukan asumsi. Aturannya:

1. Kalau user belum mengirim gambar referensi sama sekali (tidak di pesan ini, tidak di riwayat), balasan pertamamu WAJIB memintanya: 1-3 tangkapan layar UI yang jadi acuan — misalnya screenshot aplikasi sejenis, shot Dribbble/Behance, atau halaman design system. Sebutkan bahwa gambar itu akan dianalisis lalu disematkan langsung ke dalam dokumen. Tetap susun draf dokumen dari informasi yang sudah ada, dan tandai bagian visual yang masih menunggu referensi dengan "(menunggu referensi gambar)".
2. Begitu ada gambar dikirim, SEMUA gambar wajib disisipkan di dokumen, pada sub-bab `## Referensi Visual` di bagian awal (tepat setelah judul dan baris meta), satu sub-sub-bab per gambar:
   ### Referensi 1 — <nama singkat>
   ![<alt text deskriptif>](<url>)
   Lanjutkan dengan analisis berformat: **Design language** (istilah dari kamus: glassmorphism, bento grid, neubrutalism, dll.), **Palet terdeteksi** (tabel hex + porsi pixel), **Tipografi**, **Spacing & radius**, **Elevation & border**, lalu **Diadopsi ke dokumen ini** (keputusan konkret: nilai hex untuk token warna, px untuk spacing/radius, nama komponen yang dipakai).
3. Salin URL gambar PERSIS seperti yang diberikan (contoh: uploads/3/a1b2c3...webp) ke dalam markdown `![]()`. Jangan mengubah, menyingkat, atau menggabungkan URL.
4. Kamu TIDAK bisa melihat isi gambar. Data visual yang kamu miliki hanya hasil ekstraksi pixel yang kusertakan (dimensi, rasio, tone gelap/terang, palet hex beserta porsi, warna aksen paling saturasi) ditambah deskripsi user. DILARANG mengarang detail yang tidak ada di data itu. Kalau ada yang dibutuhkan tapi tidak ada di data, tanyakan ke user.
5. Saat merevisi dokumen, sub-bab Referensi Visual beserta gambar-gambarnya tidak boleh hilang, berubah urutan, atau berubah URL kecuali user memintanya.

## UI/UX TECHNICAL DICTIONARY (WAJIB DIPAKAI)
Untuk dokumen Design, gunakan vocabulary dari kamus di bawah ini: sebut hex code spesifik (bukan "biru muda"), nilai spacing numerik dan token (8px grid, md/lg/xl), nama design language yang tepat (glassmorphism, bento grid, dsb.), istilah komponen/state/motion/a11y yang benar. Jangan menggambarkan visual secara kabur.

=== DICTIONARY (MULAI) ===
{$dictionary}
=== DICTIONARY (SELESAI) ===
PROMPT;
    }

    return $base;
}

function ai_build_messages(array $document, array $history, string $userMessage, array $images = [], array $attachments = []): array
{
    $messages = [['role' => 'system', 'content' => ai_system_prompt($document['type'])]];

    if (trim($document['content']) !== '') {
        $messages[] = [
            'role' => 'system',
            'content' => "Current document:\n" . DOC_START . "\n" . $document['content'] . "\n" . DOC_END,
        ];
    }

    $historyImages = [];
    foreach (array_slice($history, -12) as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        foreach ((array) json_decode((string) ($msg['attachments'] ?? ''), true) as $att) {
            // Baris lama menyimpan array of string; yang baru menyimpan object {url,kind,name}.
            $url = is_array($att) ? ($att['url'] ?? '') : $att;
            $kind = is_array($att) ? ($att['kind'] ?? 'image') : 'image';
            if ($kind === 'image' && is_string($url) && $url !== '' && !in_array($url, $images, true)) {
                $historyImages[] = $url;
            }
        }
    }

    $notes = ai_image_notes($images);
    if ($historyImages) {
        $notes .= ($notes ? "\n" : '') . '[Gambar referensi yang sudah pernah dikirim di chat ini — tetap pertahankan di dokumen]\n- '
            . implode("\n- ", $historyImages);
    }
    $notes .= ($notes ? "\n\n" : '') . ai_file_notes($attachments);
    $userContent = trim($userMessage);
    if ($userContent === '') {
        // Kirim lampiran tanpa prompt: model perlu tahu apa yang diharapkan darinya,
        // bukan cuma fakta teknis dari file-nya.
        $typeName = DOC_TYPES[$document['type']] ?? $document['type'];
        $userContent = "Aku lampirkan file/gambar ini (tanpa catatan tambahan). Tolong baca lampirannya "
            . 'dan susunkan dokumen ' . $typeName . ' lengkap berdasarkan isinya.';
    }
    $messages[] = ['role' => 'user', 'content' => $userContent . ($notes ? "\n\n" . $notes : '')];

    return $messages;
}

/**
 * Lampiran non-gambar: isi file teks dibacakan ke model, PDF/DOCX cuma disebutkan
 * namanya (belum ada parser-nya) supaya model tahu file itu ada dan tidak mengarang isinya.
 */
function ai_file_notes(array $attachments): string
{
    $text = [];
    $docs = [];
    foreach ($attachments as $att) {
        if (($att['kind'] ?? '') === 'text') {
            $text[] = $att;
        } elseif (($att['kind'] ?? '') === 'doc') {
            $docs[] = $att;
        }
    }
    if (!$text && !$docs) {
        return '';
    }

    $out = [];
    foreach ($text as $att) {
        $path = __DIR__ . '/../' . $att['url'];
        if (!is_readable($path)) {
            continue;
        }
        $body = (string) file_get_contents($path);
        $truncated = mb_strlen($body) > MAX_TEXT_PROMPT_CHARS;
        if ($truncated) {
            $body = mb_substr($body, 0, MAX_TEXT_PROMPT_CHARS);
        }
        $out[] = "=== ISI FILE \"{$att['name']}\" ===\n" . $body
            . ($truncated ? "\n(isi file terpotong di " . MAX_TEXT_PROMPT_CHARS . " karakter)" : '')
            . "\n=== SELESAI FILE \"{$att['name']}\" ===";
    }
    if ($docs) {
        $lines = array_map(
            static fn (array $a): string => '- ' . $a['name'] . ' (' . basename((string) $a['url']) . ')',
            $docs
        );
        $out[] = "[File yang dilampirkan user tapi TIDAK bisa kubacakan isinya: \n" . implode("\n", $lines)
            . "\nJangan mengarang isinya. Kalau isinya penting untuk dokumen, minta user menyalin bagian relevan ke chat atau mengirim versi .txt/.md.]";
    }

    return implode("\n\n", $out);
}

/**
 * Fakta visual hasil ekstraksi pixel, ditempel ke pesan user supaya model
 * (yang text-only) punya angka nyata untuk dianalisis alih-alih mengarang.
 */
function ai_image_notes(array $images): string
{
    if (!$images) {
        return '';
    }
    require_once __DIR__ . '/images.php';

    $lines = ['[Gambar referensi yang baru saja diunggah user — angka di bawah hasil ekstraksi pixel, bukan karangan]'];
    foreach ($images as $i => $url) {
        $facts = image_facts(__DIR__ . '/../' . $url);
        if (!$facts) {
            $lines[] = ($i + 1) . '. ' . $url . " — file tidak terbaca, tetap sematkan URL-nya\n   Sematkan: ![Referensi](" . $url . ")";
            continue;
        }
        $palette = implode(', ', array_map(
            static fn (array $c): string => $c['hex'] . ' (' . $c['share'] . '%)',
            $facts['palette']
        ));
        $lines[] = sprintf(
            "%d. %s — %dx%d, rasio %s, tone %s\n   Palet: %s\n   Warna aksen paling saturasi: %s\n   Sematkan: ![<alt deskriptif>](%s)",
            $i + 1,
            $url,
            $facts['width'],
            $facts['height'],
            $facts['ratio'],
            $facts['tone'],
            $palette ?: '(tidak terdeteksi)',
            $facts['accent'],
            $url
        );
    }
    $lines[] = 'CHECKLIST WAJIB SEBELUM MENULIS BALASAN: dokumen harus punya sub-bab `## Referensi Visual` yang memuat SEMUA URL di atas dalam bentuk markdown `![alt](url)` — ' . count($images) . ' gambar, tidak boleh ada yang hilang. Dokumen tanpa embed gambar dianggap tidak lengkap.';

    return implode("\n", $lines);
}

/**
 * @return array{reply: string, content: ?string}
 */
function ai_generate(array $document, array $history, string $userMessage, array $images = [], array $attachments = []): array
{
    if (AI_API_KEY === '') {
        return ai_demo($document, $userMessage, $images);
    }

    $messages = ai_build_messages($document, $history, $userMessage, $images, $attachments);
    $text = ai_complete($messages);
    $result = ai_parse($text);

    return ai_ensure_reference_images($result, $images);
}

/**
 * Satu panggilan chat/completions.
 *
 * @param array<int, array<string, mixed>> $messages
 */
function ai_complete(array $messages): string
{
    $host = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''))[0]);
    $isLocal = empty($host) || in_array($host, ['localhost', '127.0.0.1', 'vcp.test'], true) || str_ends_with($host, '.test');

    // Di shared hosting gratis (seperti InfinityFree), batas waktu eksekusi PHP dibatasi ketat 20 detik oleh server.
    // Batasi timeout cURL ke 15 detik dan token ke 3000 agar respons selesai sebelum server memutus koneksi paksa.
    $effectiveTimeout = $isLocal ? AI_TIMEOUT : min(AI_TIMEOUT, 15);
    $effectiveTokens = $isLocal ? AI_MAX_TOKENS : min(AI_MAX_TOKENS, 3000);

    $payload = [
        'model' => AI_MODEL,
        'messages' => $messages,
        'temperature' => 0.4,
        'max_tokens' => $effectiveTokens,
    ];
    if (AI_THINKING === false) {
        $payload['chat_template_kwargs'] = ['thinking' => false];
    }

    $raw = false;
    $status = 0;
    $error = '';
    $errno = 0;

    $ch = curl_init(rtrim(AI_BASE_URL, '/') . '/chat/completions');
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $effectiveTimeout,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ];
    if (defined('AI_SSL_VERIFY') && !AI_SSL_VERIFY) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    // Fallback jika sertifikat CA lokal di server hosting bermasalah (error 60 / 77)
    if ($raw === false && ($errno === 60 || $errno === 77)) {
        $ch = curl_init(rtrim(AI_BASE_URL, '/') . '/chat/completions');
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    }

    if ($raw === false) {
        throw new RuntimeException('Gagal menghubungi AI provider: ' . $error);
    }
    $data = json_decode($raw, true);
    if ($status >= 400 || !isset($data['choices'][0]['message']['content'])) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $status);
        if (!isset($data['error']) && ($data['choices'][0]['finish_reason'] ?? null) === 'length') {
            $msg = 'AI berhenti karena limit token (finish_reason=length) sebelum menulis jawaban. Perbesar AI_MAX_TOKENS di config.php atau matikan AI_THINKING.';
        }
        throw new RuntimeException('AI provider error: ' . $msg);
    }

    return (string) $data['choices'][0]['message']['content'];
}

/**
 * Jaring pengaman: model kadang memakai angka paletnya tapi lupa menempelkan
 * gambarnya. URL yang hilang disisipkan ulang ke sub-bab Referensi Visual supaya
 * dokumen tetap lengkap seperti yang diminta user.
 *
 * @param array{reply: string, content: ?string} $result
 * @return array{reply: string, content: ?string}
 */
function ai_ensure_reference_images(array $result, array $images): array
{
    if (!$images || !is_string($result['content']) || $result['content'] === '') {
        return $result;
    }

    $markdown = $result['content'];
    $missing = array_values(array_filter($images, static fn (string $url): bool => !str_contains($markdown, $url)));
    if (!$missing) {
        return $result;
    }

    $embeds = implode(
        "\n\n",
        array_map(static fn (string $url): string => "![Gambar referensi UI](" . $url . ")", $missing)
    );

    if (preg_match('/^##[^\n]*Referensi Visual[^\n]*$/mi', $markdown, $m, PREG_OFFSET_CAPTURE)) {
        $after = $m[0][1] + strlen($m[0][0]);
        $markdown = rtrim(substr($markdown, 0, $after)) . "\n\n" . $embeds . "\n\n" . ltrim(substr($markdown, $after));
    } elseif (preg_match('/^## /m', $markdown, $m, PREG_OFFSET_CAPTURE)) {
        $markdown = rtrim(substr($markdown, 0, $m[0][1])) . "\n\n## Referensi Visual\n\n" . $embeds . "\n\n" . substr($markdown, $m[0][1]);
    } else {
        $markdown = rtrim($markdown) . "\n\n## Referensi Visual\n\n" . $embeds . "\n";
    }

    $result['content'] = $markdown;
    $result['reply'] .= "\n\n(Gambar referensi otomatis kusematkan di sub-bab Referensi Visual supaya tidak hilang dari dokumen.)";

    return $result;
}

function ai_parse(string $text): array
{
    // Baris `<<<DOCUMENT>>>` yang menyatu punya dua makna: penutup+_pembuka (kalau model
    // mengirim ulang dokumennya) atau hanya pembuka (kalau ia salah tanda tapi dokumennya
    // masih satu). Salah tebak bikin dokumen hilang, jadi dilihat konteksnya dulu.
    $startRe0 = '/^' . preg_quote(DOC_START, '/') . '[ \t]*$/m';
    if (preg_match_all('/^<<<DOCUMENT[ \t]*>>>[ \t]*$/m', $text, $merged, PREG_OFFSET_CAPTURE)) {
        for ($i = count($merged[0]) - 1; $i >= 0; $i--) {
            $pos = $merged[0][$i][1];
            $before = substr($text, 0, $pos);
            $penutup = preg_match($startRe0, $before) === 1 || preg_match('/^# /m', $before) === 1;
            $text = substr($text, 0, $pos)
                . ($penutup ? DOC_END . "\n" . DOC_START : DOC_START)
                . substr($text, $pos + strlen($merged[0][$i][0]));
        }
    }
    // Marker yang ikut terformat markdown (### <<<DOCUMENT) atau terbalik (>>>DOCUMENT).
    $text = preg_replace('/^[ \t]*#{1,6}[ \t]*(<<<DOCUMENT|DOCUMENT>>>)[ \t]*$/m', '$1', $text);
    $text = preg_replace('/^[ \t]*>>>[ \t]*DOCUMENT[ \t]*$/m', DOC_END, $text);
    $endRe = '/^' . preg_quote(DOC_END, '/') . '[ \t]*$/m';
    $startRe = '/^' . preg_quote(DOC_START, '/') . '[ \t]*$/m';

    preg_match_all($endRe, $text, $ends, PREG_OFFSET_CAPTURE);
    $endPos = array_column($ends[0], 1);
    $lastEnd = $endPos ? $endPos[count($endPos) - 1] : null;

    $hasStart = preg_match($startRe, $text, $sm, PREG_OFFSET_CAPTURE) === 1;

    if ($hasStart && (!$endPos || $endPos[0] > $sm[0][1])) {
        $contentStart = $sm[0][1] + strlen(DOC_START);
    } elseif ($endPos) {
        // Block emitted without a start marker: begin at the first '# ' heading before it.
        $before = substr($text, 0, $endPos[0]);
        if (preg_match('/^# /m', $before, $hm, PREG_OFFSET_CAPTURE)) {
            $contentStart = $hm[0][1];
        } else {
            $contentStart = 0;
        }
    } else {
        return ['reply' => trim($text), 'content' => null];
    }

    $contentEnd = strlen($text);
    foreach ($endPos as $p) {
        if ($p >= $contentStart) {
            $contentEnd = $p;
            break;
        }
    }
    $content = trim(substr($text, $contentStart, $contentEnd - $contentStart));
    if (preg_match('/^```(?:markdown|md)[ \t]*\n(.*)\n```[ \t]*$/s', $content, $cm)) {
        $content = $cm[1];
    } elseif (str_starts_with($content, "```\n") && str_ends_with($content, "\n```")) {
        $content = substr($content, 4, -4);
    }
    $content = trim($content);
    $content = trim(preg_replace('/<!--.*?-->/s', '', $content));

    $suffix = $lastEnd !== null && $lastEnd >= $contentEnd ? substr($text, $lastEnd + strlen(DOC_END)) : '';
    $reply = trim(preg_replace('/^<<<DOCUMENT[ \t]*$|^DOCUMENT>>>[ \t]*$/m', '', trim(substr($text, 0, $contentStart)) . "\n" . trim($suffix)));
    if ($reply === '') {
        $reply = 'Dokumen sudah diperbarui. Cek preview di sebelah kanan.';
    }

    // Model kadang membalik: ringkasan yang dibungkus marker, dokumennya dibiarkan di luar,
    // sehingga preview cuma menampilkan paragraf pembuka. Dokumen dikenali dari headingnya,
    // jadi kalau bagian dalam marker tidak punya heading padahal reply masih menyimpan teks
    // yang lebih panjang ber-heading, tukar posisinya.
    if ($content !== '' && !preg_match('/^#{1,2} /m', $content) && preg_match('/^# /m', $reply, $hm, PREG_OFFSET_CAPTURE)) {
        $moved = trim(substr($reply, $hm[0][1]));
        if (strlen($moved) > strlen($content)) {
            $reply = trim(substr($reply, 0, $hm[0][1]) . "\n\n" . $content);
            $content = $moved;
        }
    }

    // Blokir terakhir: isi dalam marker yang sama sekali tidak bergaya dokumen (tanpa heading)
    // jangan pernah jadi konten — kalau tidak, satu balasan ngawur menimpa dokumen user yang sah.
    // Teksnya dipindah ke reply saja supaya user tetap melihat apa yang model tulis.
    if ($content !== '' && !preg_match('/^#{1,2} /m', $content)) {
        $reply = trim($reply . "\n\n" . $content);
        return ['reply' => $reply, 'content' => null];
    }

    return ['reply' => $reply, 'content' => trim($content)];
}

function ai_title_from_markdown(string $markdown): ?string
{
    if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
        return mb_substr(trim($m[1]), 0, 150);
    }
    return null;
}

function ai_demo(array $document, string $userMessage, array $images = []): array
{
    $idea = trim(preg_replace('/\s+/', ' ', $userMessage));
    $name = mb_strlen($idea) > 60 ? mb_substr($idea, 0, 57) . '...' : $idea;
    $date = date('d M Y');
    $embeds = ai_demo_embeds($images);

    if (trim($document['content']) !== '') {
        $content = $document['content'] . $embeds . "\n\n## Catatan Revisi\n\n- **{$date}:** {$idea}\n";
        return [
            'reply' => "Mode demo aktif (belum ada API key), jadi permintaanmu aku tambahkan sebagai catatan revisi. Isi `AI_API_KEY` di config.php supaya AI bisa merevisi dokumen sepenuhnya.",
            'content' => $content,
        ];
    }

    $templates = [
        'planning' => <<<MD
# Project Plan: {$name}

> Dibuat oleh VCP pada {$date}

## 1. Overview
{$idea}

## 2. Goals
- Meluncurkan MVP yang dapat digunakan oleh pengguna awal
- Memvalidasi kebutuhan pasar dalam 3 bulan pertama
- Membangun fondasi teknis yang mudah dikembangkan

## 3. Scope
| In Scope | Out of Scope |
|---|---|
| Fitur inti MVP | Aplikasi mobile native |
| Autentikasi pengguna | Integrasi pihak ketiga lanjutan |
| Dashboard dasar | Multi-bahasa |

## 4. Milestones
| # | Milestone | Durasi | Output |
|---|---|---|---|
| 1 | Discovery & Riset | 1 minggu | Brief & persona |
| 2 | Desain UI/UX | 2 minggu | Prototype Figma |
| 3 | Development MVP | 4 minggu | Aplikasi siap uji |
| 4 | QA & Launch | 1 minggu | Rilis publik |

## 5. Task Breakdown
- [ ] Riset kompetitor
- [ ] Definisikan user persona
- [ ] Buat wireframe
- [ ] Setup repository & CI/CD
- [ ] Implementasi fitur inti
- [ ] Testing & bug fixing

## 6. Risks & Mitigations
| Risiko | Dampak | Mitigasi |
|---|---|---|
| Scope creep | Tinggi | Prioritaskan dengan MoSCoW |
| Keterlambatan dev | Sedang | Sprint mingguan & buffer waktu |

## 7. Success Metrics
- 100 pengguna aktif di bulan pertama
- Retensi minggu ke-4 > 30%
MD,
        'design' => <<<MD
# Design Document: {$name}

> Dibuat oleh VCP pada {$date}

## 1. Design Principles
1. **Clarity** — setiap layar punya satu tujuan utama
2. **Consistency** — komponen dan pola interaksi seragam
3. **Delight** — micro-interaction yang halus

## 2. User Persona
| Persona | Kebutuhan | Pain Point |
|---|---|---|
| Founder | Validasi ide cepat | Waktu terbatas |
| Developer | Spesifikasi jelas | Requirement berubah-ubah |

## 3. User Flow
```
Landing → Register → Onboarding → Dashboard → Fitur Utama
```

## 4. Screen Inventory
- Landing Page
- Login / Register
- Dashboard
- Detail & Settings

## 5. Design Tokens
| Token | Value |
|---|---|
| Primary | `#6366F1` |
| Accent | `#F0ABFC` |
| Text | `#111111` |
| Font | Poppins |
| Radius | 16px |

## 6. Ide Proyek
{$idea}

## 7. Accessibility
- Kontras warna minimal WCAG AA
- Semua aksi dapat diakses via keyboard
MD,
        'prd' => <<<MD
# PRD: {$name}

> Dibuat oleh VCP pada {$date} · Status: **Draft**

## 1. Problem Statement
{$idea}

## 2. Objectives
- Menyelesaikan masalah utama pengguna dengan solusi yang sederhana
- Mencapai product-market fit untuk segmen awal

## 3. Target Users
- Pengguna utama: profesional yang membutuhkan solusi cepat
- Pengguna sekunder: tim kecil & startup

## 4. User Stories
- Sebagai **pengguna baru**, saya ingin mendaftar dengan cepat agar bisa langsung mencoba produk.
- Sebagai **pengguna**, saya ingin melihat ringkasan data di dashboard agar dapat mengambil keputusan.

## 5. Functional Requirements
| ID | Requirement | Priority |
|---|---|---|
| FR-01 | Registrasi & login | Must |
| FR-02 | Dashboard ringkasan | Must |
| FR-03 | Export data | Should |
| FR-04 | Notifikasi email | Could |

## 6. Non-Functional Requirements
- Waktu muat halaman < 2 detik
- Uptime 99.5%
- Data terenkripsi saat transit (HTTPS)

## 7. Acceptance Criteria
- [ ] Pengguna dapat mendaftar dan login
- [ ] Dashboard menampilkan data terbaru

## 8. Out of Scope
- Aplikasi mobile native

## 9. Open Questions
- Model monetisasi apa yang akan digunakan?
MD,
    ];

    $content = $templates[$document['type']];
    $reply = "Ini draft awal dokumen " . DOC_TYPES[$document['type']] . " untuk idemu. Saat ini VCP berjalan di mode demo — isi `AI_API_KEY` di config.php agar AI bisa menyusun dan merevisi dokumen secara penuh.";

    if ($document['type'] === 'design') {
        $content .= $embeds ?: "\n\n## Referensi Visual\n\n(menunggu referensi gambar — lampirkan 1-3 screenshot UI lewat ikon penjepit di bawah kolom chat, lalu aku petakan ke design token.)\n";
        $reply .= " Untuk dokumen Design, lampirkan 1-3 gambar referensi UI lewat ikon penjepit (atau tempel screenshot) supaya bagian visualnya bisa dianalisis lebih detail.";
    }

    return ['reply' => $reply, 'content' => $content];
}

/** Blok markdown berisi gambar referensi yang dikirim user (untuk mode demo). */
function ai_demo_embeds(array $images): string
{
    if (!$images) {
        return '';
    }
    $lines = array_map(static fn (string $url): string => "![Referensi UI](" . $url . ")", $images);

    return "\n\n## Referensi Visual\n\n" . implode("\n\n", $lines)
        . "\n\n> Mode demo: analisis palet & design language butuh AI — isi `AI_API_KEY` di config.php.\n";
}
