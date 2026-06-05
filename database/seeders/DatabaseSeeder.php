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
        // Admin Account (skip if exists)
        User::firstOrCreate(
            ['email' => 'admin@sawitkinabalu.com.my'],
            [
                'name' => 'Admin',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
            ]
        );

        // Sr. Assistant Livestock - Issue cases & PM Examination
        User::firstOrCreate(
            ['email' => 'srassistant.livestock@sawitkinabalu.com.my'],
            [
                'name' => 'Sr. Assistant Livestock',
                'password' => bcrypt('livestock123'),
                'role' => 'livestock',
            ]
        );

        // Sr. Assistant Security - Verify cases
        User::firstOrCreate(
            ['email' => 'srassistant.security@sawitkinabalu.com.my'],
            [
                'name' => 'Sr. Assistant Security',
                'password' => bcrypt('security123'),
                'role' => 'security',
            ]
        );

        // Supervisor Livestock - Check cases
        User::firstOrCreate(
            ['email' => 'supervisor.livestock@sawitkinabalu.com.my'],
            [
                'name' => 'Supervisor Livestock',
                'password' => bcrypt('supervisor123'),
                'role' => 'supervisor',
            ]
        );

        // Penyelia Security - Witness cases
        User::firstOrCreate(
            ['email' => 'penyelia.security@sawitkinabalu.com.my'],
            [
                'name' => 'Penyelia Security',
                'password' => bcrypt('penyelia123'),
                'role' => 'penyelia',
            ]
        );

        // Livestock Manager / OIC - Approve cases
        User::firstOrCreate(
            ['email' => 'livestock.manager@sawitkinabalu.com.my'],
            [
                'name' => 'Livestock Manager / OIC',
                'password' => bcrypt('manager123'),
                'role' => 'manager',
            ]
        );

        // Real records for weekly cattle return integration tests
        $this->call(WeeklyReturnRealDataSeeder::class);
    }
}
