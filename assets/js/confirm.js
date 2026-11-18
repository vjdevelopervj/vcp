/**
 * Modal konfirmasi bergaya aplikasi, pengganti confirm() bawaan browser.
 * Pemakaian: await vcpConfirm({ title, message, confirmText }) -> true kalau user setuju.
 */
(() => {
    let el = null;
    let settle = null;

    function build() {
        el = document.createElement('div');
        el.className = 'fixed inset-0 z-[90] hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm';
        el.innerHTML = `
            <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-panel p-6 shadow-soft" role="dialog" aria-modal="true">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-500/15 text-red-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                </div>
                <h3 class="mt-4 text-center text-lg font-semibold" data-title></h3>
                <p class="mt-2 text-center text-sm leading-relaxed text-zinc-400" data-message></p>
                <div class="mt-6 flex gap-3">
                    <button type="button" data-cancel class="flex-1 rounded-full border border-white/15 px-5 py-2.5 text-sm font-medium text-zinc-300 transition hover:border-white/40 hover:text-paper">Batal</button>
                    <button type="button" data-confirm class="flex-1 rounded-full bg-red-500 px-5 py-2.5 text-sm font-semibold text-paper transition hover:bg-red-400">Hapus</button>
                </div>
            </div>`;
        document.body.appendChild(el);

        el.querySelector('[data-cancel]').addEventListener('click', () => settle(false));
        el.querySelector('[data-confirm]').addEventListener('click', () => settle(true));
        el.addEventListener('click', (e) => { if (e.target === el) settle(false); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !el.classList.contains('hidden')) settle(false);
        });
    }

    window.vcpConfirm = function ({ title = 'Yakin?', message = '', confirmText = 'Ya, lanjutkan' } = {}) {
        if (!el) build();
        el.querySelector('[data-title]').textContent = title;
        el.querySelector('[data-message]').textContent = message;
        el.querySelector('[data-confirm]').textContent = confirmText;
        return new Promise((resolve) => {
            settle = (ok) => {
                settle = null;
                el.classList.add('hidden');
                el.classList.remove('flex');
                resolve(ok);
            };
            el.classList.remove('hidden');
            el.classList.add('flex');
            // Fokus ke Batal supaya tekan Enter asal-asalan tidak langsung mengeksekusi aksi destruktif.
            el.querySelector('[data-cancel]').focus();
        });
    };
})();
