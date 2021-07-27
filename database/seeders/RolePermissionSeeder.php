<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superuser = Role::create([
            'name' => 'Superuser'
        ]);

        $admin_rsv = Role::create([
            'name' => 'Admin E-RESERVATION'
        ]);

        $permissions = [
            'user-list',
            'user-create',
            'user-edit',
            'user-delete',
            'reservation-list',
            'reservation-create',
            'reservation-edit',
            'reservation-delete',
            'approval-list',
            'approval-create',
            'approval-edit',
            'approval-delete'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $all_permissions = Permission::pluck('id', 'id')->all();

        $superuser->syncPermissions($all_permissions);

        $admin_resv_permission = Permission::whereIn('name', [
            'reservation-list',
            'reservation-create',
            'reservation-edit',
            'approval-list',
            'approval-create',
            'approval-edit',
        ])
            ->pluck('id', 'id')
            ->all();

        $admin_rsv->syncPermissions($admin_resv_permission);
    }
}
