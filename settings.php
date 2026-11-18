<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/images.php';

$user = require_login();
$uid = (int) $user['id'];

const AVATAR_MAX_BYTES = 4 * 1024 * 1024;
const AVATAR_MIMES = ['image/png' => true, 'image/jpeg' => true, 'image/webp' => true, 'image/gif' => true];

const SETTINGS_OK = [
    'profile' => 'Profil tersimpan.',
    'avatar' => 'Foto profil diperbarui.',
    'avatar_removed' => 'Foto profil dihapus.',
    'password' => 'Password berhasil diganti.',
];

$errors = [];
$saved = isset($_GET['ok']) && is_string($_GET['ok']) && isset(SETTINGS_OK[$_GET['ok']]) ? SETTINGS_OK[$_GET['ok']] : null;
$form = ['name' => $user['name'], 'email' => $user['email']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Sesi form kedaluwarsa, silakan coba lagi.';
        $action = '';
    }
    if ($action !== '') {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $hash = (string) $stmt->fetchColumn();
        $needPassword = static function (string $field) use ($hash): bool {
            return !password_verify((string) ($_POST[$field] ?? ''), $hash);
        };
    }

    if ($action === 'profile') {
        $form['name'] = trim((string) ($_POST['name'] ?? ''));
        $form['email'] = trim((string) ($_POST['email'] ?? ''));
        if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
            $errors[] = 'Nama wajib diisi (maks 100 karakter).';
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190) {
            $errors[] = 'Format email tidak valid.';
        }
        // Ganti email = ganti identitas login, jadi minta password + pastikan tidak dipakai orang lain.
        if ($form['email'] !== $user['email']) {
            if ($needPassword('current_password')) {
                $errors[] = 'Password saat ini salah, email tidak jadi diubah.';
            } else {
                $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
                $stmt->execute([$form['email'], $uid]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email itu sudah dipakai akun lain.';
                }
            }
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                ->execute([$form['name'], $form['email'], $uid]);
            redirect('settings.php?ok=profile');
        }
    }

    if ($action === 'avatar') {
        $file = $_FILES['avatar'] ?? null;
        $code = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($code !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload foto gagal — pilih file PNG/JPEG/WebP/GIF maksimal 4 MB.';
        } elseif ((int) $file['size'] > AVATAR_MAX_BYTES) {
            $errors[] = 'Foto maksimal 4 MB.';
        } elseif (!isset(AVATAR_MIMES[(string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])])) {
            $errors[] = 'File itu bukan gambar yang dikenali (PNG/JPEG/WebP/GIF).';
        } else {
            $rel = image_save_avatar($file['tmp_name'], $uid);
            if ($rel === null) {
                $errors[] = 'Gambar tidak bisa diproses, coba file lain.';
            } else {
                db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$rel, $uid]);
                image_delete_upload($user['avatar'] ?? null);
                redirect('settings.php?ok=avatar');
            }
        }
    }

    if ($action === 'avatar_remove') {
        db()->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$uid]);
        image_delete_upload($user['avatar'] ?? null);
        redirect('settings.php?ok=avatar_removed');
    }

    if ($action === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if ($needPassword('current_password')) {
            $errors[] = 'Password saat ini salah.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        }
        if ($new !== $confirm) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            redirect('settings.php?ok=password');
        }
    }

    if ($action === 'delete') {
        if ($needPassword('current_password')) {
            $errors[] = 'Password salah — akun tidak jadi dihapus.';
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            purge_user_uploads($uid);
            $_SESSION = [];
            session_destroy();
            redirect('index.php');
        }
    }
}

$providers = [];
$stmt = db()->prepare('SELECT provider FROM oauth_identities WHERE user_id = ? ORDER BY provider');
$stmt->execute([$uid]);
$providers = array_column($stmt->fetchAll(), 'provider');

