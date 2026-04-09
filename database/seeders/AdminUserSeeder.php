<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['mail' => 'admin@dimage.cl'],
            [
                'name'      => 'Administrador',
                'password'  => Hash::make('password'),
                'username'  => 'admin',
                'mail'      => 'admin@dimage.cl',
                'telephone' => '',
            ]
        );

        $admin->assignRole('admin');
    }
}
