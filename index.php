<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$pageTitle = 'vcp — Unleash Product Docs with AI Magic';
include __DIR__ . '/includes/head.php';
?>
<body class="font-sans text-paper bg-ink antialiased">

<!-- HERO -->
<header id="home" class="relative overflow-hidden bg-ink">
    <!-- Grain ditaruh sebagai layer sendiri (bukan .noise di header) supaya bisa ikut
         paint-order di bawah konten; ::after header jatuh ke atas teks dan memudarkannya. -->
    <div class="noise-layer" aria-hidden="true"></div>

    <nav class="relative z-30 mx-auto flex max-w-[1360px] items-center justify-between px-5 pt-[max(1.25rem,env(safe-area-inset-top))] pb-5 sm:py-6 sm:px-8">
        <?php include __DIR__ . '/includes/logo.php'; ?>
        <ul class="hidden items-center gap-10 text-[13px] text-zinc-400 md:flex">
            <li><a href="#home" class="relative pb-2 font-medium text-paper after:absolute after:left-[-4px] after:right-[-4px] after:-bottom-0.5 after:h-[2px] after:bg-paper">Home</a></li>
            <li><a href="#features" class="transition hover:text-paper">Features</a></li>
            <li><a href="#templates" class="transition hover:text-paper">Templates</a></li>
            <li><a href="#about" class="transition hover:text-paper">About</a></li>
        </ul>
        <div class="flex items-center gap-3 sm:gap-6 md:gap-8">
            <?php if ($user): ?>
                <a href="app.php" class="rounded-full bg-paper px-5 py-2 text-[13px] font-semibold text-ink transition hover:bg-brand-300">Open App</a>
            <?php else: ?>
                <a href="login.php" class="hidden text-[13px] font-medium text-zinc-400 transition hover:text-paper sm:inline-block">Sign in</a>
                <a href="register.php" class="rounded-full bg-paper px-5 py-2 text-[13px] font-semibold text-ink transition hover:bg-brand-300">Try Now</a>
            <?php endif; ?>
            <button id="navToggle" type="button" class="ml-1 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-white/5 text-paper transition hover:border-brand-400 md:hidden" aria-controls="mobileNav" aria-expanded="false" aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </nav>

    <!-- Menu mobile: link nav yang di md ke atas ada di bar, di bawah sini disembunyikan. -->
    <div id="mobileNav" class="relative z-30 hidden px-5 pb-6 sm:px-8 md:hidden">
        <div class="rounded-2xl border border-white/10 bg-panel/90 p-2 shadow-soft backdrop-blur-xl">
            <?php foreach ([['#features', 'Features'], ['#templates', 'Templates'], ['#about', 'About']] as [$href, $label]): ?>
                <a href="<?= $href ?>" class="block rounded-xl px-4 py-3 text-[15px] text-zinc-300 transition hover:bg-white/5 hover:text-paper"><?= $label ?></a>
            <?php endforeach; ?>
            <?php if (!$user): ?>
                <a href="login.php" class="block rounded-xl px-4 py-3 text-[15px] text-zinc-300 transition hover:bg-white/5 hover:text-paper sm:hidden">Sign in</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tanpa z-index di sini: stacking context akan meng-isolasi group, dan mix-blend screen
         pada figur hero cumablend terhadap wrapper kosong -> kotak hitamnya jadi kelihatan. -->
    <div class="relative mx-auto max-w-[1360px] px-5 pb-4 pt-2 sm:px-8">
        <!-- Judul raksasa: baris putih + baris "ghost". Figur robot ditaruh di belakang tipografi
             (z-0) — di atas teks, blend screen-nya malah menelan huruf. -->
        <div class="relative">
            <img src="assets/img/hero-robot.png" alt="Figur robot AI vcp" width="1024" height="1536" decoding="async"
                 class="hero-figure pointer-events-none absolute left-1/2 top-0 z-0 w-[min(78vw,320px)] -translate-x-1/2 opacity-45 sm:w-[min(60vw,380px)] sm:opacity-60 md:w-[400px] md:opacity-70 lg:w-[480px] lg:opacity-90">
            <h1 data-reveal class="display-hero relative z-10 text-center">
                <span class="block text-paper">— the future —</span>
                <span class="block text-ghost">of product docs</span>
            </h1>
        </div>

        <!-- Band bawah judul: copy + chips + form di kiri, kartu maskot + stat di kanan.
             Baru dua kolom di lg — di 768an kolom sempit bikin teks menabrak badan robot. -->
        <div class="relative z-30 grid gap-10 pt-10 lg:grid-cols-2 lg:gap-6 lg:pt-16">
            <div data-reveal style="--reveal-delay:80ms">
                <p class="text-[10px] uppercase tracking-[0.42em] text-brand-400">Asisten produk</p>
                <p class="mt-5 max-w-[36ch] text-[15px] leading-relaxed text-zinc-400">
                    AI yang bekerja secepat pikirmu. Ceritakan idemu, buat Planning, Design, atau PRD —
                    revisi lewat chat, langsung terlihat di preview.
                </p>

                <div id="heroChips" class="mt-8 flex flex-wrap gap-2">
                    <?php foreach (DOC_TYPES as $key => $label): ?>
                        <button type="button" data-type="<?= $key ?>"
                                class="chip rounded-full border border-white/10 bg-white/5 px-5 py-2 text-xs font-medium text-zinc-300 backdrop-blur transition hover:bg-white/10 hover:text-paper">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                    <button type="button" data-example="Aplikasi to-do list kolaboratif dengan AI yang memprioritaskan tugas"
                            class="rounded-full border border-white/10 bg-white/5 px-5 py-2 text-xs font-medium text-zinc-300 backdrop-blur transition hover:bg-white/10 hover:text-paper">
                        Try example
                    </button>
                </div>

                <form data-reveal style="--reveal-delay:160ms" action="app.php" method="get" class="mt-6" id="heroForm">
                    <input type="hidden" name="type" id="heroType" value="prd">
                    <div class="flex flex-col gap-2 border border-white/10 bg-white/[0.05] p-2 backdrop-blur-xl sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-3 px-4">
                            <svg class="h-4 w-4 shrink-0 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input name="prompt" required maxlength="2000" placeholder="Describe your app idea, e.g. marketplace for local coffee beans"
                                   class="w-full min-w-0 bg-transparent py-3 text-base sm:text-[15px] text-paper placeholder:text-zinc-400 focus:outline-none">
                        </div>
                        <button class="shrink-0 bg-gradient-to-r from-brand-300 to-brand-500 px-7 py-3.5 text-[13px] font-semibold uppercase tracking-[0.12em] text-ink transition hover:from-brand-400 hover:to-brand-600">Generate</button>
                    </div>
                </form>

                <a href="#features" class="group mt-6 inline-flex items-center gap-3 text-[13px] uppercase tracking-[0.22em] text-zinc-400 transition hover:text-paper">
                    <span class="inline-flex h-9 w-9 items-center justify-center border border-white/15 bg-white/5 transition group-hover:border-brand-400 group-hover:text-brand-300">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M7 4.5v15l13-7.5z"/></svg>
                    </span>
                    Play
                </a>
            </div>

            <div data-reveal style="--reveal-delay:220ms" class="flex flex-col items-start gap-5 lg:items-end">
                <div class="h-24 w-24 overflow-hidden rounded-2xl border border-white/10 bg-ink shadow-soft">
                    <img src="assets/img/bot-hero-head.webp" alt="Kepala robot AI vcp" width="512" height="512" class="h-full w-full object-cover">
                </div>
                <div class="grid w-full max-w-[360px] grid-cols-2 gap-px border border-white/10 bg-white/10">
                    <div class="bg-ink/80 px-5 py-6 backdrop-blur">
                        <p class="font-display text-3xl font-medium text-paper">3</p>
                        <p class="mt-3 text-[11px] leading-snug text-zinc-400">Tipe<br><span class="text-brand-400">dokumen</span></p>
                    </div>
                    <div class="bg-ink/80 px-5 py-6 backdrop-blur">
                        <p class="font-display text-3xl font-medium text-paper">.md</p>
                        <p class="mt-3 text-[11px] leading-snug text-zinc-400">Export<br><span class="text-brand-400">siap pakai</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Integration strip -->
    <div class="relative z-10 mt-14 border-t border-white/10">
        <div class="mx-auto max-w-[1360px] px-5 py-12 sm:px-8">
            <p class="text-center text-[10px] uppercase tracking-[0.45em] text-zinc-400">Our partners</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-x-12 gap-y-5 font-display text-[15px] text-zinc-400">
                <span class="flex items-center gap-2"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18v14H3z" opacity=".25"/><path d="M5 15V9l2.5 3L10 9v6M14 9v6m-2-2 2 2 2-2" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>Markdown</span>
                <span class="flex items-center gap-2"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.3-3.4-1.3-.4-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .9.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.7 1a9.4 9.4 0 0 1 5 0c1.9-1.3 2.7-1 2.7-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.4 4.7-4.6 5 .4.3.7.9.7 1.9V21c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/></svg>GitHub</span>
                <span class="flex items-center gap-2"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>Notion</span>
                <span class="flex items-center gap-2"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="15" cy="12" r="3"/><path d="M9 3h3v6H9a3 3 0 0 1 0-6zm0 6h3v6H9a3 3 0 0 1 0-6zm0 6h3v3a3 3 0 1 1-3-3zm3-12h3a3 3 0 0 1 0 6h-3z"/></svg>Figma</span>
                <span class="flex items-center gap-2"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h10M4 17h13"/></svg>Linear</span>
            </div>
        </div>
    </div>
