<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if ($password === null || $password === '') {
            if (app()->environment('local', 'testing')) {
                $password = 'password';
            } else {
                throw new \RuntimeException('ADMIN_PASSWORD must be set in .env before seeding the admin user.');
            }
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );
        $user->is_admin = true;
        $user->save();
    }
}