function purge_user_uploads(int $uid): void
{
    $dir = __DIR__ . '/uploads/' . $uid;
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

$pageTitle = 'Pengaturan akun — vcp';
include __DIR__ . '/includes/head.php';
?>
<body class="min-h-screen bg-ink font-sans text-paper antialiased">
<?php
$card = 'rounded-3xl border border-white/10 bg-panel p-5 sm:p-8 shadow-soft';
$input = 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-base sm:text-sm text-paper placeholder:text-zinc-500 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-400/20';
$label = 'mb-2 block text-sm font-medium';
$btn = 'rounded-full bg-gradient-to-r from-brand-300 to-brand-500 px-5 py-2.5 text-sm font-semibold text-ink transition hover:from-brand-400 hover:to-brand-600 disabled:opacity-50';
$ghost = 'rounded-full border border-white/15 px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-zinc-300 transition hover:border-white/40 hover:text-paper';
$initial = mb_strtoupper(mb_substr($user['name'], 0, 1));
?>
<div class="blob -left-24 top-0 h-72 w-72 bg-brand-500/20"></div>
<div class="blob right-0 top-40 h-72 w-72 bg-brand-300/10"></div>

<header class="relative z-10 border-b border-white/10">
    <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 pt-[max(1.25rem,env(safe-area-inset-top))] pb-4 sm:px-6 sm:py-5">
        <div class="flex items-center gap-4">
            <?php include __DIR__ . '/includes/logo.php'; ?>
            <span class="hidden text-sm text-zinc-500 sm:inline">Pengaturan akun</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="app.php" class="<?= $ghost ?>"><span class="hidden sm:inline">Kembali ke workspace</span><span class="sm:hidden">Kembali</span></a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="rounded-full px-3 py-2 text-sm text-zinc-400 underline-offset-4 transition hover:bg-white/5 hover:text-paper hover:underline">Keluar</button>
            </form>
        </div>
    </div>
</header>

<main class="relative z-10 mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 sm:py-10 pb-[max(2.5rem,env(safe-area-inset-bottom))]">
    <?php if ($saved): ?>
        <p class="rounded-2xl border border-brand-400/40 bg-brand-400/10 px-5 py-3 text-sm text-brand-300"><?= e($saved) ?></p>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="rounded-2xl border border-red-500/40 bg-red-500/10 px-5 py-3 text-sm">
            <?php foreach ($errors as $err): ?>
                <p class="text-red-300"><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="<?= $card ?>">
        <h2 class="text-lg font-semibold">Foto profil</h2>
        <p class="mt-1 text-sm text-zinc-400">PNG, JPEG, WebP atau GIF maksimal 4 MB. Dipotong persegi dan dikecilkan otomatis.</p>
        <div class="mt-5 flex flex-wrap items-center gap-5">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-300 to-brand-500 text-2xl font-bold text-ink">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= e($user['avatar']) ?>" alt="Foto profil" class="h-full w-full object-cover">
                <?php else: ?>
                    <?= e($initial) ?>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="avatar">
                <input type="file" id="avatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" required
                       class="max-w-[260px] flex-1 text-xs text-zinc-400 file:mr-3 file:rounded-full file:border-0 file:bg-white/10 file:px-4 file:py-2 file:text-xs file:font-medium file:text-paper hover:file:bg-white/20">
                <button class="<?= $btn ?>">Unggah foto</button>
            </form>
            <?php if (!empty($user['avatar'])): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="avatar_remove">
                    <button class="<?= $ghost ?>">Hapus foto</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="<?= $card ?>">
        <h2 class="text-lg font-semibold">Akun</h2>
        <p class="mt-1 text-sm text-zinc-400">Nama tampil di sidebar. Email dipakai untuk login, jadi pengubahannya dikonfirmasi password.</p>
        <form method="post" class="mt-5 grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile">
            <div>
                <label for="name" class="<?= $label ?>">Nama</label>
                <input id="name" name="name" required maxlength="100" value="<?= e($form['name']) ?>" class="<?= $input ?>">
            </div>
            <div>
                <label for="email" class="<?= $label ?>">Email</label>
                <input id="email" name="email" type="email" required maxlength="190" value="<?= e($form['email']) ?>" class="<?= $input ?>">
            </div>
            <div class="sm:col-span-2">
                <label for="current_password" class="<?= $label ?>">Password saat ini <span class="font-normal text-zinc-500">(hanya wajib kalau ganti email)</span></label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Isi kalau mau ganti email" class="<?= $input ?>">
            </div>
            <div class="sm:col-span-2">
                <button class="<?= $btn ?>">Simpan profil</button>
            </div>
        </form>
        <?php if ($providers): ?>
            <p class="mt-5 text-xs text-zinc-500">
                Login cepat aktif lewat: <span class="text-zinc-300"><?= e(implode(', ', array_map(static fn (string $p): string => ucfirst($p), $providers))) ?></span>.
                Password akun ini tetap bisa diganti di bawah.
            </p>
        <?php endif; ?>
    </section>

    <section class="<?= $card ?>">
        <h2 class="text-lg font-semibold">Password</h2>
        <p class="mt-1 text-sm text-zinc-400">Minimal 8 karakter. Sesi yang sudah terbuka tetap aktif sampai keluar sendiri.</p>
        <form method="post" class="mt-5 grid gap-4 grid-cols-1 md:grid-cols-3">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">
            <div>
                <label for="current_password2" class="<?= $label ?>">Password saat ini</label>
                <input id="current_password2" name="current_password" type="password" required autocomplete="current-password" class="<?= $input ?>">
            </div>
            <div>
                <label for="password" class="<?= $label ?>">Password baru</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
            </div>
            <div>
                <label for="password_confirm" class="<?= $label ?>">Ulangi password baru</label>
                <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
            </div>
            <div class="md:col-span-3">
                <button class="<?= $btn ?>">Ganti password</button>
            </div>
        </form>
    </section>

    <section class="rounded-3xl border border-red-500/30 bg-red-500/5 p-5 sm:p-8">
        <h2 class="text-lg font-semibold text-red-300">Bahaya</h2>
        <p class="mt-1 text-sm text-zinc-400">
            Menghapus akun berarti <?= e((string) (db()->query('SELECT COUNT(*) FROM documents WHERE user_id = ' . $uid)->fetchColumn() ?: 0)) ?> dokumen
            beserta seluruh riwayat chat dan file lampiran ikut terhapus permanen. Tidak ada cara untuk mengembalikannya.
        </p>
        <form method="post" id="deleteAccountForm" class="mt-5 flex flex-col sm:flex-row sm:items-end gap-3">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <div class="w-full sm:max-w-xs">
                <label for="delete_password" class="<?= $label ?>">Konfirmasi dengan password</label>
                <input id="delete_password" name="current_password" type="password" required autocomplete="current-password" placeholder="Password akunmu" class="<?= $input ?>">
            </div>
            <button class="rounded-full bg-red-500 px-5 py-2.5 text-sm font-semibold text-paper transition hover:bg-red-400 self-start sm:self-auto">Hapus akun</button>
        </form>
    </section>
</main>
<script src="assets/js/confirm.js?v=<?= filemtime(__DIR__ . '/assets/js/confirm.js') ?>"></script>
<script>
    (() => {
        // preventDefault harus sinkron — kalau menunggu await modal dulu, form sudah sempat terkirim.
        const f = document.getElementById('deleteAccountForm');
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (await window.vcpConfirm({
                title: 'Hapus akun?',
                message: 'Semua dokumen, riwayat chat, dan file lampiran akan terhapus permanen. Tindakan ini tidak bisa dibatalkan.',
                confirmText: 'Hapus akun',
            })) f.submit();
        });
    })();
</script>
</body>
</html>
