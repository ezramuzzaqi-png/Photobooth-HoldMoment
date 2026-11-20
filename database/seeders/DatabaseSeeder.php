<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@holdmoment.test'],
            [
                'name' => 'Admin HOLD',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $defaults = [
            // Receipt 2 strip kembar — biar muncul di /admin/templates + bisa di-edit background-nya
            ['name' => 'Receipt', 'layout_type' => 'receipt', 'frame_image' => null, 'background_image' => null],
        ];

        foreach ($defaults as $row) {
            Template::firstOrCreate(['name' => $row['name']], $row);
        }

        // Background bawaan dari folder Aset/backgrounds/ (ikut git).
        // Disalin ke disk 'public' + dibuatkan baris templates agar muncul di
        // home showcase, /templates, dan /camera. Nama harus persis sama dengan
        // yang dipakai BackgroundSlotsSeeder agar slots ikut terisi.
        $backgrounds = [
            ['name' => 'Dream Bigger 2 x 2', 'layout_type' => '2x2', 'file' => 'dream-bigger-2x2.jpg'],
            ['name' => 'Good Moments 2 x 2', 'layout_type' => '2x2', 'file' => 'good-moments-2x2.jpg'],
            ['name' => 'PxT 2 x 2', 'layout_type' => '2x2', 'file' => 'pxt-2x2.jpg'],
            ['name' => 'Dream Bigger 2 x 3', 'layout_type' => '2x3', 'file' => 'dream-bigger-2x3.png'],
            ['name' => 'Good Moments 2 x 3', 'layout_type' => '2x3', 'file' => 'good-moments-2x3.png'],
            ['name' => 'PxT 2 x 3', 'layout_type' => '2x3', 'file' => 'pxt-2x3.png'],
        ];

        foreach ($backgrounds as $row) {
            $target = 'backgrounds/' . $row['file'];
            if (! Storage::disk('public')->exists($target)) {
                $source = base_path('Aset/backgrounds/' . $row['file']);
                if (is_file($source)) {
                    Storage::disk('public')->put($target, file_get_contents($source));
                }
            }
            if (Storage::disk('public')->exists($target)) {
                Template::firstOrCreate(
                    ['name' => $row['name']],
                    ['layout_type' => $row['layout_type'], 'background_image' => $target]
                );
            }
        }

        // Pixel Sky (pixel-art langit, 1333x2000) — lubang BESAR ala photostrip:
        // foto dominan, dekorasi (matahari/teks/pohon/menara/tanah) jadi bingkai.
        // Slots presisi hasil ukur piksel; jangan diubah tanpa mengukur ulang.
        $pixelSkyFile = 'backgrounds/pixel-to-reality-sky.png';
        if (! Storage::disk('public')->exists($pixelSkyFile)) {
            $pixelSkySource = base_path('Aset/backgrounds/pixel-to-reality-sky.png');
            if (is_file($pixelSkySource)) {
                Storage::disk('public')->put($pixelSkyFile, file_get_contents($pixelSkySource));
            }
        }
        if (Storage::disk('public')->exists($pixelSkyFile)) {
            Template::firstOrCreate(
                ['name' => 'Pixel Sky 2x2'],
                ['layout_type' => '2x2', 'background_image' => $pixelSkyFile,
                 'slots' => ['fw' => 1333, 'fh' => 2000, 'radius' => 30, 'holes' => [
                     [138, 390, 513, 342], [681, 390, 513, 342],
                     [138, 762, 513, 342], [681, 762, 513, 342],
                 ]]]
            );
            Template::firstOrCreate(
                ['name' => 'Pixel Sky 2x3'],
                ['layout_type' => '2x3', 'background_image' => $pixelSkyFile,
                 'slots' => ['fw' => 1333, 'fh' => 2000, 'radius' => 30, 'holes' => [
                     [324, 390, 327, 218], [681, 390, 327, 218],
                     [324, 638, 327, 218], [681, 638, 327, 218],
                     [324, 886, 327, 218], [681, 886, 327, 218],
                 ]]]
            );
        }

        // ===== 10 template Aesthetic (frame 1200x1800, 2 desain berdampingan) =====
        // Daftarkan tiap sisi (kiri/kanan) sebagai template terpisah -> 20 template total.
        // Setiap template mengikuti pola Express Yourself: 6 lubang (3 kiri + 3 kanan),
        // dupe=>true/dupeFactor=>2 -> 3 foto unik yang diduplikasi ke 6 slot (2 strip).
        // Output: 1200x1800 (dua strip sama) atau 600x1800 (satu strip).
        // Setiap file frame berisi 2 desain (kiri 0-600, kanan 600-1200).
        // Template kiri/dikenali sebagai "Nama (Kiri)" dan "Nama (Kanan)" dipilih di menu.
        $aestheticFrames = [
            'denim', 'lace-brown', 'pink-plaid', 'movie-ticket',
            'music-player', 'green-smile', 'pinky-promise', 'red-hearts',
            'blue-growing', 'receipt',
        ];
        foreach ($aestheticFrames as $af) {
            $keyMap = [
                'denim' => 'denim', 'lace-brown' => 'lace-brown', 'pink-plaid' => 'pink-plaid',
                'movie-ticket' => 'movie-ticket', 'music-player' => 'music-player',
                'green-smile' => 'green-smile', 'pinky-promise' => 'pinky-promise',
                'red-hearts' => 'red-hearts', 'blue-growing' => 'blue-growing', 'receipt' => 'receipt',
            ];
            $key = $keyMap[$af];

            $cfgPath = database_path("seeders/assets/frames-config/{$af}-slots.json");
            if (! is_file($cfgPath) || ! Storage::disk('public')->exists("frames/{$af}.png")) {
                continue;
            }
            $cfg = json_decode(file_get_contents($cfgPath), true);
            $leftSlots = $cfg['variants']['left']['slots'];
            $rightSlots = $cfg['variants']['right']['slots'];

            // Bangun holes array: 6 lubang, 3 kiri + 3 kanan (seperti Express Yourself),
            // dengan urutan: kiri atas, kanan atas, kiri tengah, kanan tengah, kiri bawah, kanan bawah.
            $holes = array_merge(
                // 3 lubang kiri (dari variant kiri config)
                array_map(function($s) { return [$s['est_px'][0], $s['est_px'][1], $s['est_px'][2], $s['est_px'][3]]; }, $leftSlots),
                // 3 lubang kanan (dari variant kanan config, offset sudah termasuk di est_px)
                array_map(function($s) { return [$s['est_px'][0], $s['est_px'][1], $s['est_px'][2], $s['est_px'][3]]; }, $rightSlots)
            );

            $templateLeftName = $af . ' (Kiri)';
            $templateRightName = $af . ' (Kanan)';

            // Template kiri
            Template::firstOrCreate(
                ['name' => $templateLeftName],
                ['layout_type' => '2x3', 'frame_image' => "frames/{$af}.png",
                 'slots' => ['fw' => 1200, 'fh' => 1800, 'radius' => 0, 'pad' => 0,
                              'dupe' => true, 'dupeFactor' => 2, 'holes' => $holes]]
            );
            // Template kanan
            Template::firstOrCreate(
                ['name' => $templateRightName],
                ['layout_type' => '2x3', 'frame_image' => "frames/{$af}.png",
                 'slots' => ['fw' => 1200, 'fh' => 1800, 'radius' => 0, 'pad' => 0,
                              'dupe' => true, 'dupeFactor' => 2, 'holes' => $holes]]
            );
        }

        $this->call(FrameTemplateSeeder::class);
    }
}