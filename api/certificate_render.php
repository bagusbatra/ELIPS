<?php
// Direktori: api/certificate_render.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - pembuat gambar sertifikat (depan) & transkrip (belakang)
// Template : assets/certificate/template-depan.png / template-belakang.png (4500 x 3182)
// Font     : assets/certificate/fonts (DM Sans, Caprasimo)
// Keluaran : JPG, PNG, atau PDF 2 halaman A4 landscape
// ============================================================

require_once __DIR__ . '/qr.php';

const ELIPS_CERT_W = 4500;
const ELIPS_CERT_NAVY = [0, 38, 84];
const ELIPS_CERT_ACCENT = [203, 115, 0];
const ELIPS_CERT_ORANGE = [255, 117, 31];
const ELIPS_CERT_QR = [241, 142, 18];

function elips_cert_dir(): string
{
    return dirname(__DIR__) . '/assets/certificate';
}

function elips_cert_font(string $key): string
{
    static $map = [
        'regular' => 'DMSans_400Regular.ttf',
        'medium' => 'DMSans_500Medium.ttf',
        'semibold' => 'DMSans_600SemiBold.ttf',
        'bold' => 'DMSans_700Bold.ttf',
        'name' => 'Caprasimo_400Regular.ttf',
    ];
    return elips_cert_dir() . '/fonts/' . $map[$key];
}

/** Faktor ukuran GD (poin/piksel) dikalibrasi sekali supaya hasil sama di semua build GD. */
function elips_cert_k(): float
{
    static $k = null;
    if ($k === null) {
        $box = imagettfbbox(100, 0, elips_cert_font('bold'), 'H');
        $h = abs($box[1] - $box[5]);
        $k = $h > 0 ? $h / 70.0 : 1.0; // tinggi huruf kapital DM Sans = 0,70 em
    }
    return $k;
}

function elips_cert_gd_size(float $px): float
{
    return $px / elips_cert_k();
}

/** Lebar maju satu karakter (px) pada ukuran $px. */
function elips_cert_adv(string $ch, string $font, float $px): float
{
    static $cache = [];
    $key = $font . '|' . round($px, 2) . '|' . $ch;
    if (!isset($cache[$key])) {
        $file = elips_cert_font($font);
        $size = elips_cert_gd_size($px);
        $w = static function (string $s) use ($file, $size): float {
            $b = imagettfbbox($size, 0, $file, $s);
            return (float) ($b[2] - $b[0]);
        };
        $cache[$key] = $w('|' . $ch . '|') - $w('||');
    }
    return $cache[$key];
}

