<?php
/**
 * Standalone Pure PHP QR Code Helper
 * Uses QRCodeEngine (ISO/IEC 18004 Standard QR Code Model 2).
 * 100% Pure PHP 7.4+, Zero Dependencies, Instant scanning on all phone cameras & scanners.
 */
require_once __DIR__ . '/qrcode_engine.php';

class SimpleQRCode {
    /**
     * Generate standard QR Code SVG String
     */
    public static function svg($text, $size = 320, $margin = 4, $fgColor = '#000000', $bgColor = '#ffffff') {
        $matrix = QRCodeEngine::createMatrix($text, QR_ECLEVEL_M);
        $count = count($matrix);
        $totalSize = $count + ($margin * 2);
        
        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 ' . $totalSize . ' ' . $totalSize . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges">' . "\n";
        $svg .= '  <rect width="100%" height="100%" fill="' . $bgColor . '"/>' . "\n";
        $svg .= '  <path fill="' . $fgColor . '" d="';
        
        $pathData = '';
        for ($y = 0; $y < $count; $y++) {
            for ($x = 0; $x < $count; $x++) {
                if ($matrix[$y][$x] === 1) {
                    $posX = $x + $margin;
                    $posY = $y + $margin;
                    $pathData .= "M{$posX},{$posY}h1v1h-1z ";
                }
            }
        }
        $svg .= trim($pathData) . '"/>' . "\n";
        $svg .= '</svg>';
        
        return $svg;
    }

    /**
     * Save SVG directly to a file
     */
    public static function saveToFile($text, $filePath, $size = 340) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $svgContent = self::svg($text, $size);
        return file_put_contents($filePath, $svgContent) !== false;
    }
}
