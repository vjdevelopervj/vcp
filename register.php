<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
if (current_user()) {
    redirect($next);
}

$errors = [];
if (isset($_GET['oauth_error']) && $_GET['oauth_error'] !== '') {
    $errors[] = (string) $_GET['oauth_error'];
}
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Sesi form kedaluwarsa, silakan coba lagi.';
    }
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Nama wajib diisi (maks 100 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email sudah terdaftar.';
        } else {
            db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            login_user((int) db()->lastInsertId());
            redirect($next);
        }
    }
}

$pageTitle = 'Daftar — vcp';
$formTitle = 'Halo!';
$heading = 'Buat akunmu';
$subheading = 'Satu akun untuk semua dokumen Planning, Design, dan PRD.';
$authMode = 'register';
$renderForm = function () use ($name, $email, $next) {
    $input = 'w-full rounded-xl border border-white/15 bg-paper py-3.5 pl-4 pr-11 text-base sm:text-sm font-medium text-ink placeholder:text-ink/55 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-400/25';
    $icon = static fn (string $path): string => '<span class="pointer-events-none absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border border-ink/15 text-ink/45" aria-hidden="true">'
        . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span>';
    $eye = static fn (string $id): string => '<button type="button" data-eye="' . $id . '" aria-label="Tampilkan atau sembunyikan password" class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border border-ink/15 text-ink/45 transition hover:border-ink/30 hover:text-ink">'
        . '<svg data-eye-open class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
        . '<svg data-eye-closed class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.7 5.1C11.1 5 11.6 5 12 5c7 0 10 7 10 7a13.4 13.4 0 0 1-2.2 3.2M6.6 6.6A13.6 13.6 0 0 0 2 12s3 7 10 7c1.9 0 3.5-.5 4.9-1.2M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>'
        . '</button>'; ?>
    <form method="post" class="mt-7 space-y-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="relative">
            <input id="name" name="name" required autofocus maxlength="100" value="<?= e($name) ?>"
                   placeholder="Nama lengkap" aria-label="Nama" class="<?= $input ?>">
            <?= $icon('<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>') ?>
        </div>
        <div class="relative">
            <input id="email" name="email" type="email" required maxlength="190" value="<?= e($email) ?>"
                   placeholder="Masukkan email" aria-label="Email" class="<?= $input ?>">
            <?= $icon('<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 8 8 5 8-5"/>') ?>
        </div>
        <div class="relative">
            <input id="password" name="password" type="password" required minlength="8"
                   placeholder="Password (min. 8 karakter)" aria-label="Password" class="<?= $input ?>">
            <?= $eye('password') ?>
        </div>
        <div class="relative">
            <input id="password_confirm" name="password_confirm" type="password" required minlength="8"
                   placeholder="Ulangi password" aria-label="Konfirmasi password" class="<?= $input ?>">
            <?= $eye('password_confirm') ?>
        </div>
        <p class="text-xs leading-relaxed text-zinc-500">Campur huruf besar, huruf kecil, angka, dan simbol supaya passwordnya kuat.</p>
        <button class="!mt-3 w-full rounded-xl bg-gradient-to-r from-brand-300 to-brand-500 py-3.5 text-sm font-semibold text-ink transition hover:from-brand-400 hover:to-brand-600">Buat akun</button>
    </form>
        <?php include __DIR__ . '/includes/oauth_buttons.php'; ?>
<?php };
$footer = 'Sudah punya akun? <a href="login.php?next=' . urlencode($next) . '" class="font-semibold text-paper underline-offset-4 hover:underline hover:text-brand-300">Masuk</a>';
$legal = 'Dengan mendaftar, kamu setuju bahwa data akunmu disimpan di aplikasi ini.';

include __DIR__ . '/includes/auth_layout.php';
