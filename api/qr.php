<?php
// Direktori: api/qr.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - pembuat QR Code (mode byte, versi 1-10, koreksi M/H)
// Tanpa pustaka luar. Dipakai untuk QR pada sertifikat dan transkrip.
// ============================================================

/** @return array{0:int,1:array<int,array{0:int,1:int}>} [ec per blok, [[jumlah blok, data per blok], ...]] */
function elips_qr_block_table(string $ecc, int $version): array
{
    static $tables = [
        'M' => [
            1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]],
            5 => [24, [[2, 43]]], 6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]],
            9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
        ],
        'H' => [
            1 => [17, [[1, 9]]], 2 => [28, [[1, 16]]], 3 => [22, [[2, 13]]], 4 => [16, [[4, 9]]],
            5 => [22, [[2, 11], [2, 12]]], 6 => [28, [[4, 15]]], 7 => [26, [[4, 13], [1, 14]]], 8 => [26, [[4, 14], [2, 15]]],
            9 => [24, [[4, 12], [4, 13]]], 10 => [28, [[6, 15], [2, 16]]],
        ],
    ];
    return $tables[$ecc][$version];
}

function elips_qr_gf(): array
{
    static $gf = null;
    if ($gf === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        $gf = [$exp, $log];
    }
    return $gf;
}

/** @param int[] $data @return int[] */
function elips_qr_rs(array $data, int $degree): array
{
    [$exp, $log] = elips_qr_gf();
    // polinom pembangkit
    $gen = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($gen) + 1, 0);
        foreach ($gen as $j => $coef) {
            $next[$j] ^= $coef;
            $next[$j + 1] ^= $coef === 0 ? 0 : $exp[$log[$coef] + $i];
        }
        $gen = $next;
    }
    $rem = array_fill(0, $degree, 0);
    foreach ($data as $byte) {
        $factor = $byte ^ array_shift($rem);
        $rem[] = 0;
        if ($factor !== 0) {
            for ($i = 0; $i < $degree; $i++) {
                $g = $gen[$i + 1];
                if ($g !== 0) {
                    $rem[$i] ^= $exp[$log[$factor] + $log[$g]];
                }
            }
        }
    }
    return $rem;
}

/** @return array{0:int,1:int[]} [versi, codeword terinterleave] */
function elips_qr_codewords(string $text, string $ecc): array
{
    $bytes = array_values(unpack('C*', $text) ?: []);
    $len = count($bytes);
    for ($version = 1; $version <= 10; $version++) {
        [$ecLen, $groups] = elips_qr_block_table($ecc, $version);
        $dataCap = 0;
        foreach ($groups as [$n, $d]) {
            $dataCap += $n * $d;
        }
        $countBits = $version >= 10 ? 16 : 8;
        if (4 + $countBits + $len * 8 <= $dataCap * 8) {
            break;
        }
    }
    if ($version > 10) {
        throw new RuntimeException('Teks QR terlalu panjang.');
    }

    $bits = [];
    $push = static function (int $value, int $count) use (&$bits): void {
        for ($i = $count - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    };
    $push(0b0100, 4);
    $push($len, $countBits);
    foreach ($bytes as $b) {
        $push($b, 8);
    }
    $capBits = $dataCap * 8;
    $push(0, min(4, $capBits - count($bits)));
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }
    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $v = 0;
        for ($j = 0; $j < 8; $j++) {
            $v = ($v << 1) | $bits[$i + $j];
        }
        $data[] = $v;
    }
    for ($pad = 0xEC; count($data) < $dataCap; $pad ^= 0xEC ^ 0x11) {
        $data[] = $pad;
    }

    $blocksData = [];
    $blocksEc = [];
    $offset = 0;
    foreach ($groups as [$n, $d]) {
        for ($i = 0; $i < $n; $i++) {
            $chunk = array_slice($data, $offset, $d);
            $offset += $d;
            $blocksData[] = $chunk;
            $blocksEc[] = elips_qr_rs($chunk, $ecLen);
        }
    }
    $out = [];
    $maxData = max(array_map('count', $blocksData));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($blocksData as $blk) {
            if (isset($blk[$i])) {
                $out[] = $blk[$i];
            }
        }
    }
    for ($i = 0; $i < $ecLen; $i++) {
        foreach ($blocksEc as $blk) {
            $out[] = $blk[$i];
        }
    }
    return [$version, $out];
}

