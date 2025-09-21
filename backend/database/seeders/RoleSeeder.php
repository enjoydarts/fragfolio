<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ロールの作成
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // 権限の作成
        $permissions = [
            'view_admin_panel',
            'manage_users',
            'manage_fragrances',
            'manage_brands',
            'view_analytics',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 管理者ロールに全権限を付与
        $adminRole->givePermissionTo(Permission::all());

        // 一般ユーザーロールには基本権限のみ付与（必要に応じて追加）
        $userRole->givePermissionTo([]);
    }
}
