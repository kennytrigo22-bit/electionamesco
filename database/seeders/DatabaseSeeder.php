<?php

namespace Database\Seeders;

use App\Models\User;
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
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@amesco.test',
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'name' => 'Président CEI',
            'email' => 'pr.cei@amesco.test',
            'role' => 'admin',
            'fonction' => 'PR_CEI',
        ]);

        User::factory()->create([
            'name' => 'Président Bureau Exécutif',
            'email' => 'pr.be@amesco.test',
            'role' => 'admin',
            'fonction' => 'PR_BE',
        ]);
    }
}
