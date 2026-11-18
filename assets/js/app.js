(() => {
    const boot = JSON.parse(document.getElementById('boot').textContent);
    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    const state = {
        docs: [],
        counts: {},
        filter: '',
        search: '',
        doc: null,
        draftType: null,
        draftVersion: 0,
        messages: [],
        view: 'preview',
        busy: false,
        pane: 'chat',
        pending: [],
        screen: 'chat',
    };

    const SUGGESTIONS = {
        planning: ['Aplikasi booking lapangan futsal online', 'Platform kursus online untuk UMKM', 'Website portfolio fotografer'],
        design: ['Design system untuk aplikasi fintech', 'User flow aplikasi ojek online', 'Dashboard admin toko online'],
        prd: ['Fitur chat real-time di marketplace', 'Aplikasi absensi karyawan dengan GPS', 'Sistem langganan (subscription) SaaS'],
    };
    const REVISIONS = ['Tambahkan timeline yang lebih detail', 'Buat lebih ringkas', 'Tambahkan tabel risiko', 'Translate ke English'];

    marked.setOptions({ gfm: true, breaks: false });
    const md = (text) => DOMPurify.sanitize(marked.parse(text || ''));

    async function api(url, body) {
        const opts = body
            ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': boot.csrf }, body: JSON.stringify(body) }
            : {};
        const res = await fetch(url, opts);
        if (res.status === 401) {
            location.href = 'login.php';
            throw new Error('Unauthorized');
        }
        const data = await res.json().catch(() => ({ error: 'Respons server tidak valid.' }));
        if (!res.ok) throw new Error(data.error || 'Terjadi kesalahan.');
        return data;
    }

    let toastTimer;
    function toast(msg) {
        const el = $('#toast');
        el.textContent = msg;
        el.classList.remove('opacity-0', 'translate-y-4');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.add('opacity-0', 'translate-y-4'), 2200);
    }

    // ---------- Notifikasi: AI selesai saat tab tidak aktif ----------
    // AI butuh puluhan detik per jawaban, jadi user sering pindah tab. Badge angka di judul tab
    // jalan di semua kondisi; popup Notification hanya tersedia di secure context (vcp.test via
    // http bukan secure context, jadi di situ yang aktif cuma badge-nya).
    const baseTitle = document.title;
    let unread = 0;

    function notifyDone(success, title, snippet) {
        if (!document.hidden) return;
        unread++;
        document.title = `(${unread}) ${baseTitle}`;
        if (!window.isSecureContext || !('Notification' in window) || Notification.permission !== 'granted') return;
        try {
            const n = new Notification(success ? 'Dokumen siap' : 'AI gagal menjawab', {
                body: `${title}${snippet ? ' — ' + snippet : ''}`,
                tag: 'vcp-ai',
            });
            n.addEventListener('click', () => { window.focus(); n.close(); });
        } catch {}
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            unread = 0;
            document.title = baseTitle;
        }
    });

    function relTime(ts) {
        const diff = (Date.now() - new Date(ts.replace(' ', 'T')).getTime()) / 1000;
        if (diff < 60) return 'baru saja';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'j';
        return Math.floor(diff / 86400) + 'h';
    }

    // ---------- Sidebar ----------
    async function loadList() {
        const params = new URLSearchParams();
        if (state.filter) params.set('type', state.filter);
        if (state.search) params.set('q', state.search);
        const data = await api('api/documents.php?' + params);
        state.docs = data.documents;
        state.counts = data.counts;
        renderList();
    }

    function renderList() {
        const total = Object.values(state.counts).reduce((a, b) => a + Number(b), 0);
        $$('[data-count]').forEach((el) => {
            const key = el.dataset.count;
            el.textContent = key ? (state.counts[key] || 0) : total;
        });
        $$('.filter-btn').forEach((btn) => {
            const active = btn.dataset.filter === state.filter;
            btn.classList.toggle('bg-white/10', active);
            btn.classList.toggle('font-medium', active);
            btn.classList.toggle('text-zinc-400', !active);
        });

        const list = $('#docList');
        list.innerHTML = '';
        if (!state.docs.length) {
            list.innerHTML = '<li class="px-3 py-6 text-center text-xs text-zinc-500">Belum ada dokumen</li>';
            return;
        }
        for (const d of state.docs) {
            const li = document.createElement('li');
            const active = state.doc && Number(state.doc.id) === Number(d.id);
            const pinned = Number(d.pinned);
            li.className = 'group relative';
            li.innerHTML = `
                <div class="flex items-center">
                    <button class="doc-open flex min-w-0 flex-1 items-center gap-3 rounded-xl py-2.5 pl-3 pr-1 text-left transition ${active ? 'bg-white/10' : 'hover:bg-white/5'}">
                        <span class="type-dot type-${d.type} shrink-0"></span>
                        <span class="doc-title min-w-0 flex-1 truncate ${active ? 'font-medium' : 'text-zinc-300'}"></span>
                        ${pinned ? '<svg class="h-3.5 w-3.5 shrink-0 text-brand-300" viewBox="0 0 24 24" fill="currentColor"><path d="M16 3a2 2 0 0 1 2 2v4.2l1.7 3.4a1 1 0 0 1-.9 1.4H14v5.6a1 1 0 0 1-1.7.7l-1-1a1 1 0 0 1-.3-.7V14H5.2a1 1 0 0 1-.9-1.4L6 9.2V5a2 2 0 0 1 2-2h8z"/></svg>' : ''}
                        <span class="doc-time shrink-0 text-[11px] text-zinc-500">${relTime(d.updated_at)}</span>
                    </button>
                    <button class="doc-menu-btn ${pinned ? 'block' : 'hidden group-hover:block'} shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-white/10 hover:text-paper" title="Menu chat" aria-label="Menu chat">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                    </button>
                </div>
                <div class="doc-menu absolute right-1 top-full z-20 hidden w-40 overflow-hidden rounded-2xl border border-white/10 bg-panel p-1.5 shadow-soft">
                    <button data-act="pin" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm hover:bg-white/10">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1z"/></svg>
                        <span>${pinned ? 'Lepas pin' : 'Pin chat'}</span>
                    </button>
                    <button data-act="rename" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm hover:bg-white/10">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Rename
                    </button>
                    <button data-act="delete" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm text-red-400 hover:bg-red-500/10">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/></svg>
                        Hapus
                    </button>
                </div>`;
            li.querySelector('.doc-title').textContent = d.title;
            li.querySelector('.doc-open').title = d.title; // tooltip tetap terbaca saat sidebar jadi rail ikon
            li.querySelector('.doc-open').addEventListener('click', () => {
                openDoc(d.id);
                closeSidebar();
            });
            const menu = li.querySelector('.doc-menu');
            li.querySelector('.doc-menu-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                $$('.doc-menu').forEach((m) => { if (m !== menu) m.classList.add('hidden'); });
                menu.classList.toggle('hidden');
            });
            menu.addEventListener('click', (e) => e.stopPropagation());
            menu.querySelector('[data-act="pin"]').addEventListener('click', async () => {
                menu.classList.add('hidden');
                await togglePin(d);
            });
            menu.querySelector('[data-act="rename"]').addEventListener('click', () => {
                menu.classList.add('hidden');
                startRename(li, d);
            });
            menu.querySelector('[data-act="delete"]').addEventListener('click', async () => {
                menu.classList.add('hidden');
                await deleteFromList(d);
            });
            list.appendChild(li);
        }
    }

    async function togglePin(d) {
        try {
            const { document: doc } = await api('api/documents.php', { action: 'pin', id: d.id, pinned: !Number(d.pinned) });
            if (state.doc && Number(state.doc.id) === Number(d.id)) state.doc.pinned = Number(doc.pinned);
            await loadList();
            toast(Number(doc.pinned) ? 'Chat dipin' : 'Pin dilepas');
        } catch (err) {
            toast(err.message);
        }
    }

    function startRename(li, d) {
        const span = li.querySelector('.doc-title');
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = 150;
        input.value = d.title;
        input.className = 'min-w-0 flex-1 rounded-lg border border-brand-400/60 bg-white/10 px-2 py-1 text-sm text-paper outline-none';
        span.replaceWith(input);
        input.focus();
        input.select();
        let done = false;
        const finish = async (save) => {
            if (done) return;
            done = true;
            const val = input.value.trim();
            input.replaceWith(span);
            span.textContent = d.title;
            if (!save || !val || val === d.title) return;
            try {
                const { document: doc } = await api('api/documents.php', { action: 'update', id: d.id, title: val });
                d.title = doc.title;
                if (state.doc && Number(state.doc.id) === Number(d.id)) {
                    state.doc.title = doc.title;
                    $('#docTitle').value = doc.title;
                }
                loadList();
            } catch (err) {
                toast(err.message);
            }
        };
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') finish(true);
            else if (e.key === 'Escape') finish(false);
        });
        input.addEventListener('blur', () => finish(true));
        input.addEventListener('click', (e) => e.stopPropagation());
    }

    async function deleteFromList(d) {
        if (!(await vcpConfirm({
            title: 'Hapus dokumen?',
            message: `"${d.title}" beserta seluruh riwayat chatnya akan dihapus permanen.`,
            confirmText: 'Hapus',
        }))) return;
        try {
            await api('api/documents.php', { action: 'delete', id: d.id });
            if (state.doc && Number(state.doc.id) === Number(d.id)) {
                state.doc = null;
                showWorkspace(false);
                syncUrl();
            }
            await loadList();
            toast('Dokumen dihapus');
        } catch (err) {
            toast(err.message);
        }
    }

    $$('.filter-btn').forEach((btn) => btn.addEventListener('click', () => {
        state.filter = btn.dataset.filter;
        loadList();
    }));

    let searchTimer;
    $('#docSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.search = e.target.value.trim();
            loadList();
        }, 250);
    });

    $('#newDocBtn').addEventListener('click', startNewChat);
    $('#newChatBtn').addEventListener('click', startNewChat);
    document.addEventListener('click', () => $$('.doc-menu').forEach((m) => m.classList.add('hidden')));
    $$('[data-new]').forEach((btn) => btn.addEventListener('click', () => {
        startDraftChat(btn.dataset.new);
        closeSidebar();
    }));

    const sidebar = $('#sidebar');
    const backdrop = $('#sidebarBackdrop');
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }
    $('#sidebarOpen').addEventListener('click', () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    });
    $('#sidebarClose').addEventListener('click', closeSidebar);
    backdrop.addEventListener('click', closeSidebar);

    // ---------- Collapse panel (desktop only, dipilih di CSS pakai body class) ----------
    const ui = {
        sidebar: localStorage.getItem('vcp.sidebarCollapsed') === '1',
        preview: localStorage.getItem('vcp.previewCollapsed') === '1',
    };

    function applyUiFlags() {
        document.body.classList.toggle('sb-collapsed', ui.sidebar);
        document.body.classList.toggle('pv-collapsed', ui.preview);
        $('#sidebarCollapse').setAttribute('aria-expanded', String(!ui.sidebar));
        $('#previewDot').classList.toggle('hidden', !(ui.preview && state.doc && state.doc.content.trim()));
        clampChatWidth();
    }

    function toggleSidebar() {
        ui.sidebar = !ui.sidebar;
        localStorage.setItem('vcp.sidebarCollapsed', ui.sidebar ? '1' : '0');
        applyUiFlags();
    }

    function setPreviewCollapsed(collapsed) {
        ui.preview = collapsed;
        localStorage.setItem('vcp.previewCollapsed', collapsed ? '1' : '0');
        applyUiFlags();
    }

    $('#sidebarCollapse').addEventListener('click', toggleSidebar);
    $('#previewCollapse').addEventListener('click', () => setPreviewCollapsed(true));
    $('#previewExpand').addEventListener('click', () => setPreviewCollapsed(false));
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'b') {
            e.preventDefault();
            toggleSidebar();
        }
    });

    // ---------- Documents ----------
    function syncUrl() {
        const url = state.screen === 'history' ? 'app.php?view=history'
            : state.doc ? `app.php?doc=${state.doc.id}` : 'app.php';
        history.replaceState(null, '', url);
    }

    function showWorkspace(show) {
        if (state.screen === 'history') {
            state.screen = 'chat';
            $('#historyView').classList.add('hidden');
            $('#historyView').classList.remove('flex');
        }
        $('#emptyState').classList.toggle('hidden', show);
        $('#emptyState').classList.toggle('flex', !show);
        $('#workspace').classList.toggle('hidden', !show);
        $('#workspace').classList.toggle('flex', show);
        $('#mobileTabs').classList.toggle('hidden', !show);
        $('#mobileTabs').classList.toggle('flex', show);
        syncPlaceholder();
    }

    // Chat baru sengaja belum disimpan: tidak ada baris di DB dan tidak muncul di Recent
    // sampai pesan pertama dijawab AI (dokumen dibuat di api/chat.php saat itu juga).
    function startNewChat() {
        wizCancel();
        state.doc = null;
        state.draftType = null;
        state.draftVersion++;
        state.messages = [];
        state.pending = [];
        renderAttachments();
        showWorkspace(false);
        syncUrl();
        closeSidebar();
    }

    function startDraftChat(type) {
        wizCancel();
        state.doc = null;
        state.draftType = type;
        state.draftVersion++;
        state.messages = [];
        state.pending = [];
        renderAttachments();
        showWorkspace(true);
        syncUrl();
        setPane('chat');
        renderDraft();
        $('#mobilePreviewDot')?.classList.add('hidden');
        $('#chatInput').focus();
        return true;
    }

    function renderDraft() {
        const badge = $('#docTypeBadge');
        badge.className = `rounded-full px-3 py-1 text-xs font-medium badge-${state.draftType}`;
        badge.textContent = currentTypeLabel();
        $('#docTitle').value = '';
        syncCodeEditor();
        $('#saveStatus').textContent = '';
        renderMessages();
        renderPreview();
        applyUiFlags();
    }

    async function openDoc(id) {
        wizCancel();
        try {
            const data = await api('api/documents.php?id=' + encodeURIComponent(id));
            state.doc = data.document;
            state.messages = data.messages;
            state.pending = [];
            renderAttachments();
            renderDoc();
            $('#mobilePreviewDot')?.classList.add('hidden');
            renderList();
        } catch (err) {
            toast(err.message);
            state.doc = null;
            showWorkspace(false);
            syncUrl();
        }
    }

    function renderDoc() {
        const doc = state.doc;
        state.draftType = null;
        state.draftVersion++;
        showWorkspace(true);
        syncUrl();
        const badge = $('#docTypeBadge');
        badge.className = `rounded-full px-3 py-1 text-xs font-medium badge-${doc.type}`;
        badge.textContent = currentTypeLabel();
        $('#docTitle').value = doc.title;
        syncCodeEditor();
        $('#saveStatus').textContent = '';
        renderMessages();
        renderPreview();
        applyUiFlags();
    }

    const currentType = () => (state.doc ? state.doc.type : state.draftType);
    const currentTypeLabel = () => boot.types[currentType()] || currentType();

    function renderPreview() {
        const content = state.doc ? state.doc.content : '';
        const preview = $('#previewDoc');
        preview.innerHTML = content.trim()
            ? md(content)
            : `<div class="not-prose flex flex-col items-center py-24 text-center text-zinc-500">
                   <svg class="mb-4 h-12 w-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>
                   <p class="font-medium text-zinc-400">Preview masih kosong</p>
                   <p class="mt-1 text-sm">Kirim pesan ke AI untuk mulai membuat dokumen.</p>
               </div>`;
        $$('#previewDoc input[type=checkbox]').forEach((cb) => cb.setAttribute('disabled', ''));
        // Tabel lebar bisa melebihi pane: bungkus scroll sendiri supaya yang bergeser cuma tabelnya,
        // bukan seluruh preview (pane tidak punya overflow-x).
        $$('#previewDoc table').forEach((table) => {
            if (table.parentElement && table.parentElement.classList.contains('table-scroll')) return;
            const wrap = document.createElement('div');
            wrap.className = 'table-scroll';
            table.replaceWith(wrap);
            wrap.appendChild(table);
        });
        renderMermaid();
    }

    // Label node yang memuat kurung bikin mermaid gagal parse (A[Parameter (Asal, Tujuan)]),
    // dan itu sering ditulis model. Kutip labelnya jadi A["Parameter (Asal, Tujuan)"].
    // Hanya flowchart/graph: kurung kurawal di erDiagram adalah blok atribut, bukan label.
    // Kelompok kurung pembentuk node mermaid + label tepi panah (|teks|). Yang dua karakter
    // didahulukan supaya [[x]] / {{x}} / [(x)] tidak dianggap dua kelompok tunggal.
    const MERMAID_GROUPS = [['[[', ']]'], ['{{', '}}'], ['((', '))'], ['[(', ')]'], ['[', ']'], ['{', '}'], ['|', '|']];

    // Sekali scan per baris dari kiri ke kanan: begitu suatu kelompok dikutip, isinya
    // tidak disentuh kelompok lain (kurung bersarang dulu bikin keluaran kacau).
    function repairMermaidLine(line) {
        let out = '';
        for (let i = 0; i < line.length;) {
            const group = MERMAID_GROUPS.find(([open]) => line.startsWith(open, i));
            if (!group) { out += line[i++]; continue; }
            const [open, close] = group;
            const end = line.indexOf(close, i + open.length);
            if (end === -1) return out + line.slice(i);
            const label = line.slice(i + open.length, end);
            if (/^".*"$/.test(label) || !/[(){}\[\]"|]/.test(label)) {
                out += line.slice(i, end + close.length);
            } else {
                out += open + '"' + label.replace(/"/g, "'") + '"' + close;
            }
            i = end + close.length;
        }
        return out;
    }

    function mermaidRepair(src) {
        if (!/^\s*(flowchart|graph)\b/m.test(src)) return src;
        return src.split('\n').map(repairMermaidLine).join('\n');
    }

    function mermaidFallback(node, src) {
        node.className = 'mermaid-fallback not-prose';
        node.textContent = '';
        const note = document.createElement('p');
        note.textContent = 'Diagram gagal dirender karena syntax mermaid tidak dikenali. Kodenya ditampilkan apa adanya — perbaiki lewat tab Markdown.';
        const pre = document.createElement('pre');
        const code = document.createElement('code');
        code.textContent = src;
        pre.appendChild(code);
        node.append(note, pre);
    }

    async function renderOneDiagram(node) {
        const src = node.dataset.src || '';
        let tried = '';
        for (const candidate of [src, mermaidRepair(src)]) {
            if (!candidate.trim() || candidate === tried) continue;
            tried = candidate;
            // parse() dulu: run() di v10 tetap menyisipkan SVG "Syntax error" kalau teksnya rusak.
            try {
                await mermaid.parse(candidate);
            } catch {
                continue;
            }
            node.textContent = candidate;
            try {
                await mermaid.run({ nodes: [node] });
            } catch {
                // ditangani fallback di bawah
            }
            if (node.querySelector('svg')) return;
        }
        mermaidFallback(node, src);
    }

    async function renderMermaid() {
        if (!window.mermaid) return;
        const nodes = [];
        $$('#previewDoc pre > code.language-mermaid').forEach((code) => {
            const div = document.createElement('div');
            div.className = 'mermaid';
            div.dataset.src = code.textContent;
            code.parentElement.replaceWith(div);
            nodes.push(div);
        });
        // Satu per satu, bukan sekali jalan: run() melempar di node pertama yang gagal
        // sehingga diagram sehat sesudahnya ikut tidak ter-render.
        for (const node of nodes) await renderOneDiagram(node);
    }

    // ---------- Editor tab "Code": seluruh dokumen markdown ----------
    function syncCodeEditor() {
        $('#markdownEditor').value = state.doc ? state.doc.content : '';
        $('#markdownEditor').readOnly = !state.doc || state.busy;
    }

    // Rename
    const titleInput = $('#docTitle');
    async function saveTitle() {
        const title = titleInput.value.trim();
        if (!state.doc || !title || title === state.doc.title) {
            titleInput.value = state.doc ? state.doc.title : '';
            return;
        }
        try {
            const { document: doc } = await api('api/documents.php', { action: 'update', id: state.doc.id, title });
            state.doc.title = doc.title;
            loadList();
        } catch (err) {
            toast(err.message);
            titleInput.value = state.doc.title;
        }
    }
    titleInput.addEventListener('blur', saveTitle);
    titleInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') titleInput.blur();
        if (e.key === 'Escape') { titleInput.value = state.doc.title; titleInput.blur(); }
    });

    // Manual editing with debounced autosave
    let saveTimer;
    let editVersion = 0;
    $('#markdownEditor').addEventListener('input', (e) => {
        if (!state.doc) return;
        const content = e.target.value;
        state.doc.content = content;
        $('#saveStatus').textContent = 'Menyimpan...';
        clearTimeout(saveTimer);
        const docId = state.doc.id;
        const version = ++editVersion;
        saveTimer = setTimeout(async () => {
            try {
                const data = await api('api/documents.php', { action: 'update', id: docId, content });
                if (!state.doc || state.doc.id !== docId || version !== editVersion) return;
                state.doc.content = data.document.content;
                $('#saveStatus').textContent = 'Tersimpan';
                if (state.view === 'preview') renderPreview();
            } catch (err) {
                if (!state.doc || state.doc.id !== docId || version !== editVersion) return;
                $('#saveStatus').textContent = 'Gagal menyimpan';
                toast(err.message);
            }
        }, 700);
    });

    function setView(view) {
        state.view = view;
        $('#markdownEditor').readOnly = !state.doc || state.busy;
        if (view === 'preview') renderPreview();
        $('#previewDoc').classList.toggle('hidden', view !== 'preview');
        $('#markdownEditor').classList.toggle('hidden', view !== 'markdown');
        $$('#viewTabs button').forEach((b) => {
            const active = b.dataset.view === view;
            b.classList.toggle('bg-white/15', active);
            b.classList.toggle('text-paper', active);
            b.classList.toggle('text-zinc-500', !active);
        });
    }
    $$('#viewTabs button').forEach((b) => b.addEventListener('click', () => setView(b.dataset.view)));

    /** Yang disalin/diunduh: markdown utuh. */
    function exportText() {
        return state.doc.content;
    }

    $('#copyBtn').addEventListener('click', async () => {
        if (!state.doc) return;
        try {
            await navigator.clipboard.writeText(exportText());
            toast('Markdown disalin ke clipboard');
        } catch {
            toast('Gagal menyalin');
        }
    });

    $('#downloadBtn').addEventListener('click', () => {
        if (!state.doc) return;
        const slug = (text) => text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        const name = state.doc.title;
        const blob = new Blob([exportText()], { type: 'text/markdown;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${slug(name) || 'document'}.md`;
        a.click();
        URL.revokeObjectURL(a.href);
    });

    $('#deleteBtn').addEventListener('click', async () => {
        if (!state.doc) return;
        if (!(await vcpConfirm({
            title: 'Hapus dokumen?',
            message: `"${state.doc.title}" beserta seluruh riwayat chatnya akan dihapus permanen.`,
            confirmText: 'Hapus',
        }))) return;
        try {
            await api('api/documents.php', { action: 'delete', id: state.doc.id });
            state.doc = null;
            showWorkspace(false);
            syncUrl();
            loadList();
            toast('Dokumen dihapus');
        } catch (err) {
            toast(err.message);
        }
    });

    // ---------- Chat ----------
    const AVATAR = '<img src="assets/img/bot-hero-head.webp" alt="AI" width="512" height="512" class="h-8 w-8 shrink-0 rounded-full object-cover">';

    /** Bubble assistant untuk teks bawaan aplikasi (bukan balasan AI), HTML-nya dari kode ini sendiri. */
    function botBubble(html) {
        const wrap = document.createElement('div');
        wrap.className = 'flex gap-2.5 sm:gap-3';
        wrap.innerHTML = AVATAR + `<div class="max-w-[calc(100%-44px)] sm:max-w-[85%] break-words rounded-3xl rounded-tl-lg bg-white/10 px-3.5 sm:px-4 py-2.5 sm:py-3 text-sm leading-relaxed">${html}</div>`;
        return wrap;
    }

    function messageEl(msg) {
        const wrap = document.createElement('div');
        if (msg.role === 'user') {
            wrap.className = 'flex justify-end';
            const bubble = document.createElement('div');
            bubble.className = 'max-w-[90%] sm:max-w-[85%] break-words whitespace-pre-wrap rounded-3xl rounded-br-lg bg-paper px-3.5 sm:px-4 py-2.5 sm:py-3 text-sm leading-relaxed text-ink';
            const atts = (msg.attachments || []).map(normalizeAtt);
            const pics = atts.filter((a) => a.kind === 'image');
            const files = atts.filter((a) => a.kind !== 'image');
            if (pics.length) {
                const grid = document.createElement('div');
                grid.className = 'mb-2 flex flex-wrap justify-end gap-2';
                pics.forEach((a) => {
                    const link = document.createElement('a');
                    link.href = a.url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    const pic = document.createElement('img');
                    pic.src = a.url;
                    pic.alt = a.name;
                    pic.className = 'h-20 w-20 rounded-lg border border-ink/10 object-cover';
                    link.appendChild(pic);
                    grid.appendChild(link);
                });
                bubble.appendChild(grid);
            }
            if (files.length) {
                const row = document.createElement('div');
                row.className = (pics.length ? 'mb-2 ' : '') + 'flex flex-wrap justify-end gap-2';
                files.forEach((a) => row.appendChild(fileChip(a, false)));
                bubble.appendChild(row);
            }
            if (msg.content) bubble.appendChild(document.createTextNode(msg.content));
            wrap.appendChild(bubble);
        } else {
            wrap.className = 'flex gap-2.5 sm:gap-3';
            wrap.innerHTML = AVATAR + `<div class="prose prose-sm prose-invert max-w-[calc(100%-44px)] sm:max-w-[85%] break-words overflow-hidden rounded-3xl rounded-tl-lg bg-white/10 px-3.5 sm:px-4 py-2.5 sm:py-3 leading-relaxed prose-p:my-1 prose-code:before:content-none prose-code:after:content-none ${msg.error ? 'text-red-400' : ''}">${msg.typing ? '<div class="typing flex gap-1 py-1.5"><span class="h-2 w-2 rounded-full bg-zinc-500"></span><span class="h-2 w-2 rounded-full bg-zinc-500"></span><span class="h-2 w-2 rounded-full bg-zinc-500"></span></div>' : md(msg.content)}</div>`;
        }
        return wrap;
    }

    function renderMessages() {
        const box = $('#messages');
        box.innerHTML = '';
        if (!state.messages.length) {
            const label = currentTypeLabel() || boot.types[currentType()];
            box.appendChild(botBubble(`Halo! Ceritakan ide produkmu — siapa penggunanya, masalah apa yang diselesaikan, dan fitur utamanya. Aku akan menyusun dokumen <b>${label}</b> lengkap di panel preview.`));
            if (currentType() === 'design') {
                box.appendChild(botBubble('Dokumen Design jauh lebih akurat kalau kamu lampirkan <b>1-3 gambar referensi UI</b> — klik ikon penjepit di bawah, atau langsung tempel (<b>Ctrl+V</b>) screenshot. Warna, tipografi, dan spacing dari gambar itu akan kupakai saat menulis dokumen.'));
            }
        }
        state.messages.forEach((m) => box.appendChild(messageEl(m)));
        box.scrollTop = box.scrollHeight;
        renderSuggestions();
    }

    function renderSuggestions() {
        const wrap = $('#suggestions');
        wrap.innerHTML = '';
        if (state.busy || wiz.on) return;
        const type = currentType();
        if (!type) return;
        const hasContent = state.doc && state.doc.content.trim();
        const items = hasContent ? REVISIONS : SUGGESTIONS[type];
        for (const text of items) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rounded-full border border-white/15 px-3 py-1.5 text-xs text-zinc-400 transition hover:border-white/50 hover:text-paper';
            b.textContent = text;
            b.addEventListener('click', () => sendMessage(text));
            wrap.appendChild(b);
        }
    }

    // ---------- Wizard PRD: kategori & tech stack ditanya dulu, baru PRD ditulis ----------
    const PRD_UNKNOWN = 'Belum tahu, rekomendasikan saja';

    const PRD_QUESTIONS = [
        {
            key: 'Kategori aplikasi',
            ask: () => 'Kategori aplikasinya apa?',
            options: () => ['Mobile', 'Web', 'Desktop', 'Web + Mobile', 'API / backend saja', PRD_UNKNOWN],
        },
        {
            key: 'Teknologi frontend',
            ask: (a) => a['Kategori aplikasi'] === 'Desktop'
                ? 'Teknologi desktop apa yang dipakai?'
                : 'Frontend-nya pakai apa?',
            show: (a) => a['Kategori aplikasi'] !== 'API / backend saja',
            options: (a) => {
                const k = a['Kategori aplikasi'];
                if (k === 'Mobile') return ['Native Android (Kotlin)', 'Native iOS (Swift)', 'Flutter', 'React Native', PRD_UNKNOWN];
                if (k === 'Desktop') return ['Electron (JS/TS)', 'Tauri (Rust)', '.NET / C#', 'Java', PRD_UNKNOWN];
                if (k === 'Web + Mobile') return ['Flutter (mobile + web)', 'React Native + React web', 'Next.js + PWA', 'Native terpisah per platform', PRD_UNKNOWN];
                return ['Next.js (React)', 'Vue / Nuxt', 'Laravel Blade + Alpine', 'HTML + CSS + JS tanpa framework', PRD_UNKNOWN];
            },
        },
        {
            key: 'Bahasa pemrograman',
            ask: () => 'Bahasa pemrograman yang ingin dipakai?',
            options: () => ['PHP', 'JavaScript / TypeScript', 'Dart', 'Kotlin', 'Swift', 'Python', 'Go', PRD_UNKNOWN],
        },
        {
            key: 'Perlu backend',
            ask: () => 'Aplikasinya butuh backend, atau cukup client-only?',
            options: () => ['Ya, butuh backend', 'Tidak, tanpa backend (client-only)', PRD_UNKNOWN],
        },
        {
            key: 'Backend',
            ask: () => 'Backend-nya pakai apa?',
            show: (a) => a['Perlu backend'] === 'Ya, butuh backend',
            options: () => ['Backend custom (dibangun sendiri)', 'BaaS (Firebase / Supabase)', PRD_UNKNOWN],
        },
        {
            key: 'Framework backend',
            ask: () => 'Pakai framework backend, atau tanpa framework?',
            show: (a) => a['Backend'] === 'Backend custom (dibangun sendiri)',
            options: () => ['Laravel', 'CodeIgniter', 'Express (Node.js)', 'NestJS', 'Symfony', 'FastAPI (Python)', 'Tanpa framework backend', PRD_UNKNOWN],
        },
        {
            key: 'Perlu database',
            ask: () => 'Butuh database untuk menyimpan data?',
            options: () => ['Ya, butuh database', 'Tidak, tanpa database', PRD_UNKNOWN],
        },
        {
            key: 'Database',
            ask: () => 'Databasenya mau pakai apa?',
            show: (a) => a['Perlu database'] === 'Ya, butuh database',
            options: () => ['MySQL / MariaDB', 'PostgreSQL', 'SQLite', 'MongoDB', 'Firebase Firestore', PRD_UNKNOWN],
        },
    ];

    const wiz = { on: false, idea: '', answers: {}, step: 0, card: null };
    const wizVisible = () => PRD_QUESTIONS.filter((q) => !q.show || q.show(wiz.answers));

    function wizStart(idea) {
        wiz.on = true;
        wiz.idea = idea;
        wiz.answers = {};
        wiz.step = 0;
        $('#suggestions').innerHTML = '';
        wizRender();
    }

    function wizCancel() {
        if (!wiz.on && !wiz.card) return;
        wiz.on = false;
        wiz.answers = {};
        wiz.step = 0;
        if (wiz.card) { wiz.card.remove(); wiz.card = null; }
        renderSuggestions();
    }

    function wizChip(label, onClick, selected) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'rounded-full border px-3.5 py-2 text-xs font-medium transition '
            + (selected
                ? 'border-brand-400 bg-brand-400/15 text-brand-300'
                : 'border-white/15 bg-white/5 text-zinc-200 hover:border-brand-400 hover:bg-brand-400/10 hover:text-brand-300');
        b.textContent = label;
        b.addEventListener('click', onClick);
        return b;
    }

    function wizRender() {
        const vis = wizVisible();
        const q = vis[wiz.step];
        if (!q) { wizFinish(); return; }

        if (!wiz.card) {
            wiz.card = botBubble(`<div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-300" data-wiz-step></p>
                    <p class="text-xs text-zinc-400" data-wiz-idea></p>
                    <p class="text-sm font-semibold text-paper" data-wiz-ask></p>
                    <div class="flex flex-wrap gap-2" data-wiz-opts></div>
                    <div class="flex flex-wrap gap-1.5" data-wiz-done></div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="button" data-wiz-back class="rounded-full border border-white/15 px-3 py-1.5 text-xs text-zinc-400 transition hover:border-white/50 hover:text-paper">Kembali</button>
                        <button type="button" data-wiz-skip class="rounded-full border border-white/15 px-3 py-1.5 text-xs text-zinc-400 transition hover:border-white/50 hover:text-paper">Lewati, langsung buatkan PRD</button>
                    </div>
                </div>`);
            wiz.card.querySelector('[data-wiz-back]').addEventListener('click', () => {
                if (wiz.step > 0) { wiz.step--; wizRender(); }
            });
            wiz.card.querySelector('[data-wiz-skip]').addEventListener('click', wizSkip);
            $('#messages').appendChild(wiz.card);
        }

        const chosen = wiz.answers[q.key];
        wiz.card.querySelector('[data-wiz-step]').textContent = `Langkah ${wiz.step + 1} dari ${vis.length}`;
        wiz.card.querySelector('[data-wiz-idea]').textContent = 'Ide: ' + wiz.idea;
        wiz.card.querySelector('[data-wiz-ask]').textContent = q.ask(wiz.answers);

        const opts = wiz.card.querySelector('[data-wiz-opts]');
        opts.innerHTML = '';
        q.options(wiz.answers).forEach((label) => {
            opts.appendChild(wizChip(label, () => {
                wiz.answers[q.key] = label;
                wiz.step++;
                wizRender();
            }, label === chosen));
        });

        const done = wiz.card.querySelector('[data-wiz-done]');
        done.innerHTML = '';
        for (const p of PRD_QUESTIONS) {
            if (!wiz.answers[p.key]) continue;
            const s = document.createElement('span');
            s.className = 'rounded-full border border-brand-400/30 bg-brand-400/10 px-2.5 py-1 text-[11px] text-brand-300';
            s.textContent = `${p.key}: ${wiz.answers[p.key]}`;
            done.appendChild(s);
        }
        wiz.card.querySelector('[data-wiz-back]').classList.toggle('hidden', wiz.step === 0);
        $('#messages').scrollTop = $('#messages').scrollHeight;
    }

    function wizPromptText() {
        const lines = [];
        for (const q of PRD_QUESTIONS) {
            const v = wiz.answers[q.key];
            if (!v) continue;
            lines.push(`- ${q.key}: ${v === PRD_UNKNOWN ? 'belum tahu (tolong rekomendasikan beserta alasannya)' : v}`);
        }
        let text = 'Buatkan PRD lengkap untuk ide aplikasi berikut.\n\nIde: ' + wiz.idea;
        if (lines.length) {
            text += '\n\nPilihan platform & teknologi:\n' + lines.join('\n')
                + '\n\nPakai pilihan di atas sebagai keputusan resmi saat menulis dokumen (bab teknologi/architecture, model data, dan milestone).';
        }
        return text;
    }

    function wizClose() {
        wiz.on = false;
        wiz.answers = {};
        wiz.step = 0;
        if (wiz.card) { wiz.card.remove(); wiz.card = null; }
    }

    function wizFinish() {
        const text = wizPromptText();
        wizClose();
        sendMessage(text, { noWizard: true });
    }

    function wizSkip() {
        const idea = wiz.idea;
        wizClose();
        sendMessage(idea, { noWizard: true });
    }

    const input = $('#chatInput');
    function autoresize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }
    input.addEventListener('input', autoresize);
    // Placeholder panjang memaksa 2 baris di kolom sempit, dan textarea rows=1 lalu menampilkan
    // scrollbar (panah) yang bikin composer kelihatan rusak. Jadi placeholder ikut lebar kolom.
    const PH_WIDE = 'Ceritakan idemu atau minta revisi...';
    const PH_SHORT = 'Ceritakan idemu...';
    function syncPlaceholder() {
        input.placeholder = input.offsetWidth < 280 ? PH_SHORT : PH_WIDE;
    }
    window.addEventListener('resize', syncPlaceholder);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            $('#chatForm').requestSubmit();
        }
    });
    $('#chatForm').addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage(input.value);
    });

    function setBusy(busy) {
        state.busy = busy;
        $('#sendBtn').disabled = busy;
        input.disabled = busy;
    }

    // ---------- Lampiran: gambar referensi + file ----------
    const MAX_IMAGES = 3;
    const MAX_FILES = 5;
    const ACCEPTED_EXT = /\.(png|jpe?g|webp|gif|bmp|txt|md|markdown|csv|log|json|pdf|docx)$/i;
    const FILE_ICON = {
        text: '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 3v5h5"/><path d="M19 21H5V3h9l5 5v13Z"/><path d="M9 13h6M9 17h6"/></svg>',
        doc: '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 3v5h5"/><path d="M19 21H5V3h9l5 5v13Z"/></svg>',
    };

    /** Baris lama di database menyimpan lampiran sebagai array of URL gambar. */
    function normalizeAtt(a) {
        if (typeof a === 'string') return { url: a, name: a.split('/').pop(), kind: 'image' };
        return { url: a.url, name: a.name || a.url.split('/').pop(), kind: a.kind || 'image' };
    }

    async function uploadFile(file) {
        const form = new FormData();
        form.append('file', file);
        const res = await fetch('api/upload.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': boot.csrf },
            body: form,
        });
        if (res.status === 401) {
            location.href = 'login.php';
            throw new Error('Unauthorized');
        }
        const data = await res.json().catch(() => ({ error: 'Respons server tidak valid.' }));
        if (!res.ok) throw new Error(data.error || 'Upload gagal.');
        // Respons mentah tetap dipakai (palette dots butuh `facts`), cuma field wajibnya dinormalisasi.
        return Object.assign(data, normalizeAtt(data));
    }

    async function addFiles(files) {
        const list = Array.from(files || []);
        const btn = $('#attachBtn');
        for (const file of list) {
            const name = file.name || 'pasted-image.png';
            if (!ACCEPTED_EXT.test(name) && !/^image\//.test(file.type)) {
                toast(`"${name}" dilewati: format tidak didukung.`);
                continue;
            }
            const isImg = /^image\//.test(file.type) || /\.(png|jpe?g|webp|gif|bmp)$/i.test(name);
            if (state.pending.length >= MAX_FILES) {
                toast(`Maksimal ${MAX_FILES} lampiran per pesan.`);
                break;
            }
            const images = state.pending.filter((p) => p.kind === 'image').length;
            if (isImg && images >= MAX_IMAGES) {
                toast(`Maksimal ${MAX_IMAGES} gambar per pesan.`);
                continue;
            }
            btn.disabled = true;
            try {
                state.pending.push(await uploadFile(file));
            } catch (err) {
                toast(err.message);
            } finally {
                btn.disabled = false;
            }
        }
        renderAttachments();
    }

    function imageCell(att, onRemove) {
        const cell = document.createElement('div');
        cell.className = 'relative overflow-hidden rounded-xl border border-white/10 bg-white/5 p-1';

        const thumb = document.createElement('img');
        thumb.src = att.url;
        thumb.alt = att.name;
        thumb.title = att.name;
        thumb.className = 'h-16 w-16 rounded-lg object-cover';
        cell.appendChild(thumb);

        const dots = document.createElement('div');
        dots.className = 'mt-1 flex justify-center gap-1';
        ((att.facts && att.facts.palette) || []).slice(0, 4).forEach((c) => {
            const dot = document.createElement('span');
            dot.className = 'h-2 w-2 rounded-full border border-white/20';
            dot.style.background = c.hex;
            dot.title = `${c.hex} — ${c.share}% dari pixel gambar`;
            dots.appendChild(dot);
        });
        cell.appendChild(dots);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'absolute right-0 top-0 flex h-5 w-5 items-center justify-center rounded-bl-lg bg-black/70 text-zinc-300 transition hover:bg-red-500 hover:text-paper';
        remove.setAttribute('aria-label', 'Hapus gambar');
        remove.innerHTML = '<svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>';
        remove.addEventListener('click', onRemove);
        cell.appendChild(remove);

        return cell;
    }

    function fileChip(att, dark, onRemove) {
        const chip = document.createElement('div');
        chip.className = 'flex max-w-[230px] items-center gap-1 rounded-xl border px-2 py-1.5 '
            + (dark ? 'border-white/10 bg-white/5' : 'border-ink/10 bg-ink/5');

        const link = document.createElement('a');
        link.href = att.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.title = att.name;
        link.className = 'flex min-w-0 items-center gap-1.5 text-xs '
            + (dark ? 'text-zinc-300 hover:text-paper' : 'text-ink hover:underline');
        const icon = document.createElement('span');
        icon.className = 'shrink-0 opacity-70';
        icon.innerHTML = FILE_ICON[att.kind] || FILE_ICON.doc;
        link.appendChild(icon);
        const label = document.createElement('span');
        label.className = 'truncate';
        label.textContent = att.name;
        link.appendChild(label);
        chip.appendChild(link);

        if (onRemove) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'shrink-0 text-zinc-500 transition hover:text-red-400';
            remove.setAttribute('aria-label', 'Hapus lampiran');
            remove.innerHTML = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>';
            remove.addEventListener('click', onRemove);
            chip.appendChild(remove);
        }

        return chip;
    }

    function renderAttachments() {
        const strip = $('#attachStrip');
        strip.innerHTML = '';
        strip.classList.toggle('hidden', !state.pending.length);
        state.pending.forEach((att, i) => {
            const remove = () => {
                state.pending.splice(i, 1);
                renderAttachments();
            };
            strip.appendChild(att.kind === 'image' ? imageCell(att, remove) : fileChip(att, true, remove));
        });
    }

    $('#attachBtn').addEventListener('click', () => $('#attachInput').click());
    $('#attachInput').addEventListener('change', (e) => {
        addFiles(e.target.files);
        e.target.value = '';
    });
    input.addEventListener('paste', (e) => {
        const files = Array.from((e.clipboardData && e.clipboardData.files) || []);
        if (files.length) {
            e.preventDefault();
            addFiles(files);
        }
    });

    // Seret file ke mana saja di jendela aplikasi, bukan cuma ke kolom chat.
    const dropOverlay = $('#dropOverlay');
    let dragDepth = 0;
    const dropsFile = (e) => Array.from((e.dataTransfer && e.dataTransfer.types) || []).includes('Files');
    const canAttach = () => !!(state.doc || state.draftType);

    window.addEventListener('dragenter', (e) => {
        if (!dropsFile(e) || !canAttach()) return;
        e.preventDefault();
        dragDepth++;
        dropOverlay.classList.remove('hidden');
    });
    window.addEventListener('dragover', (e) => {
        if (dropsFile(e) && canAttach()) e.preventDefault();
    });
    window.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (!dragDepth) dropOverlay.classList.add('hidden');
    });
    window.addEventListener('drop', (e) => {
        if (!dropsFile(e) || !canAttach()) return;
        e.preventDefault();
        dragDepth = 0;
        dropOverlay.classList.add('hidden');
        addFiles(e.dataTransfer.files);
    });

    async function sendMessage(text, opts = {}) {
        text = text.trim();
        const startDocId = state.doc ? state.doc.id : null;
        const draftType = state.draftType;
        const attachments = state.pending.map((p) => ({ url: p.url, name: p.name, kind: p.kind }));
        // Prompt boleh kosong kalau ada lampiran (mis. tinggal seret screenshot lalu kirim).
        if (state.busy || (!startDocId && !draftType) || (!text && !attachments.length)) return;
        // Ide pertama di chat PRD: jangan langsung ditulis — tanyakan kategori & tech stack
        // dulu supaya dokumennya rinci dan user tidak bingung harus menulis apa.
        if (wiz.on) wizCancel();
        if (!opts.noWizard && !startDocId && draftType === 'prd' && text) {
            input.value = '';
            autoresize();
            wizStart(text);
            return;
        }
        // Draft: belum ada dokumen di DB, identitas chat ini masih tipenya,
        // dan baru Known setelah chat.php menyimpan dokumen pesan pertama.
        let currentId = startDocId;
        const isCurrent = () => currentId
            ? (state.doc && state.doc.id === currentId)
            : (!state.doc && state.draftType === draftType);
        input.value = '';
        autoresize();
        setBusy(true);
        // Izin notifikasi diminta sekali dari klik kirim (butuh gesture user).
        if (window.isSecureContext && 'Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }

        state.messages.push({ role: 'user', content: text, attachments });
        renderMessages();
        const box = $('#messages');
        const typing = messageEl({ role: 'assistant', typing: true });
        box.appendChild(typing);
        box.scrollTop = box.scrollHeight;

        try {
            const data = await api('api/chat.php', currentId
                ? { document_id: currentId, message: text, attachments }
                : { type: draftType, message: text, attachments });
            if (!isCurrent()) {
                notifyDone(true, data.document.title, (data.reply || '').replace(/\s+/g, ' ').slice(0, 90));
                loadList();
                return;
            }
            state.pending = [];
            renderAttachments();
            currentId = data.document.id;
            state.messages.push({ role: 'assistant', content: data.reply });
            state.doc = data.document;
            state.draftType = null;
            $('#docTitle').value = state.doc.title;
            renderPreview();
            syncCodeEditor();
            if (state.pane !== 'preview') {
                $('#mobilePreviewDot')?.classList.remove('hidden');
            }
            syncUrl();
            loadList();
            notifyDone(true, state.doc.title, data.reply.replace(/\s+/g, ' ').slice(0, 90));
        } catch (err) {
            if (isCurrent()) {
                state.messages.pop();
                input.value = text;
                state.messages.push({ role: 'assistant', content: err.message, error: true });
            }
            notifyDone(false, (state.doc && state.doc.title) || 'chat baru', err.message.slice(0, 80));
        } finally {
            setBusy(false);
            if (isCurrent()) {
                renderMessages();
                input.focus();
            }
        }
    }

    // ---------- History ----------
    const hist = { q: '', type: '', offset: 0, limit: 50, total: 0, items: [] };
    let histLoading = false;

    function showHistory(show) {
        state.screen = show ? 'history' : 'chat';
        $('#historyView').classList.toggle('hidden', !show);
        $('#historyView').classList.toggle('flex', show);
        if (show) {
            $('#workspace').classList.add('hidden');
            $('#workspace').classList.remove('flex');
            $('#emptyState').classList.add('hidden');
            $('#emptyState').classList.remove('flex');
            loadHistory(true);
        } else {
            showWorkspace(!!(state.doc || state.draftType));
        }
        syncUrl();
    }

    function historyDayLabel(ts) {
        const d = new Date(ts.replace(' ', 'T'));
        const startOfDay = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
        const days = Math.round((startOfDay(new Date()) - startOfDay(d)) / 86400000);
        if (days <= 0) return 'Hari ini';
        if (days === 1) return 'Kemarin';
        if (days < 7) return '7 hari terakhir';
        if (days < 30) return '30 hari terakhir';
        return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }

    const historyDate = (ts) => new Date(ts.replace(' ', 'T'))
        .toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

    /** Isi teks + sorotan kata yang dicari, dibuat lewat node supaya aman dari HTML di judul/pesan. */
    function markText(el, text, needle) {
        if (!needle) {
            el.textContent = text;
            return;
        }
        const re = new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
        let last = 0;
        for (const m of text.matchAll(re)) {
            if (m.index > last) el.appendChild(document.createTextNode(text.slice(last, m.index)));
            const mark = document.createElement('mark');
            mark.textContent = m[0];
            el.appendChild(mark);
            last = m.index + m[0].length;
        }
        el.appendChild(document.createTextNode(text.slice(last)));
    }

    async function loadHistory(reset) {
        if (histLoading) return;
        histLoading = true;
        if (reset) {
            hist.offset = 0;
            hist.items = [];
        }
        const params = new URLSearchParams({ offset: String(hist.offset), limit: String(hist.limit) });
        if (hist.q) params.set('q', hist.q);
        if (hist.type) params.set('type', hist.type);
        try {
            const data = await api('api/history.php?' + params);
            hist.total = data.total;
            hist.items = hist.items.concat(data.items);
            renderHistory();
        } catch (err) {
            toast(err.message);
        } finally {
            histLoading = false;
        }
    }

    function renderHistoryFilters() {
        const wrap = $('#historyFilters');
        wrap.innerHTML = '';
        const total = Object.values(state.counts).reduce((a, b) => a + Number(b), 0);
        const chips = [['', 'Semua', total], ['planning', 'Planning', state.counts.planning || 0], ['design', 'Design', state.counts.design || 0], ['prd', 'PRD', state.counts.prd || 0]];
        for (const [key, label, count] of chips) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rounded-full border px-4 py-1.5 text-sm transition ' +
                (hist.type === key ? 'border-brand-400/60 bg-brand-400/15 text-brand-300' : 'border-white/10 text-zinc-400 hover:border-white/30 hover:text-paper');
            b.textContent = `${label} ${count}`;
            b.addEventListener('click', () => {
                if (hist.type === key) return;
                hist.type = key;
                loadHistory(true);
            });
            wrap.appendChild(b);
        }
    }

    function historyCard(item) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'flex w-full items-start gap-3 rounded-2xl border border-white/10 bg-panel px-4 py-3.5 text-left transition hover:border-brand-400/40 hover:bg-white/5';

        const dot = document.createElement('span');
        dot.className = `type-dot type-${item.type} mt-2 shrink-0`;
        btn.appendChild(dot);

        const body = document.createElement('div');
        body.className = 'min-w-0 flex-1';

        const title = document.createElement('p');
        title.className = 'truncate text-sm font-medium text-paper';
        markText(title, item.title, hist.q);
        if (Number(item.pinned)) {
            const pin = document.createElement('span');
            pin.className = 'ml-2 align-middle text-[10px] uppercase tracking-wider text-brand-300';
            pin.textContent = 'pinned';
            title.appendChild(pin);
        }

        const meta = document.createElement('p');
        meta.className = 'mt-0.5 text-xs text-zinc-500';
        const typeLabel = boot.types[item.type] || item.type;
        meta.textContent = `${typeLabel} · ${item.message_count} pesan · ${historyDate(item.updated_at)}`;

        body.appendChild(title);
        body.appendChild(meta);
        if (item.snippet) {
            const snip = document.createElement('p');
            snip.className = 'mt-2 line-clamp-2 text-xs leading-relaxed text-zinc-400';
            markText(snip, item.snippet, hist.q);
            body.appendChild(snip);
        }
        btn.appendChild(body);

        const arrow = document.createElement('span');
        arrow.className = 'shrink-0 translate-y-1 text-zinc-500';
        arrow.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>';
        btn.appendChild(arrow);

        btn.addEventListener('click', () => {
            showHistory(false);
            openDoc(item.id);
        });
        return btn;
    }

    function renderHistory() {
        renderHistoryFilters();
        $('#historyTotal').textContent = `${hist.total} chat`;
        const wrap = $('#historyGroups');
        wrap.innerHTML = '';

        if (!hist.items.length) {
            wrap.innerHTML = `<div class="rounded-2xl border border-dashed border-white/10 px-6 py-16 text-center text-sm text-zinc-500">
                ${hist.q ? 'Tidak ada chat yang memuat kata itu.' : 'Belum ada chat. Mulai dari New Chat.'}</div>`;
            $('#historyMore').innerHTML = '';
            return;
        }

        let lastLabel = null;
        let list = null;
        for (const item of hist.items) {
            const label = historyDayLabel(item.updated_at);
            if (label !== lastLabel) {
                const group = document.createElement('div');
                const head = document.createElement('p');
                head.className = 'mb-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500';
                head.textContent = label;
                list = document.createElement('div');
                list.className = 'space-y-2';
                group.appendChild(head);
                group.appendChild(list);
                wrap.appendChild(group);
                lastLabel = label;
            }
            list.appendChild(historyCard(item));
        }

        const more = $('#historyMore');
        more.innerHTML = '';
        if (hist.items.length < hist.total) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rounded-full border border-white/15 px-5 py-2 text-sm text-zinc-300 transition hover:border-brand-400/60 hover:text-paper';
            b.textContent = `Muat ${Math.min(hist.limit, hist.total - hist.items.length)} chat lagi`;
            b.addEventListener('click', () => {
                hist.offset = hist.items.length;
                loadHistory(false);
            });
            more.appendChild(b);
        }
    }

    $('#historyBtn').addEventListener('click', () => {
        showHistory(true);
        closeSidebar();
    });
    $('#historyClose').addEventListener('click', () => showHistory(false));
    $('#historyCloseDesktop')?.addEventListener('click', () => showHistory(false));
    let histTimer;
    $('#historySearch').addEventListener('input', (e) => {
        clearTimeout(histTimer);
        histTimer = setTimeout(() => {
            hist.q = e.target.value.trim();
            loadHistory(true);
        }, 300);
    });

    // ---------- Mobile panes ----------
    function setPane(pane) {
        state.pane = pane;
        if (pane === 'preview') {
            $('#mobilePreviewDot')?.classList.add('hidden');
        }
        $('#chatPane').classList.toggle('hidden', pane !== 'chat');
        $('#chatPane').classList.toggle('flex', pane === 'chat');
        $('#previewPane').classList.toggle('hidden', pane !== 'preview');
        $('#previewPane').classList.toggle('flex', pane === 'preview');
        $('#chatPane').classList.add('lg:flex');
        $('#previewPane').classList.add('lg:flex');
        $$('#mobileTabs button').forEach((b) => {
            const active = b.dataset.pane === pane;
            b.classList.toggle('bg-white/15', active);
            b.classList.toggle('text-paper', active);
            b.classList.toggle('text-zinc-500', !active);
        });
        // Lebar kolom composer baru diketahui setelah pane ditampilkan, jadi placeholder
        // disinkronkan di sini (bukan hanya saat boot, saat #workspace masih hidden).
        syncPlaceholder();
    }
    $$('#mobileTabs button').forEach((b) => b.addEventListener('click', () => setPane(b.dataset.pane)));

    // ---------- Chat/preview split width (drag handle) ----------
    // Preview selalu melebar sampai tepi kanan layar; handle mengatur lebar chat.
    const CHAT_W_DEFAULT = 460;
    const CHAT_W_MIN = 320;
    const PREVIEW_W_MIN = 420;
    const SIDEBAR_W = 288;
    const SIDEBAR_RAIL_W = 76;
    const workspace = $('#workspace');
    const resizer = $('#previewResizer');

    const isDesktop = () => window.innerWidth >= 1024;
    const sidebarW = () => (isDesktop() && ui.sidebar ? SIDEBAR_RAIL_W : SIDEBAR_W);
    const chatWMax = () => Math.max(
        CHAT_W_MIN,
        window.innerWidth - sidebarW() - (isDesktop() && ui.preview ? 16 : PREVIEW_W_MIN) - 8,
    );
    const applyChatWidth = (w) => workspace.style.setProperty('--chat-w', `${Math.round(w)}px`);
    const clampChatWidth = () => {
        if (isDesktop() && $('#chatPane').getBoundingClientRect().width > chatWMax()) applyChatWidth(chatWMax());
    };
    const savedW = Number(localStorage.getItem('vcp.chatWidth'));
    if (savedW >= CHAT_W_MIN) applyChatWidth(Math.min(savedW, chatWMax()));

    let dragStartX = null;
    let dragStartW = 0;
    resizer.addEventListener('pointerdown', (e) => {
        dragStartX = e.clientX;
        dragStartW = $('#chatPane').getBoundingClientRect().width;
        resizer.setPointerCapture(e.pointerId);
        document.body.classList.add('select-none');
        document.body.style.cursor = 'col-resize';
    });
    resizer.addEventListener('pointermove', (e) => {
        if (dragStartX === null) return;
        applyChatWidth(Math.min(chatWMax(), Math.max(CHAT_W_MIN, dragStartW + (e.clientX - dragStartX))));
    });
    const endChatDrag = () => {
        if (dragStartX === null) return;
        dragStartX = null;
        document.body.classList.remove('select-none');
        document.body.style.cursor = '';
        localStorage.setItem('vcp.chatWidth', String(Math.round($('#chatPane').getBoundingClientRect().width)));
    };
    resizer.addEventListener('pointerup', endChatDrag);
    resizer.addEventListener('pointercancel', endChatDrag);
    resizer.addEventListener('dblclick', () => {
        const w = Math.min(CHAT_W_DEFAULT, chatWMax());
        applyChatWidth(w);
        localStorage.setItem('vcp.chatWidth', String(w));
    });
    window.addEventListener('resize', clampChatWidth);

    // ---------- Boot ----------
    (async () => {
        applyUiFlags();
        setView('preview');
        setPane('chat');
        syncPlaceholder();
        await loadList();
        if (boot.view === 'history') {
            showHistory(true);
        } else if (boot.prompt) {
            startDraftChat(boot.promptType);
            sendMessage(boot.prompt);
        } else if (boot.newType) {
            startDraftChat(boot.newType);
        } else if (boot.docId) {
            await openDoc(boot.docId);
        }
    })();
})();
