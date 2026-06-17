<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$users = [
    [
        'name' => 'Admin',
        'email' => 'admin@sawitkinabalu.com.my',
        'password' => Hash::make('admin123'),
        'role' => 'admin',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Sr. Assistant Livestock',
        'email' => 'srassistant.livestock@sawitkinabalu.com.my',
        'password' => Hash::make('livestock123'),
        'role' => 'livestock',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Sr. Assistant Security',
        'email' => 'srassistant.security@sawitkinabalu.com.my',
        'password' => Hash::make('security123'),
        'role' => 'security',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Supervisor Livestock',
        'email' => 'supervisor.livestock@sawitkinabalu.com.my',
        'password' => Hash::make('supervisor123'),
        'role' => 'supervisor',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Penyelia Security',
        'email' => 'penyelia.security@sawitkinabalu.com.my',
        'password' => Hash::make('penyelia123'),
        'role' => 'penyelia',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Livestock Manager / OIC',
        'email' => 'livestock.manager@sawitkinabalu.com.my',
        'password' => Hash::make('manager123'),
        'role' => 'manager',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ],
];

foreach ($users as $user) {
    $exists = DB::table('users')->where('email', $user['email'])->exists();

    if ($exists) {
        echo "SKIP: {$user['email']} (already exists)\n";
        continue;
    }

    DB::table('users')->insert($user);
    echo "INSERTED: {$user['email']} [{$user['role']}]\n";
}

echo "\nDone.\n";