</header>

<!-- STATEMENT -->
<section class="mx-auto max-w-[1080px] px-6 py-28 text-center">
    <h2 data-reveal class="display-statement uppercase">
        <span class="text-paper">Kami bikin dokumen produk lebih cepat, lebih rapi, dan terakses untuk</span>
        <span class="text-ghost">semua — dari pemula yang baru mulai sampai power user yang ship tiap minggu.</span>
    </h2>
</section>

<!-- FEATURES -->
<section id="features" class="mx-auto max-w-[1360px] px-5 pb-28 sm:px-8">
    <div class="grid items-center gap-12 lg:grid-cols-[1.05fr_1fr] lg:gap-16">
        <div data-reveal>
            <h2 class="display-2 uppercase">
                <span class="text-paper">AI × manusia:</span>
                <span class="text-ghost">satu alur, satu hasil.</span>
            </h2>
            <p class="mt-7 max-w-[46ch] text-[15px] leading-relaxed text-zinc-400">
                AI menyusun strukturnya, kamu yang memutuskan isinya. Setiap dokumen bisa direvisi lewat chat,
                diedit manual di editor markdown, lalu dikirim ke tim dalam format yang mereka pakai.
            </p>
            <a href="<?= $user ? 'app.php' : 'register.php' ?>" class="mt-9 inline-block bg-paper px-7 py-3.5 text-[11px] font-semibold uppercase tracking-[0.22em] text-ink transition hover:bg-brand-300">Explore demo</a>
        </div>
        <div data-reveal style="--reveal-delay:120ms" class="relative">
            <div class="overflow-hidden rounded-2xl border border-white/10 shadow-soft [transform:perspective(1400px)_rotateY(-11deg)_rotateX(4deg)]">
                <img src="assets/img/markdown-document-3d.svg" alt="Dokumen Markdown 3D berlapis dengan heading, checklist, dan blok kode" class="h-full w-full object-cover" loading="lazy">
            </div>
        </div>
    </div>

    <div class="mt-20 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php
        $features = [
            ['planning', 'Planning', 'markdown-document-3d.svg', 'Milestone, timeline, task breakdown, dan risiko proyek dalam satu dokumen.'],
            ['design', 'Design', 'markdown-document-3d.svg', 'User flow, persona, design tokens, dan arsitektur yang siap diserahkan ke tim.'],
            ['prd', 'PRD', 'markdown-document-3d.svg', 'User stories, requirement, dan acceptance criteria yang terukur.'],
        ];
        foreach ($features as $i => [$type, $label, $img, $desc]): ?>
            <a data-reveal style="--reveal-delay:<?= $i * 120 ?>ms" href="app.php?new=<?= $type ?>" class="group border border-white/10 bg-panel p-2 transition hover:-translate-y-1 hover:border-brand-400/40 hover:shadow-soft">
                <div class="relative h-52 overflow-hidden bg-[#13131C]">
                    <img src="assets/img/<?= $img ?>" alt="" class="absolute inset-0 h-full w-full object-cover object-right-top transition duration-500 group-hover:scale-105" loading="lazy">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-ink/90 to-transparent"></div>
                    <div class="absolute inset-x-5 bottom-4 flex items-center justify-between">
                        <h3 class="font-display text-[26px] font-medium text-paper"><?= $label ?></h3>
                        <svg class="h-6 w-6 text-paper transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></svg>
                    </div>
                </div>
                <p class="px-3 pb-3 pt-5 text-sm leading-relaxed text-zinc-400"><?= $desc ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- TEMPLATES -->
