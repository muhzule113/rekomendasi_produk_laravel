<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Kredensial admin tidak di-hardcode. Buat admin development dengan:
     *   php artisan app:create-admin
     * (membaca ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NAME dari environment)
     */
    public function run(): void
    {
        // Sengaja kosong: data demo/admin tidak boleh berisi password tetap di repo.
    }
}
