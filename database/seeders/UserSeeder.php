<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('code', 'admin')->first();
        $supervisorRole = Role::where('code', 'supervisor')->first();
        $cashierRole = Role::where('code', 'cashier')->first();

        // 1. Default Admin
        User::updateOrCreate(
            ['email' => 'admin@kasirpro.local'],
            [
                'role_id' => $adminRole->id,
                'name' => 'Administrator Toko',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        // 2. Default Supervisor
        User::updateOrCreate(
            ['email' => 'supervisor@kasirpro.local'],
            [
                'role_id' => $supervisorRole->id,
                'name' => 'Supervisor Toko',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        // 3. Default Cashier
        User::updateOrCreate(
            ['email' => 'kasir@kasirpro.local'],
            [
                'role_id' => $cashierRole->id,
                'name' => 'Kasir 01',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }
}
