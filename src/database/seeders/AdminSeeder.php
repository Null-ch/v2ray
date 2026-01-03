<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            ['username' => 'cockpit_admin'],
            [
                'password' => Hash::make('47d49911108b5da06ac893f346c07984'),
            ]
        );
    }
}

