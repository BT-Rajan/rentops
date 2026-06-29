<?php
// Cpdf is in dompdf/lib/ in v1.x (but in src/ in some builds)
spl_autoload_register(function (string $class): void {
    $map = [
        'Dompdf\\Cpdf' => __DIR__ . '/dompdf/lib/Cpdf.php',
    ];
    if (isset($map[$class])) { require $map[$class]; return; }

    $prefixes = [
        'Dompdf\\'  => __DIR__ . '/dompdf/src/',
        'FontLib\\' => __DIR__ . '/fontlib/src/FontLib/',
        'Svg\\'     => __DIR__ . '/svglib/src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
        $rel  = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', '/', $rel) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});
