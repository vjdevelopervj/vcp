<?php
/**
 * Tombol login Google / GitHub. Provider yang credentials-nya belum diisi di config.php
 * tetap ditampilkan supaya opsi sosialnya terlihat, tapi dalam keadaan nonaktif.
 *
 * @var string $next
 */
$oauthNext = safe_next($next ?? null);
?>
<div class="mt-6 flex items-center gap-4 text-[11px] uppercase tracking-[0.2em] text-zinc-500">
    <span class="h-px flex-1 bg-white/10"></span>
    <span>atau lanjut dengan</span>
    <span class="h-px flex-1 bg-white/10"></span>
</div>
<div class="mt-4 flex items-center justify-center gap-3">
    <?php foreach (oauth_providers() as $oauthKey => $oauthProvider): ?>
        <?php if (oauth_configured($oauthKey)): ?>
            <a href="auth/oauth.php?provider=<?= e($oauthKey) ?>&next=<?= urlencode($oauthNext) ?>"
               title="Lanjut dengan <?= e($oauthProvider['label']) ?>" aria-label="Lanjut dengan <?= e($oauthProvider['label']) ?>"
               class="flex h-12 w-[74px] items-center justify-center rounded-xl bg-paper text-ink shadow-soft transition hover:bg-brand-300">
                <?= oauth_icon($oauthKey) ?>
            </a>
        <?php else: ?>
            <button type="button" disabled title="<?= e($oauthProvider['label']) ?> belum dikonfigurasi di config.php"
                    aria-label="<?= e($oauthProvider['label']) ?> belum tersedia"
                    class="flex h-12 w-[74px] cursor-not-allowed items-center justify-center rounded-xl border border-white/15 bg-white/5 text-zinc-600">
                <span class="opacity-60"><?= oauth_icon($oauthKey) ?></span>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php if (!oauth_available()): ?>
    <p class="mt-3 text-center text-xs text-zinc-500">Login sosial belum tersedia saat ini — silakan lanjut dengan email.</p>
<?php endif; ?>
