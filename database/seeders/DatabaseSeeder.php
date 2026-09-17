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
        $this->seedAdministrator();

        // Sample employees and attendance records are only useful while
        // developing. Production deployments (XAMPP) never seed fake records.
        if (app()->environment('local')) {
            $this->call(DemoAttendanceSeeder::class);
        }
    }

    /**
     * Create the first administrator account.
     */
    protected function seedAdministrator(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'admin@euro-mega.com']);

        if ($admin->exists) {
            $this->command?->info('Administrator account already exists: admin@euro-mega.com');

            return;
        }

        $admin->fill([
            'name' => 'Administrator',
            'employee_id' => 'ADMIN001',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ])->save();

        $this->command?->info('Administrator created: admin@euro-mega.com / password');
    }
}
