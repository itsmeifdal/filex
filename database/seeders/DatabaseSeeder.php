<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AccreditationStructureSeeder::class);

        $configuredEmail = config('app.seed_admin.email');
        $configuredPassword = config('app.seed_admin.password');
        $email = is_string($configuredEmail) ? $configuredEmail : (app()->isLocal() ? 'admin@akreditasi.test' : null);
        $password = is_string($configuredPassword) ? $configuredPassword : (app()->isLocal() ? 'password' : null);
        $configuredName = config('app.seed_admin.name');

        if ($email && $password) {
            User::query()->updateOrCreate(['email' => $email], [
                'name' => is_string($configuredName) ? $configuredName : 'Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}
