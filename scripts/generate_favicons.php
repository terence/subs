<?php
// Simple favicon generator using GD. Reads assets/glitchdata_logo1.png and
// produces several PNG sizes and a basic favicon.ico containing the 32x32 PNG.
// Usage: php scripts/generate_favicons.php

$src = __DIR__ . '/../assets/glitchdata_logo1.png';
if (!is_readable($src)) {
    echo "Source logo not found: $src\n";
    exit(1);
}

if (!function_exists('imagecreatefrompng')) {
    echo "GD extension with PNG support is required.\n";
    exit(2);
}

$im = @imagecreatefrompng($src);
if (!$im) {
    echo "Failed to load source PNG: $src\n";
    exit(3);
}

$sizes = [16, 32, 48, 180];
foreach ($sizes as $s) {
    $outPath = __DIR__ . "/../assets/favicon-{$s}x{$s}.png";
    $canvas = imagecreatetruecolor($s, $s);
    imagesavealpha($canvas, true);
    $trans_colour = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $trans_colour);
    imagecopyresampled($canvas, $im, 0, 0, 0, 0, $s, $s, imagesx($im), imagesy($im));
    imagepng($canvas, $outPath, 9);
    imagedestroy($canvas);
    echo "Wrote: $outPath\n";
}

// Create a very simple favicon.ico that contains only the 32x32 PNG blob.
$png32 = __DIR__ . "/../assets/favicon-32x32.png";
if (is_readable($png32)) {
    $pngdata = file_get_contents($png32);
    $icoPath = __DIR__ . '/../assets/favicon.ico';
    // ICO header
    $header = pack('vvv', 0, 1, 1); // reserved, type=1(icon), count=1
    $width = 32; $height = 32; $colorCount = 0; $reserved=0; $planes=1; $bitcount=32; $size = strlen($pngdata);
    $offset = 6 + 16; // header + dir entry
    $entry = pack('CCCCvvVV', $width, $height, $colorCount, $reserved, $planes, $bitcount, $size, $offset);
    $data = $header . $entry . $pngdata;
    file_put_contents($icoPath, $data);
    echo "Wrote ICO: $icoPath\n";
} else {
    echo "Missing generated 32x32 png - cannot create favicon.ico\n";
}

echo "Done.\n";
