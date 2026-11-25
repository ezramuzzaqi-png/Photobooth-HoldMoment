<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@holdmoment.test'],
            [
                'name' => 'Admin HOLD',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
            ]
        );

        $this->call(TemplatesSeeder::class);
    }
}