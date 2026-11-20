<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$templates = App\Models\Template::all();
echo "=== DATABASE TEMPLATES ===\n";
echo "Total: " . $templates->count() . "\n\n";

// List all with details
foreach ($templates as $t) {
    $a = $t->toArray();
    $sid = $a['slots'] ?? 'null';
    $slots_info = '';
    if (is_array($sid)) {
        $slots_info = 'fw=' . $sid['fw'] . ' fh=' . $sid['fh'] . ' holes=' . count($sid['holes']);
    }
    echo 'ID=' . $t->id . ' name="' . $t->name . '" layout="' . $a['layout_type'] . '" frame="' . ($a['frame_image'] ?? 'null') . '" slots=' . $slots_info . "\n";
}

echo "\n=== FILES IN public/storage/frames/ ===\n";
$files = glob('public/storage/frames/*.png');
foreach (sort($files) as $f) {
    $sz = filesize($f);
    echo $f . ' (' . round($sz/1024) . ' KB)' . "\n";
}

echo "\n=== FILES IN public/frames-config/ ===\n";
$jsonfiles = glob('public/frames-config/*.json');
$pngfiles = glob('public/frames-config/*.png');
foreach (sort($jsonfiles) as $f) { echo 'JSON: ' . basename($f) . "\n"; }
foreach (sort($pngfiles) as $f) { echo 'PNG : ' . basename($f) . "\n"; }