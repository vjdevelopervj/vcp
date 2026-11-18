<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$boot = [
    'csrf' => csrf_token(),
    'docId' => isset($_GET['doc']) && is_string($_GET['doc']) ? (int) $_GET['doc'] : null,
    'view' => isset($_GET['view']) && $_GET['view'] === 'history' ? 'history' : null,
    'newType' => valid_type($_GET['new'] ?? null),
    'prompt' => isset($_GET['prompt']) && is_string($_GET['prompt']) ? mb_substr(trim($_GET['prompt']), 0, 2000) : null,
    'promptType' => valid_type($_GET['type'] ?? null) ?? 'prd',
    'types' => DOC_TYPES,
    'demo' => AI_API_KEY === '',
];

$pageTitle = 'Workspace — vcp';
include __DIR__ . '/includes/head.php';
?>
<body class="h-screen h-[100dvh] overflow-hidden bg-ink font-sans text-paper antialiased">
<script id="boot" type="application/json"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<div class="flex h-full">
    <!-- SIDEBAR -->
    <div id="sidebarBackdrop" class="fixed inset-0 z-30 hidden bg-black/40 backdrop-blur-sm lg:hidden"></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[85vw] -translate-x-full flex-col border-r border-white/10 bg-panel transition-transform lg:static lg:translate-x-0">
        <div class="flex items-center justify-between px-6 pb-4 pt-[max(1.5rem,env(safe-area-inset-top))]">
            <?php $logoIcon = true; include __DIR__ . '/includes/logo.php'; ?>
            <div class="flex items-center gap-1">
                <button id="sidebarCollapse" class="hidden rounded-lg p-1.5 text-zinc-400 transition hover:bg-white/10 hover:text-paper lg:block" title="Collapse sidebar (Ctrl+B)" aria-label="Collapse sidebar" aria-expanded="true">
                    <svg class="h-5 w-5 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <button id="sidebarClose" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/10 lg:hidden" aria-label="Close sidebar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </div>

        <div class="px-4">
            <button id="newDocBtn" class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-brand-300 to-brand-500 py-3 text-sm font-semibold text-ink shadow-[0_6px_24px_-8px_rgba(161,241,2,0.55)] transition hover:from-brand-400 hover:to-brand-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                <span class="side-label">New Chat</span>
            </button>
        </div>

        <nav class="mt-6 px-4">
            <p class="side-block px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Menu</p>
            <ul class="space-y-0.5 text-sm" id="filterMenu">
                <li><button data-filter="" class="filter-btn flex w-full items-center justify-between rounded-xl px-3 py-2.5 transition hover:bg-white/5" title="All Documents">
                    <span class="flex items-center gap-3"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span class="side-label">All Documents</span></span>
                    <span class="side-count count text-xs text-zinc-500" data-count=""></span></button></li>
                <li><button data-filter="planning" class="filter-btn flex w-full items-center justify-between rounded-xl px-3 py-2.5 transition hover:bg-white/5" title="Planning">
                    <span class="flex items-center gap-3"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/></svg><span class="side-label">Planning</span></span>
                    <span class="side-count count text-xs text-zinc-500" data-count="planning"></span></button></li>
                <li><button data-filter="design" class="filter-btn flex w-full items-center justify-between rounded-xl px-3 py-2.5 transition hover:bg-white/5" title="Design">
                    <span class="flex items-center gap-3"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.7-.9 1.4-1.9-.3-.9.2-2.1 1.4-2.1H17a4 4 0 0 0 4-4c0-5.5-4-10-9-10z"/><circle cx="7.5" cy="11" r="1"/><circle cx="11" cy="7" r="1"/><circle cx="15.5" cy="8.5" r="1"/></svg><span class="side-label">Design</span></span>
                    <span class="side-count count text-xs text-zinc-500" data-count="design"></span></button></li>
                <li><button data-filter="prd" class="filter-btn flex w-full items-center justify-between rounded-xl px-3 py-2.5 transition hover:bg-white/5" title="PRD">
                    <span class="flex items-center gap-3"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg><span class="side-label">PRD</span></span>
                    <span class="side-count count text-xs text-zinc-500" data-count="prd"></span></button></li>
                <li><button id="historyBtn" class="filter-btn flex w-full items-center justify-between rounded-xl px-3 py-2.5 transition hover:bg-white/5" title="History">
                    <span class="flex items-center gap-3"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><span class="side-label">History</span></span>
                </button></li>
                <li><a href="settings.php" class="nav-link flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition hover:bg-white/5" title="Settings">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg>
                    <span class="side-label">Settings</span>
                </a></li>
            </ul>
        </nav>

        <div class="mt-6 flex min-h-0 flex-1 flex-col px-4">
            <p class="side-block px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Recent</p>
            <div id="docSearchWrap" class="side-block relative mb-2">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="docSearch" placeholder="Search documents" class="w-full rounded-full bg-white/5 py-2 pl-9 pr-3 text-base sm:text-sm text-paper placeholder:text-zinc-500 outline-none focus:ring-2 focus:ring-brand-400/30">
            </div>
            <ul id="docList" class="-mx-1 flex-1 space-y-0.5 overflow-y-auto px-1 pb-4 text-sm"></ul>
        </div>

        <div class="border-t border-white/10 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div id="userRow" class="flex items-center gap-3">
                <a href="settings.php" title="Account settings" class="shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-brand-600 font-semibold text-ink">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= e($user['avatar']) ?>" alt="" class="h-full w-full object-cover">
                        <?php else: ?>
                            <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                </a>
                <div id="userMeta" class="side-label min-w-0 flex-1">
                    <a href="settings.php" class="block truncate text-sm font-medium hover:text-brand-300"><?= e($user['name']) ?></a>
                    <p class="truncate text-xs text-zinc-500"><?= e($user['email']) ?></p>
                </div>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button title="Logout" class="rounded-lg p-2 text-zinc-400 transition hover:bg-white/10 hover:text-paper">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="relative flex min-w-0 flex-1 flex-col">
        <!-- Mobile top bar -->
        <div class="flex items-center gap-3 border-b border-white/10 bg-panel px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3 lg:hidden">
            <button id="sidebarOpen" class="rounded-lg p-2 hover:bg-white/10 transition" aria-label="Open sidebar">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="ml-auto hidden rounded-full bg-white/10 p-1 text-xs sm:text-sm" id="mobileTabs">
                <button data-pane="chat" class="rounded-full px-3.5 sm:px-4 py-1.5 font-medium transition">Chat</button>
                <button data-pane="preview" class="relative rounded-full px-3.5 sm:px-4 py-1.5 font-medium transition">Preview<span id="mobilePreviewDot" class="absolute right-1 top-1 hidden h-2 w-2 rounded-full bg-brand-400 ring-2 ring-panel"></span></button>
            </div>
        </div>

        <!-- Empty state -->
        <section id="emptyState" class="relative flex flex-1 items-start justify-center overflow-y-auto overflow-x-hidden noise md:items-center">
            <div class="blob w-[420px] h-[360px] bg-brand-400/25 -top-20 right-[10%]"></div>
            <div class="blob w-[420px] h-[420px] bg-brand-600/30 bottom-0 left-[5%]"></div>
            <div class="relative z-10 max-w-4xl px-4 sm:px-6 py-8 sm:py-12 text-center">
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight md:text-5xl">Hi, <?= e(explode(' ', $user['name'])[0]) ?>. What are we<br class="hidden md:block"> building today?</h1>
                <p class="mt-3 sm:mt-4 text-sm sm:text-base text-zinc-400">Pilih jenis dokumen untuk memulai percakapan dengan AI.</p>
                <div class="mt-8 sm:mt-10 grid gap-4 sm:gap-5 grid-cols-1 sm:grid-cols-3">
                    <?php foreach ([['planning', 'markdown-document-3d.svg'], ['design', 'markdown-document-3d.svg'], ['prd', 'markdown-document-3d.svg']] as [$key, $img]): ?>
                        <button data-new="<?= $key ?>" class="group rounded-2xl border border-white/10 bg-panel/80 p-2 text-left shadow-soft backdrop-blur transition hover:-translate-y-1 hover:border-brand-400/40">
                            <div class="relative h-28 sm:h-32 overflow-hidden rounded-xl bg-[#13131C]">
                                <img src="assets/img/<?= $img ?>" alt="" class="absolute inset-0 h-full w-full object-cover object-right-top transition duration-300 group-hover:scale-105">
                                <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-ink/90 to-transparent"></div>
                                <span class="absolute bottom-3 left-4 text-lg sm:text-xl font-semibold text-paper"><?= DOC_TYPES[$key] ?></span>
                            </div>
                            <span class="flex items-center justify-between px-2 pb-1 pt-3 text-xs text-zinc-400">Create new <svg class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Workspace -->
        <section id="workspace" class="hidden min-h-0 flex-1">
            <!-- Chat -->
            <div id="chatPane" class="flex min-h-0 w-full flex-col border-r border-white/10 bg-panel lg:shrink-0">
                <div class="flex items-center gap-2 sm:gap-3 border-b border-white/10 px-3 sm:px-5 py-3 sm:py-4">
                    <span id="docTypeBadge" class="rounded-full px-2.5 sm:px-3 py-0.5 sm:py-1 text-[11px] sm:text-xs font-medium"></span>
                    <input id="docTitle" maxlength="150" placeholder="Chat baru — belum tersimpan" class="min-w-0 flex-1 truncate rounded-lg bg-transparent px-2 py-1 text-base sm:text-[15px] font-semibold outline-none hover:bg-white/5 focus:bg-white/5" title="Click to rename">
                    <button id="previewExpand" title="Tampilkan preview" class="relative shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-white/10 hover:text-paper" aria-label="Show preview">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 4h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M3 9v6M3 12h5"/></svg>
                        <span id="previewDot" class="absolute right-1 top-1 hidden h-2 w-2 rounded-full bg-brand-400"></span>
                    </button>
                    <button id="newChatBtn" title="New Chat" class="shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-white/10 hover:text-paper" aria-label="New Chat">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>

                <div id="messages" class="flex-1 space-y-4 sm:space-y-5 overflow-y-auto px-3 sm:px-5 py-4 sm:py-6"></div>

                <div class="border-t border-white/10 p-3 sm:p-4 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <div id="suggestions" class="mb-3 flex flex-wrap gap-1.5 sm:gap-2"></div>
                    <div id="attachStrip" class="mb-3 hidden flex-wrap gap-1.5 sm:gap-2"></div>
                    <form id="chatForm" class="flex items-end gap-1.5 sm:gap-2 rounded-3xl border border-white/10 bg-white/5 p-1.5 sm:p-2 pl-2 transition focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-400/15">
                        <button id="attachBtn" type="button" title="Lampirkan gambar / file teks / PDF / DOCX (drag & drop juga bisa)" aria-label="Lampirkan file"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-zinc-400 transition hover:bg-white/10 hover:text-paper disabled:opacity-40">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5 5 0 0 1-7-7l8-8a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7-7"/></svg>
                        </button>
                        <input type="file" id="attachInput" accept="image/png,image/jpeg,image/webp,image/gif,.txt,.md,.markdown,.csv,.json,.log,.pdf,.docx" multiple class="hidden">
                        <textarea id="chatInput" rows="1" maxlength="8000" placeholder="Ceritakan idemu atau minta revisi..."
                                  class="max-h-36 sm:max-h-40 flex-1 resize-none bg-transparent py-2 text-base sm:text-sm outline-none placeholder:text-zinc-500"></textarea>
                        <button id="sendBtn" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-300 to-brand-500 text-ink transition hover:from-brand-400 hover:to-brand-600 disabled:opacity-40" aria-label="Send">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 19V5m-6 6 6-6 6 6"/></svg>
                        </button>
                    </form>
                    <p class="mt-2 px-2 text-[10px] sm:text-[11px] text-zinc-500">Enter untuk kirim · Shift+Enter untuk baris baru<?= $boot['demo'] ? ' · <span class="text-amber-400">Demo mode</span>' : '' ?></p>
                </div>
            </div>

            <!-- Preview -->
            <div id="previewResizer" class="group hidden w-2 shrink-0 cursor-col-resize items-center justify-center lg:flex" title="Geser untuk mengatur lebar preview · klik dua kali untuk reset">
                <div class="h-14 w-1 rounded-full bg-white/15 transition group-hover:bg-brand-400/70 group-active:bg-brand-400"></div>
            </div>
            <div id="previewPane" class="hidden min-h-0 min-w-0 flex-1 flex-col lg:flex">
                <div class="flex items-center gap-1.5 sm:gap-2 border-b border-white/10 bg-panel px-3 sm:px-5 py-2.5 sm:py-3">
                    <div class="flex rounded-full bg-white/10 p-0.5 sm:p-1 text-xs sm:text-sm" id="viewTabs">
                        <button data-view="preview" class="rounded-full px-3 sm:px-4 py-1 sm:py-1.5 transition">Preview</button>
                        <button data-view="markdown" class="rounded-full px-3 sm:px-4 py-1 sm:py-1.5 transition">Markdown</button>
                    </div>
                    <span id="saveStatus" class="ml-1 sm:ml-2 text-xs text-zinc-500"></span>
                    <div class="ml-auto flex items-center gap-1 sm:gap-1.5">
                        <button id="copyBtn" class="flex items-center gap-1.5 rounded-full px-2.5 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm text-zinc-300 transition hover:bg-white/10 hover:text-paper" title="Copy document">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg><span class="hidden sm:inline">Copy</span>
                        </button>
                        <button id="downloadBtn" class="flex items-center gap-1.5 rounded-full bg-paper px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm text-ink transition hover:bg-zinc-200" title="Download .md">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M5 21h14"/></svg><span id="downloadLabel">.md</span>
                        </button>
                        <button id="deleteBtn" title="Delete document" class="rounded-full p-1.5 sm:p-2 text-zinc-400 transition hover:bg-red-500/10 hover:text-red-400" aria-label="Delete document">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/></svg>
                        </button>
                        <button id="previewCollapse" title="Sembunyikan preview" class="hidden rounded-full p-2 text-zinc-400 transition hover:bg-white/10 hover:text-paper lg:block" aria-label="Collapse preview">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 4h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M3 9v6M8 12H3"/></svg>
                        </button>
                    </div>
                </div>
                <div id="docScroll" class="min-h-0 flex-1 overflow-y-auto bg-panel">
                    <article id="previewDoc" class="prose prose-invert max-w-none w-full bg-panel p-4 sm:p-8 md:p-12
                        prose-headings:font-semibold prose-h1:text-2xl sm:prose-h1:text-3xl prose-a:text-brand-400 prose-code:rounded prose-code:bg-white/10 prose-code:px-1 prose-code:before:content-none prose-code:after:content-none
                        prose-table:text-sm prose-th:bg-white/5 prose-th:px-3 prose-td:px-3 prose-pre:bg-black/60 prose-img:rounded-xl prose-img:border prose-img:border-white/10"></article>
                    <textarea id="markdownEditor" spellcheck="false" class="hidden h-full min-h-[60vh] w-full resize-none bg-panel p-4 sm:p-8 font-mono text-base sm:text-sm leading-relaxed text-paper outline-none focus:ring-4 focus:ring-brand-400/15"></textarea>
                </div>
            </div>
        </section>

        <!-- History -->
        <section id="historyView" class="hidden min-h-0 flex-1 flex-col bg-ink">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 border-b border-white/10 bg-panel px-4 sm:px-6 py-3 sm:py-4">
                <div class="flex items-center justify-between w-full sm:w-auto gap-3">
                    <div class="flex items-baseline gap-2">
                        <h1 class="text-lg font-semibold">History</h1>
                        <p id="historyTotal" class="text-xs text-zinc-500"></p>
                    </div>
                    <button id="historyClose" title="Tutup history" class="rounded-lg p-2 text-zinc-400 transition hover:bg-white/10 hover:text-paper sm:hidden" aria-label="Tutup history">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="relative w-full sm:ml-auto sm:max-w-sm">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="historySearch" placeholder="Cari kata di dalam semua chat..." class="w-full rounded-full bg-white/5 py-2 pl-9 pr-3 text-base sm:text-sm text-paper placeholder:text-zinc-500 outline-none focus:ring-2 focus:ring-brand-400/30">
                </div>
                <button id="historyCloseDesktop" title="Tutup history" class="hidden sm:block rounded-lg p-2 text-zinc-400 transition hover:bg-white/10 hover:text-paper" aria-label="Tutup history">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 sm:px-6 py-4 sm:py-6">
                <div class="mb-5 sm:mb-6 flex gap-2 overflow-x-auto pb-1 sm:pb-0 sm:flex-wrap" id="historyFilters"></div>
                <div id="historyGroups" class="space-y-6 sm:space-y-8"></div>
                <div id="historyMore" class="mt-6 flex justify-center"></div>
            </div>
        </section>
    </main>