function elips_cert_chars(string $text): array
{
    return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function elips_cert_text_w(string $text, string $font, float $px, float $track): float
{
    $chars = elips_cert_chars($text);
    $w = 0.0;
    foreach ($chars as $ch) {
        $w += elips_cert_adv($ch, $font, $px) + $track;
    }
    return max(0.0, $w - ($chars ? $track : 0.0));
}

function elips_cert_color($im, array $rgb, int $alpha = 0): int
{
    return imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
}

/** Tulis teks dengan jarak antar huruf. $x = tepi kiri, $y = garis dasar. */
function elips_cert_draw_text($im, string $text, float $x, float $y, string $font, float $px, float $track, int $color): void
{
    $file = elips_cert_font($font);
    $size = elips_cert_gd_size($px);
    foreach (elips_cert_chars($text) as $ch) {
        if ($ch !== ' ') {
            imagettftext($im, $size, 0, (int) round($x), (int) round($y), $color, $file, $ch);
        }
        $x += elips_cert_adv($ch, $font, $px) + $track;
    }
}

function elips_cert_draw_center($im, string $text, float $cx, float $y, string $font, float $px, float $track, int $color): void
{
    elips_cert_draw_text($im, $text, $cx - elips_cert_text_w($text, $font, $px, $track) / 2, $y, $font, $px, $track, $color);
}

/** Potong teks dengan elipsis agar muat dalam $maxW. */
function elips_cert_fit(string $text, string $font, float $px, float $track, float $maxW): string
{
    if (elips_cert_text_w($text, $font, $px, $track) <= $maxW) {
        return $text;
    }
    $chars = elips_cert_chars($text);
    while ($chars && elips_cert_text_w(implode('', $chars) . '…', $font, $px, $track) > $maxW) {
        array_pop($chars);
    }
    return rtrim(implode('', $chars)) . '…';
}

function elips_cert_rrect($im, float $x1, float $y1, float $x2, float $y2, float $r, int $color): void
{
    $x1 = (int) round($x1);
    $y1 = (int) round($y1);
    $x2 = (int) round($x2);
    $y2 = (int) round($y2);
    $r = (int) max(0, min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2));
    if ($r === 0) {
        imagefilledrectangle($im, $x1, $y1, $x2, $y2, $color);
        return;
    }
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

/** Gambar QR di kotak ($bx,$by,$bsize); modul berukuran bulat agar tajam. */
function elips_cert_draw_qr($im, string $text, int $bx, int $by, int $bsize, string $ecc, bool $logo): void
{
    $matrix = elips_qr_matrix($text, $ecc);
    $n = count($matrix);
    $mod = max(2, intdiv($bsize, $n));
    $total = $mod * $n;
    $ox = $bx + intdiv($bsize - $total, 2);
    $oy = $by + intdiv($bsize - $total, 2);
    $dark = elips_cert_color($im, ELIPS_CERT_QR);
    foreach ($matrix as $y => $row) {
        foreach ($row as $x => $on) {
            if ($on) {
                imagefilledrectangle($im, $ox + $x * $mod, $oy + $y * $mod, $ox + ($x + 1) * $mod - 1, $oy + ($y + 1) * $mod - 1, $dark);
            }
        }
    }
    $logoFile = elips_cert_dir() . '/qr-logo.png';
    if ($logo && $ecc === 'H' && is_file($logoFile)) {
        $cell = (int) round($total * 0.22);
        $cx = $ox + intdiv($total, 2);
        $cy = $oy + intdiv($total, 2);
        $white = imagecolorallocate($im, 255, 255, 255);
        elips_cert_rrect($im, $cx - $cell / 2, $cy - $cell / 2, $cx + $cell / 2, $cy + $cell / 2, $cell * 0.18, $white);
        $lg = imagecreatefrompng($logoFile);
        if ($lg) {
            $lw = imagesx($lg);
            $lh = imagesy($lg);
            $target = (int) round($cell * 0.72);
            $th = (int) round($target * $lh / $lw);
            imagealphablending($im, true);
            imagecopyresampled($im, $lg, $cx - intdiv($target, 2), $cy - intdiv($th, 2), 0, 0, $target, $th, $lw, $lh);
            imagedestroy($lg);
        }
    }
}

/** Teks nama bergaya miring tebal (Caprasimo + kemiringan). */
function elips_cert_draw_name($im, string $name, float $cx, float $baseline, float $maxW): void
{
    $px = 262.0;
    $track = 16.0;
    $slant = 0.25;
    $w = elips_cert_text_w($name, 'name', $px, $track);
    if ($w > $maxW) {
        $f = $maxW / $w;
        $px *= $f;
        $track *= $f;
        $w = $maxW;
    }
    $padX = 200;
    $height = (int) ($px * 1.6);
    $canvasW = (int) ($w + $padX * 2);
    $tmp = imagecreatetruecolor($canvasW, $height);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 255, 255, 255, 127));
    imagealphablending($tmp, true);
    $base = (int) ($px * 1.15);
    elips_cert_draw_text($tmp, $name, $padX, $base, 'name', $px, $track, elips_cert_color($tmp, ELIPS_CERT_NAVY));
    imagealphablending($im, true);
    $ox = (int) round($cx - $canvasW / 2);
    $oy = (int) round($baseline - $base);
    for ($y = 0; $y < $height; $y++) {
        $shift = (int) round($slant * ($base - $y));
        imagecopy($im, $tmp, $ox + $shift, $oy + $y, 0, $y, $canvasW, 1);
    }
    imagedestroy($tmp);
}

/** Bungkus kata bergaya (navy/aksen) menjadi baris. @param array<int,array{0:string,1:bool}> $words */
function elips_cert_wrap(array $words, float $px, float $track, float $maxW): array
{
    $lines = [];
    $cur = [];
    $curW = 0.0;
    $space = elips_cert_adv(' ', 'bold', $px) + $track;
    foreach ($words as [$text, $accent]) {
        $w = elips_cert_text_w($text, 'bold', $px, $track);
        $add = ($cur ? $space : 0.0) + $w;
        if ($cur && $curW + $add > $maxW) {
            $lines[] = $cur;
            $cur = [];
            $curW = 0.0;
            $add = $w;
        }
        $cur[] = [$text, $accent, $w];
        $curW += $add;
    }
    if ($cur) {
        $lines[] = $cur;
    }
    return $lines;
}