<section id="templates" class="overflow-hidden pb-28">
    <div data-reveal class="mx-auto max-w-[1080px] px-6 text-center">
        <h2 class="display-2 uppercase">
            <span class="text-paper">Template siap pakai,</span>
            <span class="text-ghost">tinggal ganti isinya.</span>
        </h2>
        <p class="mt-6 text-[15px] leading-relaxed text-zinc-400">Mulai dari roadmap startup sampai spesifikasi fitur yang detail.</p>
    </div>
    <div data-reveal class="template-scroll mt-14 overflow-x-auto px-5 pb-6 pt-3 sm:px-8" tabindex="0" role="region" aria-label="Contoh template dokumen, geser untuk melihat semua kartu">
        <div class="template-gallery">
            <article class="template-card template-card--small bg-brand-400 p-5 text-ink">
                <p class="text-[10px] font-medium uppercase tracking-[0.18em] text-ink/80">planning.md</p>
                <h4 class="mt-3 font-display text-2xl font-semibold leading-tight">Startup<br>Roadmap</h4>
                <div class="mt-5 rounded-xl bg-ink p-3 text-paper">
                    <div class="flex items-center justify-between text-[10px]"><span>MVP scope</span><span class="text-brand-400">Q1</span></div>
                    <div class="mt-3 h-1.5 rounded-full bg-white/15"><div class="h-full w-3/4 rounded-full bg-brand-400"></div></div>
                    <p class="mt-2 text-[9px] text-zinc-400">Discovery → Beta launch</p>
                </div>
            </article>

            <article class="template-card template-card--medium border border-white/10 bg-panel p-5 text-paper">
                <p class="text-[10px] uppercase tracking-[0.18em] text-zinc-400">design.md</p>
                <h4 class="mt-3 font-display text-2xl font-semibold leading-tight">Design<br>System</h4>
                <div class="mx-auto mt-5 flex h-24 w-24 items-center justify-center rounded-full border-[16px] border-brand-400 border-r-paper border-b-emerald-500" aria-hidden="true">
                    <span class="h-9 w-9 rounded-full border border-white/15 bg-ink"></span>
                </div>
                <div class="mt-5 flex justify-center gap-2" aria-hidden="true"><span class="h-5 w-5 rounded-full bg-brand-400"></span><span class="h-5 w-5 rounded-full bg-paper"></span><span class="h-5 w-5 rounded-full bg-emerald-500"></span></div>
                <p class="mt-4 text-center text-[11px] text-zinc-400">Aa — Poppins<br>Tokens · Components</p>
            </article>

            <article class="template-card template-card--hero bg-gradient-to-br from-brand-300 to-brand-500 p-6 text-ink">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-medium uppercase tracking-[0.18em] text-ink/80">prd.md</p>
                    <span class="rounded-full bg-ink px-2.5 py-1 text-[9px] font-medium text-brand-400">PRD</span>
                </div>
                <h4 class="mt-5 font-display text-5xl font-semibold leading-none tracking-tight">NEXT<br>APP</h4>
                <p class="mt-4 text-xs leading-relaxed text-ink/80">Dari ide aplikasi ke spesifikasi yang siap dibangun.</p>
                <div class="mt-7 -rotate-3 rounded-2xl bg-ink p-4 text-paper shadow-soft">
                    <div class="flex items-center gap-1.5" aria-hidden="true"><span class="h-1.5 w-1.5 rounded-full bg-brand-400"></span><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span><span class="h-1.5 w-1.5 rounded-full bg-white/30"></span></div>
                    <table class="mt-4 w-full text-left text-[10px]">
                        <caption class="sr-only">Contoh kebutuhan aplikasi</caption>
                        <thead><tr class="border-b border-white/15 text-zinc-400"><th class="pb-2 font-normal" scope="col">ID</th><th class="font-normal" scope="col">Requirement</th><th class="font-normal" scope="col">P</th></tr></thead>
                        <tbody>
                            <tr class="border-b border-white/10"><td class="py-2">FR-01</td><td>Auth</td><td class="text-brand-400">M</td></tr>
                            <tr class="border-b border-white/10"><td class="py-2">FR-02</td><td>Dashboard</td><td class="text-brand-400">M</td></tr>
                            <tr><td class="pt-2">FR-03</td><td>Export</td><td class="text-brand-400">S</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="template-card template-card--medium border border-white/10 bg-panel p-5 text-paper">
                <p class="text-[10px] uppercase tracking-[0.14em] text-zinc-400">feature-spec.md</p>
                <h4 class="mt-3 font-display text-2xl font-semibold leading-tight">New<br>Feature</h4>
                <div class="mt-5 rotate-3 rounded-xl border border-white/10 bg-ink p-3">
                    <span class="inline-flex rounded-lg bg-brand-400 px-3 py-1 font-display text-xl font-semibold text-ink" aria-hidden="true">Aa</span>
                    <p class="mt-4 text-xs font-medium">User story</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-zinc-400">Bagikan dokumen agar tim tetap selaras.</p>
                    <div class="mt-3 h-1 w-2/3 rounded-full bg-brand-400/70" aria-hidden="true"></div>
                </div>
            </article>

            <article class="template-card template-card--small bg-brand-400 p-5 text-ink">
                <p class="text-[10px] font-medium uppercase tracking-[0.18em] text-ink/80">design.md</p>
                <h4 class="mt-3 font-display text-2xl font-semibold leading-tight">Dashboard<br>UI</h4>
                <div class="mt-5 rounded-xl bg-ink p-3" aria-hidden="true">
                    <div class="flex items-center justify-between"><span class="h-1 w-9 rounded-full bg-white/30"></span><span class="h-2 w-2 rounded-full bg-brand-400"></span></div>
                    <div class="mt-3 flex h-9 items-end gap-1.5"><span class="h-1/3 flex-1 rounded-t bg-brand-400/40"></span><span class="h-2/3 flex-1 rounded-t bg-brand-400/60"></span><span class="h-1/2 flex-1 rounded-t bg-brand-400/80"></span><span class="h-full flex-1 rounded-t bg-brand-400"></span></div>
                </div>
            </article>
        </div>
    </div>
    <p class="mt-2 text-center text-xs text-zinc-400 lg:hidden">Geser untuk melihat semua template</p>
