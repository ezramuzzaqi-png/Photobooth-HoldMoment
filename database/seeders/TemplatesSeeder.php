<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/templates_export.json')), true);
        $disk = Storage::disk('public');

        foreach ($rows as $row) {
            $frame = $row['frame_image'];

            if (! $disk->exists($frame)) {
                $source = database_path('seeders/assets/frames/' . basename($frame));
                if (is_file($source)) {
                    $disk->put($frame, fopen($source, 'r'));
                }
            }

            $template = Template::firstOrNew([
                'name' => $row['name'],
                'layout_type' => $row['layout_type'],
            ]);
            $template->forceFill($row)->save();
        }
    }
}