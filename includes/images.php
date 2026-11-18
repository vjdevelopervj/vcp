<?php
/**
 * Ekstraksi fakta visual dari file gambar yang diunggah user.
 * Model text-only (nemotron thinking-off) tidak bisa melihat gambar, jadi angka
 * inilah yang dikirim ke prompt supaya analisisnya spesifik dan tidak mengarang.
 */

/**
 * @return array{width:int,height:int,ratio:string,tone:string,accent:string,palette:array<int, array{hex:string,share:float}>}|null
 */
function image_facts(string $absPath): ?array
{
    if (!is_file($absPath)) {
        return null;
    }
    $img = @imagecreatefromstring((string) file_get_contents($absPath));
    if (!$img) {
        return null;
    }
    $facts = image_facts_from_resource($img);
    imagedestroy($img);

    return $facts;
}

/** @return array{width:int,height:int,ratio:string,tone:string,accent:string,palette:array<int, array{hex:string,share:float}>} */
function image_facts_from_resource(GdImage $img): array
{
    $width = imagesx($img);
    $height = imagesy($img);
    $palette = image_palette($img);
    $luminance = image_mean_luminance($img);

    return [
        'width' => $width,
        'height' => $height,
        'ratio' => image_ratio_label($width, $height),
        'tone' => $luminance < 80 ? 'gelap (dark UI)' : ($luminance > 175 ? 'terang (light UI)' : 'menengah'),
        'accent' => image_accent_hex($palette) ?? ($palette[0]['hex'] ?? '#000000'),
        'palette' => $palette,
    ];
}

/**
 * Sampel pixel (dibatasi ~20k titik), dibucket ke 4 bit per channel, lalu bucket
 * yang warnanya berdekatan digabung supaya hex yang dilaporkan benar-benar mewakili gambar.
 *
 * @return array<int, array{hex:string,share:float}>
 */
function image_palette(GdImage $img, int $limit = 6): array
{
    $width = imagesx($img);
    $height = imagesy($img);
    $step = max(1, (int) floor(sqrt(($width * $height) / 20000)));

    $buckets = [];
    $sampled = 0;
    for ($y = 0; $y < $height; $y += $step) {
        for ($x = 0; $x < $width; $x += $step) {
            $color = imagecolorat($img, $x, $y);
            $alpha = ($color >> 24) & 0x7F;
            if ($alpha > 90) {
                continue; // hampir transparan, bukan bagian dari tampilan
            }
            $r = ($color >> 16) & 0xFF;
            $g = ($color >> 8) & 0xFF;
            $b = $color & 0xFF;
            $key = (($r >> 4) << 8) | (($g >> 4) << 4) | ($b >> 4);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [0, 0, 0, 0];
            }
            $buckets[$key][0]++;
            $buckets[$key][1] += $r;
            $buckets[$key][2] += $g;
            $buckets[$key][3] += $b;
            $sampled++;
        }
    }
    if ($sampled === 0) {
        return [];
    }

    uasort($buckets, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

    $merged = [];
    foreach ($buckets as $bucket) {
        [$count, $sumR, $sumG, $sumB] = $bucket;
        $r = (int) round($sumR / $count);
        $g = (int) round($sumG / $count);
        $b = (int) round($sumB / $count);

        $hit = null;
        foreach ($merged as $i => $m) {
            if (abs($m['r'] - $r) + abs($m['g'] - $g) + abs($m['b'] - $b) < 30) {
                $hit = $i;
                break;
            }
        }
        if ($hit === null) {
            $merged[] = ['count' => $count, 'r' => $r, 'g' => $g, 'b' => $b];
            continue;
        }
        $total = $merged[$hit]['count'] + $count;
        foreach (['r', 'g', 'b'] as $ch) {
            $incoming = $ch === 'r' ? $r : ($ch === 'g' ? $g : $b);
            $merged[$hit][$ch] = (int) round(($merged[$hit][$ch] * $merged[$hit]['count'] + $incoming * $count) / $total);
        }
        $merged[$hit]['count'] = $total;
    }

    usort($merged, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

    $palette = [];
    foreach (array_slice($merged, 0, $limit) as $m) {
        $palette[] = [
            'hex' => sprintf('#%02X%02X%02X', $m['r'], $m['g'], $m['b']),
            'share' => round($m['count'] / $sampled * 100, 1),
        ];
    }

    return $palette;
}

function image_mean_luminance(GdImage $img): float
{
    $width = imagesx($img);
    $height = imagesy($img);
    $step = max(1, (int) floor(sqrt(($width * $height) / 4000)));
    $sum = 0;
    $count = 0;
    for ($y = 0; $y < $height; $y += $step) {
        for ($x = 0; $x < $width; $x += $step) {
            $color = imagecolorat($img, $x, $y);
            $alpha = ($color >> 24) & 0x7F;
            if ($alpha > 90) {
                continue;
            }
            $sum += 0.2126 * (($color >> 16) & 0xFF) + 0.7152 * (($color >> 8) & 0xFF) + 0.0722 * ($color & 0xFF);
            $count++;
        }
    }

    return $count ? $sum / $count : 0.0;
}

/** Warna paling menyala di palet — kandidat kuat untuk warna CTA/brand. */
function image_accent_hex(array $palette): ?string
{
    $best = null;
    $bestScore = 0.0;
    foreach ($palette as $entry) {
        sscanf($entry['hex'], '#%02X%02X%02X', $r, $g, $b);
        $saturation = (max($r, $g, $b) - min($r, $g, $b)) / 255;
        $score = $saturation * min(1, $entry['share'] / 4);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $entry['hex'];
        }
    }

    return $bestScore > 0.06 ? $best : null;
}

function image_ratio_label(int $width, int $height): string
{
    if (!$width || !$height) {
        return '?';
    }
    $gcd = gcd($width, $height);
    $rw = intdiv($width, $gcd);
    $rh = intdiv($height, $gcd);
    if ($rw > 24 || $rh > 24) {
        return round($width / $height, 2) . ':1';
    }

    return $rw . ':' . $rh;
}

function gcd(int $a, int $b): int
{
    while ($b !== 0) {
        [$a, $b] = [$b, $a % $b];
    }

    return max(1, $a);
}

/**
 * Foto profil: crop tengah jadi persegi, kecilkan ke 256px, simpan sebagai WebP.
 * Nama file selalu acak bikinan server — folder uploads bisa diakses langsung lewat
 * web server, jadi nama dari user tidak pernah dipakai mentah-mentah.
 *
 * @return string|null path relatif (uploads/12/abc.webp) atau null kalau gagal
 */
function image_save_avatar(string $absSrc, int $uid): ?string
{
    $img = @imagecreatefromstring((string) file_get_contents($absSrc));
    if (!$img) {
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $side = min($w, $h);
    $size = 256;

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
    imagedestroy($img);

    $dir = __DIR__ . '/../uploads/' . $uid;
    $rel = 'uploads/' . $uid . '/' . bin2hex(random_bytes(12)) . '.webp';
    $saved = is_dir($dir) || mkdir($dir, 0775, true) ? @imagewebp($out, __DIR__ . '/../' . $rel, 88) : false;
    imagedestroy($out);

    return $saved ? $rel : null;
}

/** Hapus file avatar lama, tapi hanya kalau path-nya memang hasil upload kita sendiri. */
function image_delete_upload(?string $rel): void
{
    if (is_string($rel) && preg_match('#^uploads/(\d+)/([a-f0-9]{24})\.(webp|txt|pdf|docx)$#', $rel, $m)) {
        @unlink(__DIR__ . '/../' . $rel);
    }
}