</section>

<!-- ABOUT / HOW IT WORKS -->
<section id="about" class="py-28">
    <div class="mx-auto max-w-[1360px] px-5 sm:px-8">
        <div data-reveal class="mx-auto max-w-[1080px] text-center">
            <h2 class="display-2 uppercase">
                <span class="text-paper">Dari ide jadi dokumen,</span>
                <span class="text-ghost">tiga langkah.</span>
            </h2>
            <p class="mt-6 text-[15px] text-zinc-400">vcp menggabungkan chat AI dan live preview markdown dalam satu workspace.</p>
        </div>
        <div class="mt-16 grid gap-px border border-white/10 bg-white/10 md:grid-cols-3">
            <?php foreach ([
                ['01', 'Pilih jenis dokumen', 'Planning, Design, atau PRD — masing-masing punya struktur yang dioptimalkan.'],
                ['02', 'Ngobrol dengan AI', 'Ceritakan idemu, lalu minta revisi kapan saja lewat chat.'],
                ['03', 'Preview & export', 'Lihat hasilnya langsung, edit manual, salin, atau unduh sebagai file .md.'],
            ] as $i => [$num, $title, $desc]): ?>
                <div data-reveal style="--reveal-delay:<?= $i * 120 ?>ms" class="bg-ink px-8 py-12">
                    <span class="font-display text-5xl font-medium text-zinc-400"><?= $num ?></span>
                    <h3 class="mt-6 font-display text-xl font-medium text-paper"><?= $title ?></h3>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-400"><?= $desc ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div data-reveal class="relative mt-24 overflow-hidden border border-white/10 bg-panel/40 py-20 text-center text-paper">
            <div class="blob h-72 w-72 bg-brand-500/25 -left-10 -top-10"></div>
            <div class="blob h-72 w-72 bg-emerald-400/15 -right-10 -bottom-20"></div>
            <div class="relative px-6">
                <h2 class="display-statement uppercase">
                    <span class="text-paper">Siap ship ide berikutnya?</span>
                    <span class="text-ghost">Gratis untuk mulai.</span>
                </h2>
                <a href="<?= $user ? 'app.php' : 'register.php' ?>" class="mt-10 inline-block bg-paper px-8 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-ink transition hover:bg-brand-300">Start for Free</a>
            </div>
        </div>
    </div>
