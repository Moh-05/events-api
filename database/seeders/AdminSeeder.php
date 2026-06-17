<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // First super_admin. Change the password after the first login.
        Admin::updateOrCreate(
            ['email' => 'admin@haflati.com'],
            [
                'name'     => 'Super Admin',
                'password' => '0000', // auto-hashed by the model cast
                'role'     => 'super_admin',
            ]
        );
    }
}
