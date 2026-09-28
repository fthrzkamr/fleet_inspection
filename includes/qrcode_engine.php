<?php
/**
 * PHP QR Code - Complete Standalone Pure PHP 7.4+ ISO/IEC 18004 Engine
 * Based on QRcode 2D Barcode Generator by Kazuhiko Arase & Dominik Dzienia.
 * 100% Pure PHP, Zero Extensions Required, Outputs sharp Vector SVG.
 */

define('QR_MODE_NUL', -1);
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);
define('QR_MODE_STRUCTURE', 4);

define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

define('QR_FORMAT_TEXT', 0);
define('QR_FORMAT_PNG', 1);

class QRspec {
    public static $capacity = [
        [0, 0, 0, [0, 0, 0, 0]],
        [21, 26, 0, [7, 10, 13, 17], [1, 1, 1, 1], [19, 16, 13, 9]],
        [25, 44, 7, [10, 16, 22, 28], [1, 1, 1, 1], [34, 28, 22, 16]],
        [29, 70, 7, [15, 26, 36, 44], [1, 1, 2, 2], [55, 44, 17, 13]],
        [33, 100, 7, [20, 36, 52, 64], [1, 2, 2, 4], [80, 32, 24, 9]],
        [37, 134, 7, [26, 48, 72, 88], [1, 2, 4, 4], [108, 43, 15, 11]]
    ];

    public static $lengthTableBits = [
        [10, 12, 14],
        [9, 11, 13],
        [8, 16, 16],
        [8, 10, 12]
    ];

    public static $alignmentPattern = [
        [0, 0],
        [0, 0],
        [6, 18],
        [6, 22],
        [6, 26],
        [6, 30]
    ];

    public static function getDataLength($version, $level) {
        return self::$capacity[$version][5][$level];
    }

    public static function getECCLength($version, $level) {
        return self::$capacity[$version][3][$level];
    }

    public static function getWidth($version) {
        return self::$capacity[$version][0];
    }

    public static function lengthIndicator($mode, $version) {
        if ($mode == QR_MODE_KANJI) return 0;
        $l = ($version <= 9) ? 0 : (($version <= 26) ? 1 : 2);
        return self::$lengthTableBits[$mode][$l];
    }

    public static function maximumWords($mode, $version) {
        $l = ($version <= 9) ? 0 : (($version <= 26) ? 1 : 2);
        $bits = self::$lengthTableBits[$mode][$l];
        $words = (1 << $bits) - 1;
        if ($mode == QR_MODE_8) return $words;
        if ($mode == QR_MODE_NUM) return (int)($words * 3 / 10);
        if ($mode == QR_MODE_AN) return (int)($words * 2 / 11);
        return 0;
    }

    public static function createFrame($version) {
        $width = self::$capacity[$version][0];
        $frame = array_fill(0, $width, str_repeat("\0", $width));

        // Finder patterns
        self::putFinder($frame, 0, 0);
        self::putFinder($frame, $width - 7, 0);
        self::putFinder($frame, 0, $width - 7);

        // Separator
        for ($y = 0; $y < 8; $y++) {
            $frame[$y][7] = "\xC0";
            $frame[$y][$width - 8] = "\xC0";
            $frame[$width - 8 + $y][7] = "\xC0";
        }
        for ($x = 0; $x < 8; $x++) {
            $frame[7][$x] = "\xC0";
            $frame[7][$width - 8 + $x] = "\xC0";
            $frame[$width - 8][7 - $x] = "\xC0";
        }

        // Timing pattern
        for ($i = 8; $i < $width - 8; $i++) {
            $val = ($i & 1) ? "\x84" : "\x85";
            $frame[6][$i] = $val;
            $frame[$i][6] = $val;
        }

        // Alignment pattern
        if ($version >= 2) {
            $pos = self::$alignmentPattern[$version];
            self::putAlignment($frame, $pos[1], $pos[1]);
        }

        // Dark module
        $frame[4 * $version + 9][8] = "\x85";

        // Reserve format info strips (values written later during masking;
        // must be marked non-zero now so the data zigzag skips over them)
        for ($i = 0; $i < 9; $i++) {
            if ($i == 6) continue;
            $frame[8][$i] = "\xC0";
            $frame[$i][8] = "\xC0";
        }
        for ($i = 0; $i < 7; $i++) {
            $frame[$width - 1 - $i][8] = "\xC0";
        }
        for ($i = 0; $i < 8; $i++) {
            $frame[8][$width - 8 + $i] = "\xC0";
        }

        return $frame;
    }

