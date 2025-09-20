<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view own profile',
            'edit own profile',
            'manage own fragrances',
            'manage own wearing logs',

            // Admin permissions
            'view all users',
            'manage users',
            'manage brands',
            'manage fragrances',
            'manage master data',
            'view admin dashboard',
            'view system logs',
            'manage ai settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // User role permissions
        $userRole->givePermissionTo([
            'view own profile',
            'edit own profile',
            'manage own fragrances',
            'manage own wearing logs',
        ]);

        // Admin role permissions (all permissions)
        $adminRole->givePermissionTo(Permission::all());
    }
}
