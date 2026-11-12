<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('admin.username');
        $email = config('admin.email');
        $password = config('admin.password');

        if (!$username || !$email || !$password) {
            throw new RuntimeException(
                'Set ADMIN_USERNAME, ADMIN_EMAIL, and ADMIN_PASSWORD before running AdminUserSeeder.'
            );
        }

        $admin = User::query()
            ->where('username', $username)
            ->orWhere('email', $email)
            ->first() ?? new User();

        $admin->fill([
            'username' => $username,
            'first_name' => 'Store',
            'last_name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ])->save();
    }
}
