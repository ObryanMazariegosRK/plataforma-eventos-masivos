<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PasarelaSeeder::class);
        $this->call(RecintoSeeder::class);

        // Los usuarios viven en "usuarios" (no en la tabla "users" del esqueleto de Laravel).
        // Para un admin: php artisan usuario:crear-admin
    }
}