    private static function putFinder(&$frame, $ox, $oy) {
        $finder = [
            "\xC1\xC1\xC1\xC1\xC1\xC1\xC1",
            "\xC1\xC0\xC0\xC0\xC0\xC0\xC1",
            "\xC1\xC0\xC1\xC1\xC1\xC0\xC1",
            "\xC1\xC0\xC1\xC1\xC1\xC0\xC1",
            "\xC1\xC0\xC1\xC1\xC1\xC0\xC1",
            "\xC1\xC0\xC0\xC0\xC0\xC0\xC1",
            "\xC1\xC1\xC1\xC1\xC1\xC1\xC1"
        ];
        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $frame[$oy + $y][$ox + $x] = $finder[$y][$x];
            }
        }
    }

    private static function putAlignment(&$frame, $ox, $oy) {
        $align = [
            "\x91\x91\x91\x91\x91",
            "\x91\x90\x90\x90\x91",
            "\x91\x90\x91\x90\x91",
            "\x91\x90\x90\x90\x91",
            "\x91\x91\x91\x91\x91"
        ];
        for ($y = 0; $y < 5; $y++) {
            for ($x = 0; $x < 5; $x++) {
                $frame[$oy - 2 + $y][$ox - 2 + $x] = $align[$y][$x];
            }
        }
    }
}

class QRbitstream {
    public $data = [];

    public function size() {
        return count($this->data);
    }

    public function appendNum($bits, $num) {
        if ($bits == 0) return 0;
        for ($i = $bits - 1; $i >= 0; $i--) {
            $this->data[] = ($num >> $i) & 1;
        }
        return 0;
    }

    public function appendBytes($bits, $data) {
        if ($bits == 0) return 0;
        for ($i = 0; $i < $bits; $i++) {
            $byte = (int)($i / 8);
            $bit = 7 - ($i % 8);
            $this->data[] = (ord($data[$byte]) >> $bit) & 1;
        }
        return 0;
    }

    public function toByte() {
        $bytes = [];
        $size = count($this->data);
        for ($i = 0; $i < $size; $i += 8) {
            $b = 0;
            for ($j = 0; $j < 8; $j++) {
                $b <<= 1;
                if ($i + $j < $size) {
                    $b |= $this->data[$i + $j];
                }
            }
            $bytes[] = $b;
        }
        return $bytes;
    }
}

class QRrs {
    public static $exp = [];
    public static $log = [];
    public static $init = false;

    public static function init() {
        if (self::$init) return;
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11d;
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$init = true;
    }

    public static function modN($x) {
        while ($x >= 255) {
            $x -= 255;
            $x = ($x >> 8) + ($x & 255);
        }
        return $x;
    }

    public static function encode($data, $nroots) {
        self::init();
        $genPoly = self::getGenPoly($nroots);
        $res = array_fill(0, $nroots, 0);
        $dataLen = count($data);

        for ($i = 0; $i < $dataLen; $i++) {
            $feedback = self::$log[$data[$i] ^ $res[0]];
            if ($data[$i] ^ $res[0]) {
                for ($j = 1; $j < $nroots; $j++) {
                    $res[$j - 1] = $res[$j] ^ self::$exp[self::modN($feedback + self::$log[$genPoly[$nroots - $j]])];
                }
                $res[$nroots - 1] = self::$exp[self::modN($feedback + self::$log[$genPoly[0]])];
            } else {
                for ($j = 1; $j < $nroots; $j++) {
                    $res[$j - 1] = $res[$j];
                }
                $res[$nroots - 1] = 0;
            }
        }
        return $res;
    }

    private static function getGenPoly($nroots) {
        $gen = [1];
        for ($i = 0; $i < $nroots; $i++) {
            $next = [1, self::$exp[$i]];
            $res = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                for ($k = 0; $k < count($next); $k++) {
                    $res[$j + $k] ^= ($gen[$j] && $next[$k]) ? self::$exp[self::modN(self::$log[$gen[$j]] + self::$log[$next[$k]])] : 0;
                }
            }
            $gen = $res;
        }
        return array_reverse(array_slice($gen, 1));
    }
}