function elips_cert_draw_body($im, array $d): void
{
    $px = 70.0;
    $track = 13.1;
    $cx = 2311.0;
    $words = [];
    $push = static function (string $text, bool $accent) use (&$words): void {
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $w) {
            if ($w !== '') {
                $words[] = [$w, $accent];
            }
        }
    };
    $push('Telah menyelesaikan materi kursus', false);
    $push(mb_strtoupper($d['course']), true);
    $push('predikat', false);
    $push(mb_strtoupper($d['predicateLabel']), true);
    $push('dan memenuhi syarat pendidikan yang telah ditentukan untuk memperoleh sertifikat dengan durasi pembelajaran', false);
    $push(elips_cert_jp_text((float) $d['totalJp']) . ' jam.', false);

    $navy = elips_cert_color($im, ELIPS_CERT_NAVY);
    $accent = elips_cert_color($im, ELIPS_CERT_ACCENT);
    $space = elips_cert_adv(' ', 'bold', $px) + $track;
    $y = 1711.0;
    foreach (elips_cert_wrap($words, $px, $track, 3600.0) as $line) {
        $total = 0.0;
        foreach ($line as $i => [$t, $a, $w]) {
            $total += ($i ? $space : 0.0) + $w;
        }
        $x = $cx - $total / 2;
        foreach ($line as $i => [$t, $a, $w]) {
            if ($i) {
                $x += $space;
            }
            elips_cert_draw_text($im, $t, $x, $y, 'bold', $px, $track, $a ? $accent : $navy);
            $x += $w;
        }
        $y += 84.5;
    }
}

function elips_cert_jp_text(float $jp): string
{
    return rtrim(rtrim(number_format($jp, 2, ',', ''), '0'), ',');
}

function elips_cert_fmt_num(float $v, int $dec = 0): string
{
    return number_format($v, $dec, ',', '');
}

/** Warna & huruf dipakai ulang oleh halaman depan dan belakang. */
function elips_cert_draw_number($im, string $number, float $baseline): void
{
    elips_cert_draw_center($im, $number, 2251.0, $baseline, 'regular', 91.0, 3.5, elips_cert_color($im, ELIPS_CERT_NAVY));
}

function elips_cert_draw_ribbon($im, string $validText): void
{
    $text = 'Sertifikat ini berlaku untuk 3 (Tiga) Tahun. Berlaku s/d ' . $validText;
    elips_cert_draw_center($im, $text, 3029.0, 3127.0, 'regular', 66.0, 4.4, imagecolorallocate($im, 255, 255, 255));
}

function elips_cert_load_template(string $side)
{
    $file = elips_cert_dir() . '/template-' . ($side === 'back' ? 'belakang' : 'depan') . '.png';
    if (!is_file($file)) {
        throw new RuntimeException('Template sertifikat tidak ditemukan: ' . basename($file));
    }
    $im = imagecreatefrompng($file);
    if (!$im) {
        throw new RuntimeException('Template sertifikat tidak dapat dibaca.');
    }
    if (!imageistruecolor($im)) {
        imagepalettetotruecolor($im);
    }
    imagealphablending($im, true);
    imagesavealpha($im, false);
    return $im;
}

function elips_cert_render_front(array $d)
{
    $im = elips_cert_load_template('front');
    elips_cert_draw_number($im, $d['number'], 966.0);
    elips_cert_draw_name($im, $d['name'], 2246.0, 1489.0, 3930.0);
    elips_cert_draw_body($im, $d);
    elips_cert_draw_qr($im, $d['url'], 538, 2219, 528, 'H', true);
    elips_cert_draw_ribbon($im, $d['validText']);
    return $im;
}

function elips_cert_render_back(array $d)
{
    $im = elips_cert_load_template('back');
    elips_cert_draw_number($im, $d['number'], 897.0);

    // Data peserta di sebelah kanan
    $orange = elips_cert_color($im, ELIPS_CERT_ORANGE);
    $rows = [[$d['name'], 1160.0], [$d['course'], 1268.0], [$d['predicateLabel'], 1377.0]];
    foreach ($rows as [$value, $baseline]) {
        $px = 74.0;
        $track = 10.1;
        $maxW = 4330.0 - 2935.0;
        $w = elips_cert_text_w($value, 'bold', $px, $track);
        if ($w > $maxW) {
            $f = $maxW / $w;
            $px *= $f;
            $track *= $f;
        }
        elips_cert_draw_text($im, $value, 2935.0, $baseline, 'bold', $px, $track, $orange);
    }
    elips_cert_draw_qr($im, $d['url'], 2415, 1477, 197, 'M', false);
    elips_cert_draw_transcript($im, $d);
    elips_cert_draw_ribbon($im, $d['validText']);
    return $im;
}