function elips_qr_penalty(array $m, int $size): int
{
    $score = 0;
    // aturan 1: deret warna sama
    for ($pass = 0; $pass < 2; $pass++) {
        for ($a = 0; $a < $size; $a++) {
            $run = 1;
            for ($b = 1; $b < $size; $b++) {
                $cur = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                $prev = $pass === 0 ? $m[$a][$b - 1] : $m[$b - 1][$a];
                if ($cur === $prev) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }
                    $run = 1;
                }
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }
    }
    // aturan 2: blok 2x2
    for ($y = 0; $y < $size - 1; $y++) {
        for ($x = 0; $x < $size - 1; $x++) {
            $c = $m[$y][$x];
            if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                $score += 3;
            }
        }
    }
    // aturan 3: pola mirip penanda
    $pat = [true, false, true, true, true, false, true];
    for ($pass = 0; $pass < 2; $pass++) {
        for ($a = 0; $a < $size; $a++) {
            for ($b = 0; $b <= $size - 7; $b++) {
                $match = true;
                for ($k = 0; $k < 7; $k++) {
                    $v = $pass === 0 ? $m[$a][$b + $k] : $m[$b + $k][$a];
                    if ($v !== $pat[$k]) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
                $light = static function (int $i) use ($m, $a, $pass, $size): bool {
                    if ($i < 0 || $i >= $size) {
                        return true;
                    }
                    return !($pass === 0 ? $m[$a][$i] : $m[$i][$a]);
                };
                $before = $light($b - 1) && $light($b - 2) && $light($b - 3) && $light($b - 4);
                $after = $light($b + 7) && $light($b + 8) && $light($b + 9) && $light($b + 10);
                if ($before || $after) {
                    $score += 40;
                }
            }
        }
    }
    // aturan 4: keseimbangan
    $dark = 0;
    foreach ($m as $row) {
        foreach ($row as $v) {
            $dark += $v ? 1 : 0;
        }
    }
    $total = $size * $size;
    $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
    return $score + max(0, $k) * 10;
}

/** @return bool[][] matriks modul (true = gelap) */
function elips_qr_matrix(string $text, string $ecc = 'H'): array
{
    try {
        [$version, $codewords] = elips_qr_codewords($text, $ecc);
    } catch (RuntimeException $e) {
        if ($ecc === 'H') {
            return elips_qr_matrix($text, 'M');
        }
        throw $e;
    }
    $size = 17 + 4 * $version;
    $m = array_fill(0, $size, array_fill(0, $size, false));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $setFn = static function (int $x, int $y, bool $dark) use (&$m, &$fn, $size): void {
        if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
            $m[$y][$x] = $dark;
            $fn[$y][$x] = true;
        }
    };

    // pola pewaktu
    for ($i = 0; $i < $size; $i++) {
        $setFn(6, $i, $i % 2 === 0);
        $setFn($i, 6, $i % 2 === 0);
    }
    // penanda posisi
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $setFn($cx + $dx, $cy + $dy, $dist !== 2 && $dist !== 4);
            }
        }
    }
    // pola penyelaras
    $positions = [];
    if ($version > 1) {
        $numAlign = intdiv($version, 7) + 2;
        $step = (int) (ceil(($version * 4 + 4) / ($numAlign * 2 - 2)) * 2);
        $positions = [6];
        for ($pos = $size - 7; count($positions) < $numAlign; $pos -= $step) {
            array_splice($positions, 1, 0, [$pos]);
        }
    }
    $last = count($positions) - 1;
    foreach ($positions as $i => $px) {
        foreach ($positions as $j => $py) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $setFn($px + $dx, $py + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
    }
    // cadangkan area format (diisi nanti) dan versi
    for ($i = 0; $i < 9; $i++) {
        $setFn(8, $i, false);
        $setFn($i, 8, false);
    }
    for ($i = 0; $i < 8; $i++) {
        $setFn($size - 1 - $i, 8, false);
        $setFn(8, $size - 1 - $i, false);
    }
    if ($version >= 7) {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $setFn($a, $b, $bit);
            $setFn($b, $a, $bit);
        }
    }

    // penempatan data
    $i = 0;
    $totalBits = count($codewords) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if (!$fn[$y][$x] && $i < $totalBits) {
                    $m[$y][$x] = ((($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1);
                    $i++;
                }
            }
        }
    }

    $eccBits = ['M' => 0, 'H' => 2][$ecc];
    $applyMask = static function (array $base, int $mask) use ($size, $fn): array {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($fn[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $inv = ($x + $y) % 2 === 0; break;
                    case 1: $inv = $y % 2 === 0; break;
                    case 2: $inv = $x % 3 === 0; break;
                    case 3: $inv = ($x + $y) % 3 === 0; break;
                    case 4: $inv = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $inv = ($x * $y) % 2 + ($x * $y) % 3 === 0; break;
                    case 6: $inv = (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0; break;
                    default: $inv = (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0; break;
                }
                if ($inv) {
                    $base[$y][$x] = !$base[$y][$x];
                }
            }
        }
        return $base;
    };
    $drawFormat = static function (array $base, int $mask) use ($size, $eccBits): array {
        $data = ($eccBits << 3) | $mask;
        $rem = $data;
        for ($k = 0; $k < 10; $k++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = static fn (int $k): bool => (($bits >> $k) & 1) === 1;
        for ($k = 0; $k <= 5; $k++) {
            $base[$k][8] = $bit($k);
        }
        $base[7][8] = $bit(6);
        $base[8][8] = $bit(7);
        $base[8][7] = $bit(8);
        for ($k = 9; $k < 15; $k++) {
            $base[8][14 - $k] = $bit($k);
        }
        for ($k = 0; $k < 8; $k++) {
            $base[8][$size - 1 - $k] = $bit($k);
        }
        for ($k = 8; $k < 15; $k++) {
            $base[$size - 15 + $k][8] = $bit($k);
        }
        $base[$size - 8][8] = true;
        return $base;
    };

    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidate = $drawFormat($applyMask($m, $mask), $mask);
        $score = elips_qr_penalty($candidate, $size);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }
    return $best;
}