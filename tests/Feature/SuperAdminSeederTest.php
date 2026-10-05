<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

test('the seeder creates the super admin from config', function () {
    config(['wms.admin.email' => 'admin@example.com', 'wms.admin.password' => 'secret-pass']);

    $this->seed(SuperAdminSeeder::class);

    $admin = User::where('email', 'admin@example.com')->sole();
    expect($admin->role)->toBe(Role::SuperAdmin)
        ->and($admin->company_id)->toBeNull()
        ->and(Hash::check('secret-pass', $admin->password))->toBeTrue();
});

test('running the seeder twice keeps one super admin', function () {
    config(['wms.admin.email' => 'admin@example.com', 'wms.admin.password' => 'secret-pass']);

    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', 'admin@example.com')->count())->toBe(1);
});

test('the seeder skips when credentials are missing', function () {
    config(['wms.admin.email' => null, 'wms.admin.password' => null]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('role', Role::SuperAdmin)->count())->toBe(0);
});
