<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Creates the first super admin from ADMIN_EMAIL / ADMIN_PASSWORD. A random password is printed if none is given. */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        if (! $email) {
            $this->command?->warn('ADMIN_EMAIL not set: skipping admin user.');

            return;
        }
        if (User::query()->where('email', $email)->exists()) {
            return;
        }
        $password = env('ADMIN_PASSWORD') ?: Str::password(20);
        $user = User::query()->create([
            'name' => 'Admin', 'nickname' => env('ADMIN_NICKNAME', 'admin'), 'email' => $email, 'password' => $password,
        ]);
        $user->forceFill(['role' => Role::SuperAdmin, 'email_verified_at' => now()])->save();
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->info("Super admin created: $email / $password (change it after first login)");
        }
    }
}