</section>

<footer data-reveal class="border-t border-white/10 py-10">
    <div class="mx-auto flex max-w-[1360px] flex-col items-center justify-between gap-4 px-5 text-sm text-zinc-400 sm:px-8 md:flex-row">
        <?php include __DIR__ . '/includes/logo.php'; ?>
        <p>&copy; <?= date('Y') ?> vcp. AI-powered Planning, Design &amp; PRD generator.</p>
    </div>
</footer>

<noscript><style>[data-reveal] { opacity: 1; }</style></noscript>
<style>
    /* Tipografi display ala referensi: ukuran raksasa, tracking rapat, baris kedua jadi "ghost". */
    .display-hero {
        font-family: 'Space Grotesk', 'Poppins', sans-serif;
        font-weight: 500;
        line-height: .84;
        letter-spacing: -.045em;
        font-size: clamp(2rem, 11.5vw, 10.5rem);
    }
    .display-statement {
        font-family: 'Space Grotesk', 'Poppins', sans-serif;
        font-weight: 500;
        line-height: 1.06;
        letter-spacing: -.02em;
        font-size: clamp(1.45rem, 4.1vw, 3rem);
    }
    .display-2 {
        font-family: 'Space Grotesk', 'Poppins', sans-serif;
        font-weight: 500;
        line-height: 1.02;
        letter-spacing: -.03em;
        font-size: clamp(1.7rem, 4.4vw, 3.2rem);
    }
    .text-ghost { color: #A1A1AA; }
    /* Figur hero: PNG berlatar hitam murni, jadi mix-blend screen membuat hitamnya hilang ke bg ink.
       Mask di bawah bikin badan robot memudar sebelum menabrak teks form. */
    .hero-figure {
        mix-blend-mode: screen;
        -webkit-mask-image: linear-gradient(to bottom, #000 52%, rgba(0, 0, 0, .55) 78%, transparent 96%);
        mask-image: linear-gradient(to bottom, #000 52%, rgba(0, 0, 0, .55) 78%, transparent 96%);
    }
    @media (max-width: 767px) {
        /* Di ponsel badan robot kepanjangan sampai menabrak paragraf -> dipotong lebih awal. */
        .hero-figure {
            -webkit-mask-image: linear-gradient(to bottom, #000 26%, rgba(0, 0, 0, .5) 46%, transparent 68%);
            mask-image: linear-gradient(to bottom, #000 26%, rgba(0, 0, 0, .5) 46%, transparent 68%);
        }
    }
    @media (min-width: 768px) and (max-width: 1023px) {
        /* Tablet: kolom masih satu kolom, badan robot dipotong sebelum sampai ke form. */
        .hero-figure {
            -webkit-mask-image: linear-gradient(to bottom, #000 34%, rgba(0, 0, 0, .5) 58%, transparent 84%);
            mask-image: linear-gradient(to bottom, #000 34%, rgba(0, 0, 0, .5) 58%, transparent 84%);
        }
    }
    .template-scroll { scroll-snap-type: x proximity; scroll-padding-inline: 20px; }
    .template-scroll:focus-visible { outline: 2px solid #A1F102; outline-offset: -2px; }
    .template-gallery {
        display: grid;
        grid-template-columns: 1fr 1fr 1.25fr 1fr 1fr;
        align-items: center;
        gap: clamp(12px, 1.4vw, 20px);
        min-width: 900px;
        max-width: 1240px;
        margin-inline: auto;
    }
    .template-card { position: relative; overflow: hidden; border-radius: 24px; scroll-snap-align: center; }
    .template-card--small { height: 240px; }
    .template-card--medium { height: 340px; }
    .template-card--hero { height: 460px; }
    @media (min-width: 1024px) {
        .template-gallery { min-width: 0; }
    }
    /* translate terpisah supaya reveal tidak menimpa transform pada konten kartu. */
    [data-reveal] { opacity: 0; }
    [data-reveal].is-visible { animation: reveal-up .7s cubic-bezier(.22, 1, .36, 1) var(--reveal-delay, 0ms) both; }
    @keyframes reveal-up {
        from { opacity: 0; translate: 0 26px; }
        to { opacity: 1; translate: 0 0; }
    }
    html { scroll-behavior: smooth; }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        [data-reveal] { opacity: 1; }
        [data-reveal].is-visible { animation: none; }
    }
</style>
<script>
    const revealables = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries, obs) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            }
        }, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });
        revealables.forEach((el) => io.observe(el));
    } else {
        revealables.forEach((el) => el.classList.add('is-visible'));
    }
</script>
<script>
    const navToggle = document.getElementById('navToggle');
    const mobileNav = document.getElementById('mobileNav');
    const setNav = (open) => {
        mobileNav.classList.toggle('hidden', !open);
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        navToggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    };
    navToggle.addEventListener('click', () => setNav(mobileNav.classList.contains('hidden')));
    mobileNav.addEventListener('click', (ev) => { if (ev.target.closest('a')) setNav(false); });
    window.addEventListener('resize', () => { if (window.innerWidth >= 768) setNav(false); });
</script>
<script>
    const typeInput = document.getElementById('heroType');
    const chips = document.querySelectorAll('#heroChips .chip');
    const setType = (type) => chips.forEach((c) => {
        const active = c.dataset.type === type;
        typeInput.value = type;
        // Kelas warna aktif/non-aktif di-toggle mutual eksklusif: kalau dua-duanya menempel,
        // yang menang cuma urutan stylesheet Tailwind CDN — bukan maksud kita (teks chip aktif jadi tak terbaca).
        c.classList.toggle('bg-brand-400', active);
        c.classList.toggle('bg-white/5', !active);
        c.classList.toggle('text-ink', active);
        c.classList.toggle('text-zinc-300', !active);
        c.classList.toggle('hover:text-ink', active);
        c.classList.toggle('hover:text-paper', !active);
        c.classList.toggle('hover:bg-brand-500', active);
        c.classList.toggle('hover:bg-white/10', !active);
    });
    chips.forEach((c) => c.addEventListener('click', () => setType(c.dataset.type)));
    setType('prd');
    document.querySelector('[data-example]').addEventListener('click', (e) => {
        const input = document.querySelector('#heroForm input[name=prompt]');
        input.value = e.currentTarget.dataset.example;
        input.focus();
    });
</script>
</body>
</html>
