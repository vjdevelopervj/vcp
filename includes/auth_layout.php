<?php
/**
 * Layout halaman auth: nav transparan di atas, dua kolom — karakter hero di kiri
 * (menghadap form) dan form full-screen di kanan. Tanpa aurora: latarnya ink polos.
 *
 * @var string $pageTitle
 * @var string $formTitle   sapaan kecil di atas heading (mis. 'Halo!')
 * @var string $heading     headline di kolom form
 * @var string $subheading  subtext di bawah heading
 * @var callable $renderForm
 * @var string $authMode    'login' | 'register' — menentukan tautan aktif di bar nav
 * @var list<string> $errors
 * @var string $next        tujuan redirect setelah berhasil
 * @var string $footer      baris pindah halaman (HTML, boleh membawa tautan)
 * @var string $legal       catatan kecil di bawah form (teks polos)
 */
include __DIR__ . '/head.php';

$errors = $errors ?? [];
$authMode = $authMode ?? 'login';
$next = $next ?? '';

$switchLink = function (string $file, string $label, bool $active) use ($next): string {
    $cls = $active
        ? 'relative pb-1 text-[13px] font-medium text-paper after:absolute after:-bottom-1 after:left-0 after:right-0 after:h-[2px] after:bg-white'
        : 'rounded-full bg-paper px-4 py-2 text-[13px] font-semibold text-ink transition hover:bg-brand-300';
    return '<a href="' . e($file) . '?next=' . urlencode($next) . '" class="' . $cls . '">' . e($label) . '</a>';
};
?>
<body class="font-sans text-paper bg-ink antialiased">
<main class="relative min-h-screen overflow-hidden bg-ink">
    <div class="noise-layer" aria-hidden="true"></div>
    <!-- Bingkai tipis di tepi layar, mengikuti referensi; di bawah konten. -->
    <div class="pointer-events-none absolute inset-2 rounded-2xl border border-white/10 sm:inset-4 sm:rounded-[1.75rem]" aria-hidden="true"></div>

    <!-- z-index di wrapper ini dilarang: stacking context meng-isolasi group blend, dan
         kotak hitam PNG malah tercetak di atas latar. -->
    <div class="relative flex min-h-screen flex-col">
        <nav class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 pt-[max(1.25rem,env(safe-area-inset-top))] pb-4 sm:px-9 sm:py-5 lg:px-12">
            <div class="flex items-center gap-7">
                <?php include __DIR__ . '/logo.php'; ?>
                <ul class="hidden items-center gap-6 text-[13px] md:flex xl:gap-9">
                    <?php foreach (['Beranda' => 'index.php#home', 'Fitur' => 'index.php#features',
                                    'Template' => 'index.php#templates', 'Tentang' => 'index.php#about'] as $label => $href): ?>
                        <li><a href="<?= e($href) ?>" class="text-zinc-400 transition hover:text-paper"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="flex items-center gap-4 sm:gap-8">
                <?= $switchLink('login.php', 'Masuk', $authMode === 'login') ?>
                <?= $switchLink('register.php', 'Daftar', $authMode === 'register') ?>
            </div>
        </nav>

        <div class="grid flex-1 lg:grid-cols-2">
            <div class="relative hidden items-end justify-center overflow-hidden lg:flex">
                <img src="assets/img/auth-robot.png" alt="Robot AI vcp" width="1024" height="1536" loading="lazy"
                     class="auth-visual pointer-events-none absolute left-1/2 top-0 h-[126vh] max-h-[1116px] max-w-none -translate-x-1/2 -translate-y-[12%] w-auto object-contain object-bottom">
            </div>

            <section class="flex flex-col justify-center px-4 py-8 pb-[max(2rem,env(safe-area-inset-bottom))] sm:px-10 lg:px-14 xl:px-20">
                <div class="mx-auto w-full max-w-[400px]">
                    <h1 class="text-center font-display text-[28px] font-semibold leading-[1.18] tracking-tight sm:text-[34px]">
                        <span class="block text-brand-400"><?= e($formTitle) ?></span>
                        <span class="block text-paper"><?= e($heading) ?></span>
                    </h1>
                    <p class="mt-3 text-center text-sm text-zinc-400"><?= e($subheading) ?></p>
                    <?php if ($errors): ?>
                        <div class="mt-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                            <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php $renderForm(); ?>
                    <?php if (!empty($footer ?? '')): ?>
                        <p class="mt-5 text-center text-sm text-zinc-400"><?= $footer ?></p>
                    <?php endif; ?>
                    <?php if (!empty($legal ?? '')): ?>
                        <p class="mt-3 text-center text-xs leading-relaxed text-zinc-500"><?= e($legal) ?></p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>
<style>
    /* PNG karakter berlatar hitam murni: screen membuat hitamnya melebur ke bg ink,
       jadi tidak perlu mask lagi sekarang figurnya sudah ter-contain di kolom sendiri. */
    .auth-visual {
        mix-blend-mode: screen;
    }
</style>
<script>
    (() => {
        document.querySelectorAll('[data-eye]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.eye);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.querySelector('[data-eye-open]').classList.toggle('hidden', show);
                btn.querySelector('[data-eye-closed]').classList.toggle('hidden', !show);
            });
        });
    })();
</script>
</body>
</html>
