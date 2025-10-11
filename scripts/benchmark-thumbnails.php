#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use App\Photo\Domain\Service\ThumbnailGenerator;

$generator = new ThumbnailGenerator('/app/var/storage/photos');

echo "🔍 Thumbnail Generator Benchmark\n";
echo "================================\n\n";
echo '📊 Current method: '.$generator->getMethod()."\n\n";

// Trouver une image de test
$testImage = null;
$storageDir = '/app/var/storage/photos';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($storageDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/\.(jpg|jpeg|png)$/i', $file->getFilename())) {
        // Exclure les thumbnails
        if (!str_contains($file->getPathname(), '/thumbs/')) {
            $testImage = str_replace($storageDir.'/', '', $file->getPathname());

            break;
        }
    }
}

if (!$testImage) {
    echo "❌ No test image found\n";

    exit(1);
}

echo "📸 Test image: {$testImage}\n";
echo "📏 Generating 5 thumbnails...\n\n";

$times = [];
for ($i = 1; $i <= 5; ++$i) {
    $start = microtime(true);

    try {
        $thumbnailPath = $generator->generateThumbnail($testImage);
        $elapsed = (microtime(true) - $start) * 1000; // ms
        $times[] = $elapsed;

        echo sprintf("  Run %d: %.2f ms\n", $i, $elapsed);

        // Nettoyer le thumbnail de test
        @unlink($storageDir.'/'.$thumbnailPath);
    } catch (Exception $e) {
        echo sprintf("  Run %d: FAILED - %s\n", $i, $e->getMessage());
    }
}

if (!empty($times)) {
    $avg = array_sum($times) / count($times);
    echo sprintf("\n⏱️  Average: %.2f ms\n", $avg);
    echo sprintf("⚡ Speed: ~%.0f images/second\n", 1000 / $avg);
}

echo "\n✅ Benchmark complete\n";
