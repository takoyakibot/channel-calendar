<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $emails = array_filter(array_map('trim', explode(',', config('app.admin_emails', ''))));

        if (empty($emails)) {
            $this->command->warn('ADMIN_EMAILS is not set. Skipping admin user seeding.');
            return;
        }

        foreach ($emails as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => explode('@', $email)[0]],
            );
            $user->is_admin = true;
            $user->save();

            $this->command->info("Admin: {$email}");
        }
    }
}
