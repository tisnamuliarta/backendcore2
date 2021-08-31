<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Company;
use App\Traits\RolePermission;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class MenuSeeder extends Seeder
{
    use RolePermission;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            [
                'name' => 'Settings',
                'app_name' => 'All',
                'menu_name' => 'Settings',
                'parent_id' => 0,
                'icon' => 'mdi-cog',
                'route_name' => null,
                'has_child' => 'Y',
                'has_route' => 'N',
                'order_line' => '1',
                'is_crud' => 'N',
                'guard_name' => 'api'
            ],
            [
                'name' => 'Reservation',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Reservation',
                'parent_id' => 0,
                'icon' => 'mdi-store',
                'route_name' => null,
                'has_child' => 'Y',
                'has_route' => 'N',
                'order_line' => '2',
                'is_crud' => 'N',
                'guard_name' => 'api'
            ],
            [
                'name' => 'Inventory',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Inventory',
                'parent_id' => 0,
                'icon' => 'mdi-file-document',
                'route_name' => null,
                'has_child' => 'Y',
                'has_route' => 'N',
                'order_line' => '3',
                'is_crud' => 'N',
                'guard_name' => 'api'
            ],
            [
                'name' => 'Reservation Request',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Reservation Request',
                'parent_id' => 2,
                'icon' => 'mdi-file-document-multiple',
                'route_name' => '/reservation/request',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '2.1',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],
            [
                'name' => 'Reservation Approval',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Reservation Approval',
                'parent_id' => 2,
                'icon' => 'mdi-file-document-multiple',
                'route_name' => '/reservation/approval',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '2.2',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Request Item',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Request Item',
                'parent_id' => 2,
                'icon' => 'mdi-file-document-multiple',
                'route_name' => '/reservation/requestitem',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '2.3',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Users',
                'app_name' => 'All',
                'menu_name' => 'Users',
                'parent_id' => 1,
                'icon' => 'mdi-account-circle-outline',
                'route_name' => '/master/users',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '1.1',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Roles',
                'app_name' => 'All',
                'menu_name' => 'Roles',
                'parent_id' => 1,
                'icon' => 'mdi-file-tree',
                'route_name' => '/master/roles',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '1.2',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Permission',
                'app_name' => 'All',
                'menu_name' => 'Permission',
                'parent_id' => 1,
                'icon' => 'mdi-file-tree',
                'route_name' => '/master/permission',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '1.2',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Goods Issue',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Goods Issue',
                'parent_id' => 3,
                'icon' => 'mdi-file-document',
                'route_name' => '/inventory/goodissue',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '3.1',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Cancel Goods Issue',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Cancel Goods Issue',
                'parent_id' => 3,
                'icon' => 'mdi-file-document',
                'route_name' => '/inventory/cancelgi',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '3.2',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'View Goods Issue',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'View Goods Issue',
                'parent_id' => 3,
                'icon' => 'mdi-file-document',
                'route_name' => '/inventory/viewgi',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '3.3',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],

            [
                'name' => 'Master Item',
                'app_name' => 'E-RESERVATION',
                'menu_name' => 'Master Item',
                'parent_id' => 3,
                'icon' => 'mdi-file-document',
                'route_name' => '/inventory/items',
                'has_child' => 'N',
                'has_route' => 'Y',
                'order_line' => '3.3',
                'is_crud' => 'Y',
                'guard_name' => 'api'
            ],
        ];

        foreach ($permissions as $permission) {
            $this->generatePermission((object)$permission);
        }

        $list_apps = [
            [
                'app_name' => 'E-RESERVATION',
                'app_description' => 'E-RESERVATION',
                'app_url' => 'http://localhost:3000',
                'active' => 'Yes'
            ],
            [
                'app_name' => 'E-FORM',
                'app_description' => 'E-FORM',
                'app_url' => 'http://localhost:3000',
                'active' => 'Yes'
            ]
        ];

        foreach ($list_apps as $list_app) {
            Application::create($list_app);
        }

        Role::create([
            'name' => 'Superuser'
        ]);

        Role::create([
            'name' => 'Admin E-RESERVATION'
        ]);

        Company::create([
            'db_code' => 'IMIP_TEST_1217',
            'db_name' => 'IMIP_TEST_1217'
        ]);

        Company::create([
            'db_code' => 'IMIP_LIVE',
            'db_name' => 'IMIP'
        ]);


        $permissions = Permission::pluck('id', 'id')
            ->where('guard_name', '=', 'api');

        $superuser = Role::where('name', '=', 'Superuser')->first();
        $superuser->syncPermissions($permissions);


        $permission_resv = Permission::where('app_name', '=', 'E-RESERVATION')
            ->where('guard_name', '=', 'api')
            ->pluck('id', 'id');

        $admin_rsv = Role::where('name', '=', 'Admin E-RESERVATION')->first();
        $admin_rsv->syncPermissions($permission_resv);
    }
}