</div>

<div id="dropOverlay" class="pointer-events-none fixed inset-0 z-40 hidden items-center justify-center bg-ink/80 p-6 backdrop-blur-sm">
    <div class="rounded-3xl border-2 border-dashed border-brand-400 bg-panel/90 px-10 py-8 text-center shadow-soft">
        <svg class="mx-auto h-10 w-10 text-brand-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 16V4m-5 5 5-5 5 5M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        <p class="mt-3 text-lg font-semibold">Lepaskan untuk melampirkan</p>
        <p class="mt-1 text-sm text-zinc-400">Gambar · .txt / .md / .csv · PDF · DOCX</p>
    </div>
</div>

<div id="toast" class="pointer-events-none fixed bottom-6 left-1/2 z-50 -translate-x-1/2 translate-y-4 rounded-full bg-paper px-5 py-2.5 text-sm text-ink opacity-0 shadow-lg transition"></div>

<style>
    html, body {
        height: 100%;
        height: 100dvh;
        overscroll-behavior-y: none;
    }
    #docScroll, #messages, #docList, #historyView > div:last-child {
        -webkit-overflow-scrolling: touch;
    }
    .type-dot { width: 10px; height: 10px; border-radius: 9999px; display: inline-block; }
    .type-planning { background: #A1F102; } .type-design { background: #34D399; } .type-prd { background: #69D202; }
    .badge-planning { background: rgba(161, 241, 2, 0.15); color: #CBF407; } .badge-design { background: rgba(52, 211, 153, 0.15); color: #6EE7B7; } .badge-prd { background: rgba(105, 210, 2, 0.15); color: #A1F102; }
    .prose input[type=checkbox] { margin: 0 .5rem 0 0; accent-color: #A1F102; }
    .prose li:has(> input[type=checkbox]) { list-style: none; margin-left: -1.25rem; }
    .typing span { animation: blink 1.2s infinite both; } .typing span:nth-child(2) { animation-delay: .2s; } .typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes blink { 0%, 80%, 100% { opacity: .2; } 40% { opacity: 1; } }
    .prose .mermaid { display: flex; justify-content: center; margin: 1.75em 0; overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
    .prose .mermaid svg { min-width: 260px; max-width: 100%; height: auto; }
    /* Tabel & code yang lebih lebar dari pane: hanya bloknya sendiri yang bergeser
       (wrapper .table-scroll dibuat renderPreview), jadi preview tidak pernah memotong isi. */
    #previewDoc .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 1.5rem 0; }
    #previewDoc .table-scroll table { margin: 0; min-width: 100%; }
    #previewDoc pre { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    /* Diagram yang tetap gagal parse: tampilkan kodenya, bukan kotak error bawaan mermaid. */
    #previewDoc .mermaid-fallback { margin: 1.75em 0; padding: 14px 16px; border: 1px solid rgba(251, 191, 36, .35); border-radius: 14px; background: rgba(251, 191, 36, .07); }
    #previewDoc .mermaid-fallback p { margin: 0 0 10px; font-size: .8rem; line-height: 1.45; color: #FBBF24; }
    #previewDoc .mermaid-fallback pre { margin: 0; padding: 0; overflow-x: auto; background: none; font-size: .78rem; line-height: 1.5; color: #E4E4E7; }
    /* Target sentuh & responsivitas di ponsel/tablet: kontrol yang sering dipakai dinaikkan ke >=40px */
    @media (max-width: 1023px) {
        #sidebar { max-width: 85vw; }
        #sidebarOpen, #previewExpand, #newChatBtn, #historyClose, #historyCloseDesktop, #sidebarClose { min-width: 40px; min-height: 40px; }
        #mobileTabs button { min-height: 36px; }
        #suggestions > button { min-height: 38px; }
        #docTitle { min-height: 36px; }
        #docList .doc-menu-btn { min-width: 36px; min-height: 36px; }
        #chatInput { overflow-y: auto; scrollbar-width: thin; }
    }
    /* Lebar chat diatur lewat --chat-w (drag handle / localStorage); preview selalu memenuhi sisa layar sampai tepi kanan. Mobile tetap full width. */
    @media (min-width: 1024px) {
        #chatPane { width: var(--chat-w, 460px); }
    }
    /* 1024-1199px: sidebar 288 + chat 460 menyisakan preview cuma ~275px. Chat dibatasi 380
       supaya dokumen punya ruang; hasil drag yang lebih kecil tetap dihormati (pakai min()). */
    @media (min-width: 1024px) and (max-width: 1199px) {
        #chatPane { width: min(var(--chat-w, 460px), 380px); }
    }
    .logo-icon { display: none; }
    /* Rel drawer mobile: waktu dokumen tidak cukup ruang di samping judul + menu. */
    @media (max-width: 1023px) { #docList .doc-time { display: none; } }
    #sidebar { transition: width .22s ease; }
    /* #sidebar/#chatPane adalah flex item: flex-basis menang atas width, jadi mode collapse
       harus menulis ulang keduanya (utility tw-width di markup tidak bisa diandalkan). */
    @media (min-width: 1024px) {
        /* #sidebar/#chatPane itu flex item: flex-basis + min-width harus ditulis ulang,
           kalau tidak ukurannya terkunci oleh lebar konten (min-width:auto). */
        #sidebar { flex: 0 0 288px; min-width: 0; }
        body.sb-collapsed #sidebar { width: 76px; flex: 0 0 76px; overflow-x: hidden; }
        body.sb-collapsed #sidebar .side-label,
        body.sb-collapsed #sidebar .side-block,
        body.sb-collapsed #sidebar .side-count,
        body.sb-collapsed #docList .doc-title,
        body.sb-collapsed #docList .doc-time,
        body.sb-collapsed #docList .doc-menu-btn,
        body.sb-collapsed #docSearchWrap { display: none; }
        body.sb-collapsed .logo-wordmark { display: none; }
        body.sb-collapsed .logo-icon { display: block; }
        body.sb-collapsed #sidebarCollapse svg { transform: rotate(180deg); }
        body.sb-collapsed #newDocBtn,
        body.sb-collapsed #filterMenu .filter-btn,
        body.sb-collapsed #filterMenu .nav-link,
        body.sb-collapsed #docList .doc-open { justify-content: center; padding-left: 0; padding-right: 0; }
        /* Collapsed (lebar 76px): avatar di atas, logout di bawahnya — berdampingan tidak muat. */
        body.sb-collapsed #userRow { flex-direction: column; align-items: center; gap: 6px; }
        body.sb-collapsed #userRow form { display: flex; }
        body.sb-collapsed #userRow form button { width: 36px; }
    }
    /* Preview collapse: chat mengambil alih seluruh lebar.
       Resizer tetap flex (display:none-nya kalah oleh utility lg:flex) tapi lebarnya dinolkan. */
    #previewExpand { display: none; }
    @media (min-width: 1024px) {
        body.pv-collapsed #previewPane { display: none; }
        body.pv-collapsed #previewResizer { flex: 0 0 0; overflow: hidden; }
        body.pv-collapsed #chatPane { width: auto; flex: 1 1 auto; }
        body.pv-collapsed #previewExpand { display: inline-flex; }
    }
    #historyView mark { background: rgba(161, 241, 2, .22); color: #CBF407; border-radius: 4px; padding: 0 2px; }
    /* Aturan ID supaya tidak kalah oleh urutan kelas di stylesheet Tailwind CDN. */
    #dropOverlay:not(.hidden) { display: flex; }
</style>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.3/dist/mermaid.min.js"></script>
<script>
    if (window.mermaid) {
        mermaid.initialize({ startOnLoad: false, theme: 'dark', securityLevel: 'strict', fontFamily: 'Poppins, sans-serif' });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script src="assets/js/confirm.js?v=<?= filemtime(__DIR__ . '/assets/js/confirm.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