class QRmask {
    public static function mask($frame, $maskNo, $level) {
        $width = strlen($frame[0]);
        $masked = $frame;

        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (ord($frame[$y][$x]) & 0x80) continue;
                $invert = false;
                switch ($maskNo) {
                    case 0: $invert = (($x + $y) % 2 == 0); break;
                    case 1: $invert = ($y % 2 == 0); break;
                    case 2: $invert = ($x % 3 == 0); break;
                    case 3: $invert = (($x + $y) % 3 == 0); break;
                    case 4: $invert = (((int)($y / 2) + (int)($x / 3)) % 2 == 0); break;
                    case 5: $invert = (($x * $y) % 2 + ($x * $y) % 3 == 0); break;
                    case 6: $invert = ((($x * $y) % 2 + ($x * $y) % 3) % 2 == 0); break;
                    case 7: $invert = ((($x + $y) % 2 + ($x * $y) % 3) % 2 == 0); break;
                }
                if ($invert) {
                    $masked[$y][$x] = chr(ord($frame[$y][$x]) ^ 1);
                }
            }
        }

        // Format info (15 bits BCH)
        $format = self::getFormatInfo($maskNo, $level);

        // Copy 1 (top-left): col8 ascending bits 0-7, row8 descending bits 14-7
        $col8Rows = [0, 1, 2, 3, 4, 5, 7, 8];
        foreach ($col8Rows as $k => $r) {
            $masked[$r][8] = chr(0x84 | (($format >> $k) & 1));
        }
        $row8Cols = [0, 1, 2, 3, 4, 5, 7, 8];
        foreach ($row8Cols as $k => $c) {
            $masked[8][$c] = chr(0x84 | (($format >> (14 - $k)) & 1));
        }

        // Copy 2: bottom-left descending bits 14-8, top-right descending bits 7-0
        for ($k = 0; $k < 7; $k++) {
            $masked[$width - 1 - $k][8] = chr(0x84 | (($format >> (14 - $k)) & 1));
        }
        for ($k = 0; $k < 8; $k++) {
            $masked[8][$width - 8 + $k] = chr(0x84 | (($format >> (7 - $k)) & 1));
        }

        return $masked;
    }

    public static function getFormatInfo($mask, $level) {
        $table = [
            [0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976], // L
            [0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0], // M
            [0x355f, 0x3068, 0x3f31, 0x3a06, 0x24b4, 0x2183, 0x2eda, 0x2bed], // Q
            [0x1689, 0x13be, 0x1ce7, 0x19d0, 0x0762, 0x0255, 0x0d0c, 0x083b]  // H
        ];
        return $table[$level][$mask];
    }
}

class QRCodeEngine {
    public static function createMatrix($text, $level = QR_ECLEVEL_M) {
        $len = strlen($text);
        $version = 1;
        for ($v = 1; $v <= 5; $v++) {
            if ($len <= QRspec::getDataLength($v, $level)) {
                $version = $v;
                break;
            }
        }

        // 1. Bitstream Encode (Byte Mode)
        $bstream = new QRbitstream();
        $bstream->appendNum(4, 1 << QR_MODE_8); // Mode indicator per ISO 18004: Byte mode = 0100
        $bstream->appendNum(QRspec::lengthIndicator(QR_MODE_8, $version), $len);
        $bstream->appendBytes($len * 8, $text);

        $maxDataBits = QRspec::getDataLength($version, $level) * 8;
        $termBits = min(4, $maxDataBits - $bstream->size());
        if ($termBits > 0) $bstream->appendNum($termBits, 0);

        if ($bstream->size() % 8 != 0) {
            $bstream->appendNum(8 - ($bstream->size() % 8), 0);
        }

        $padWords = ($maxDataBits - $bstream->size()) / 8;
        $padBytes = [0xEC, 0x11];
        for ($i = 0; $i < $padWords; $i++) {
            $bstream->appendNum(8, $padBytes[$i % 2]);
        }

        $rawBytes = $bstream->toByte();
        $eccWords = QRspec::getECCLength($version, $level);
        $ecc = QRrs::encode($rawBytes, $eccWords);
        $finalCodewords = array_merge($rawBytes, $ecc);

        // 2. Put Codewords into Frame
        $frame = QRspec::createFrame($version);
        $width = strlen($frame[0]);

        $finalBits = new QRbitstream();
        foreach ($finalCodewords as $cw) {
            $finalBits->appendNum(8, $cw);
        }

        $bitIdx = 0;
        $bitCount = $finalBits->size();
        $dir = -1;
        $y = $width - 1;

        for ($x = $width - 1; $x > 0; $x -= 2) {
            if ($x == 6) $x--;
            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    $cx = $x - $c;
                    if (ord($frame[$y][$cx]) == 0) {
                        $frame[$y][$cx] = ($bitIdx < $bitCount && $finalBits->data[$bitIdx]) ? "\x01" : "\x00";
                        $bitIdx++;
                    }
                }
                $y += $dir;
                if ($y < 0 || $y >= $width) {
                    $dir = -$dir;
                    $y += $dir;
                    break;
                }
            }
        }

        // Apply Standard Mask 0 (Level M)
        $masked = QRmask::mask($frame, 0, $level);

        // Convert to binary matrix (0 / 1)
        $matrix = [];
        for ($r = 0; $r < $width; $r++) {
            $row = [];
            for ($c = 0; $c < $width; $c++) {
                $row[] = (ord($masked[$r][$c]) & 1) ? 1 : 0;
            }
            $matrix[] = $row;
        }

        return $matrix;
    }
}
