<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create or update the platform owner from ADMIN_EMAIL / ADMIN_PASSWORD.
     */
    public function run(): void
    {
        $email = config('wms.admin.email');
        $password = config('wms.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD are empty: super admin not created.');

            return;
        }

        User::updateOrCreate(['email' => $email], [
            'company_id' => null,
            'name' => 'مدير المنصة',
            'password' => $password,
            'role' => Role::SuperAdmin,
            'is_active' => true,
        ]);
    }
}