/** Tabel nilai: modern, menyesuaikan jumlah baris (teks mengecil bila banyak). */
function elips_cert_draw_transcript($im, array $d): void
{
    $x1 = 371.0;
    $x2 = 2290.0;
    $y1 = 980.0;
    $y2 = 2861.0;
    $W = $x2 - $x1;

    $rows = [];
    $no = 0;
    foreach ($d['groups'] as $g) {
        $rows[] = ['type' => 'group', 'title' => (string) $g['title']];
        foreach ($g['items'] as $it) {
            $no++;
            $rows[] = ['type' => 'item', 'no' => $no, 'title' => (string) $it['title'], 'score' => $it['score'], 'letter' => $it['letter'], 'jp' => $it['jp']];
        }
    }
    $count = count($rows) + 2; // + judul kolom + total
    $rowH = min(92.0, ($y2 - $y1) / $count);
    $fs = max(13.0, min(42.0, $rowH * 0.46));
    $pad = max(8.0, $rowH * 0.28);
    $r = min(26.0, $rowH * 0.34);

    $cols = [
        'title' => [$x1, $x1 + $W * 0.60],
        'score' => [$x1 + $W * 0.60, $x1 + $W * 0.77],
        'ket' => [$x1 + $W * 0.77, $x1 + $W * 0.90],
        'jp' => [$x1 + $W * 0.90, $x2],
    ];

    $navy = elips_cert_color($im, ELIPS_CERT_NAVY);
    $accent = elips_cert_color($im, ELIPS_CERT_ACCENT);
    $white = elips_cert_color($im, [255, 255, 255]);
    $rowBg = elips_cert_color($im, [255, 255, 255], 20);
    $rowAlt = elips_cert_color($im, [242, 246, 252], 18);
    $groupBg = elips_cert_color($im, [255, 233, 204], 6);
    $line = elips_cert_color($im, [214, 222, 235], 30);
    $totalBg = elips_cert_color($im, [255, 233, 204], 2);

    $gap = max(2.0, $rowH * 0.05);
    $baselineOff = ($rowH + $fs * 0.70) / 2 - 1;
    $center = static fn (array $c): float => ($c[0] + $c[1]) / 2;

    $y = $y1;
    // judul kolom
    elips_cert_rrect($im, $x1, $y, $x2, $y + $rowH - $gap, $r, $navy);
    $hfs = $fs * 0.82;
    $hb = $y + ($rowH - $gap + $hfs * 0.70) / 2;
    elips_cert_draw_text($im, 'MATERI', $x1 + $pad * 1.4, $hb, 'semibold', $hfs, $hfs * 0.08, $white);
    elips_cert_draw_center($im, 'NILAI', $center($cols['score']), $hb, 'semibold', $hfs, $hfs * 0.08, $white);
    elips_cert_draw_center($im, 'KET.', $center($cols['ket']), $hb, 'semibold', $hfs, $hfs * 0.08, $white);
    elips_cert_draw_center($im, 'JP', $center($cols['jp']), $hb, 'semibold', $hfs, $hfs * 0.08, $white);
    $y += $rowH;

    $i = 0;
    foreach ($rows as $row) {
        $top = $y;
        $bot = $y + $rowH - $gap;
        $base = $y + $baselineOff - $gap / 2;
        if ($row['type'] === 'group') {
            elips_cert_rrect($im, $x1, $top, $x2, $bot, $r * 0.7, $groupBg);
            $t = elips_cert_fit($row['title'], 'bold', $fs, $fs * 0.04, $W - $pad * 3);
            elips_cert_draw_text($im, $t, $x1 + $pad * 1.4, $base, 'bold', $fs, $fs * 0.04, $navy);
            $i = 0;
        } else {
            elips_cert_rrect($im, $x1, $top, $x2, $bot, $r * 0.7, $i % 2 === 0 ? $rowBg : $rowAlt);
            $label = $row['no'] . '.  ' . $row['title'];
            $t = elips_cert_fit($label, 'medium', $fs, 0.0, $cols['title'][1] - $x1 - $pad * 2.4);
            elips_cert_draw_text($im, $t, $x1 + $pad * 1.4, $base, 'medium', $fs, 0.0, $navy);
            $scoreText = $row['score'] === null ? '-' : elips_cert_fmt_num((float) $row['score'], 0);
            elips_cert_draw_center($im, $scoreText, $center($cols['score']), $base, 'semibold', $fs, 0.0, $navy);
            elips_cert_draw_center($im, (string) $row['letter'], $center($cols['ket']), $base, 'bold', $fs, 0.0, $accent);
            elips_cert_draw_center($im, elips_cert_jp_text((float) $row['jp']), $center($cols['jp']), $base, 'medium', $fs, 0.0, $navy);
            $i++;
        }
        $y += $rowH;
    }

    // baris total
    $top = $y + $gap;
    $bot = $y + $rowH - $gap;
    elips_cert_rrect($im, $x1, $top, $x2, $bot, $r, $totalBg);
    imagesetthickness($im, max(2, (int) round($rowH * 0.04)));
    imageline($im, (int) $x1 + (int) $r, (int) $top, (int) $x2 - (int) $r, (int) $top, $navy);
    imagesetthickness($im, 1);
    $tb = $top + (($bot - $top) + $fs * 0.70) / 2;
    elips_cert_draw_text($im, 'RATA-RATA / TOTAL JP', $x1 + $pad * 1.4, $tb, 'bold', $fs, $fs * 0.04, $navy);
    elips_cert_draw_center($im, elips_cert_fmt_num((float) $d['average'], 0), $center($cols['score']), $tb, 'bold', $fs, 0.0, $navy);
    elips_cert_draw_center($im, (string) $d['averageLetter'], $center($cols['ket']), $tb, 'bold', $fs, 0.0, $accent);
    elips_cert_draw_center($im, elips_cert_jp_text((float) $d['totalJp']), $center($cols['jp']), $tb, 'bold', $fs, 0.0, $navy);
}

/** @return string biner gambar ('jpg' | 'png') untuk sisi 'front' | 'back'. */
function elips_cert_image(array $d, string $side, string $format): string
{
    @ini_set('memory_limit', '768M');
    @set_time_limit(120);
    $im = $side === 'back' ? elips_cert_render_back($d) : elips_cert_render_front($d);
    ob_start();
    if ($format === 'png') {
        imagepng($im, null, 6);
    } else {
        imagejpeg($im, null, 92);
    }
    $bytes = (string) ob_get_clean();
    imagedestroy($im);
    return $bytes;
}

/** PDF 2 halaman A4 landscape dari dua JPEG (depan, belakang). */
function elips_cert_pdf(string $frontJpg, string $backJpg, string $title): string
{
    $pages = [];
    foreach ([$frontJpg, $backJpg] as $jpg) {
        $info = getimagesizefromstring($jpg);
        $pages[] = ['w' => (int) $info[0], 'h' => (int) $info[1], 'data' => $jpg];
    }
    $objs = [];
    $add = static function (string $body) use (&$objs): int {
        $objs[] = $body;
        return count($objs);
    };
    $catalog = $add('');
    $pagesObj = $add('');
    $kids = [];
    foreach ($pages as $i => $p) {
        $img = $add("<< /Type /XObject /Subtype /Image /Width {$p['w']} /Height {$p['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($p['data']) . " >>\nstream\n" . $p['data'] . "\nendstream");
        $content = "q 841.89 0 0 595.28 0 0 cm /Im0 Do Q";
        $cont = $add('<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream");
        $page = $add("<< /Type /Page /Parent $pagesObj 0 R /MediaBox [0 0 841.89 595.28] /Resources << /XObject << /Im0 $img 0 R >> >> /Contents $cont 0 R >>");
        $kids[] = "$page 0 R";
    }
    $safeTitle = preg_replace('/[^\x20-\x7E]/', '', $title);
    $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $safeTitle);
    $infoObj = $add("<< /Title ($safeTitle) /Producer (ELIPS LMS) /Creator (ELIPS Academy) >>");
    $objs[$catalog - 1] = "<< /Type /Catalog /Pages $pagesObj 0 R >>";
    $objs[$pagesObj - 1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

    $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objs as $i => $body) {
        $offsets[$i + 1] = strlen($out);
        $out .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $out .= sprintf("%010d 00000 n \n", $off);
    }
    $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root $catalog 0 R /Info $infoObj 0 R >>\nstartxref\n$xref\n%%EOF";
    return $out;
}