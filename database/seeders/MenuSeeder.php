<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Company;
use App\Models\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Menu::create([
            'menu' => 'Settings',
            'parent_id' => 0,
            'icon' => 'mdi-cog',
            'icon_alt' => 'mdi-menu-down',
            'has_child' => 'Y',
            'has_route' => 'N',
            'order_line' => '1'
        ]);

        Menu::create([
            'menu' => 'My Reservation',
            'app_name' => 'E-RESERVATION',
            'parent_id' => 0,
            'icon' => 'mdi-menu-up',
            'icon_alt' => 'mdi-menu-down',
            'has_child' => 'Y',
            'has_route' => 'N',
            'order_line' => '2'
        ]);

        Menu::create([
            'menu' => 'Inventory',
            'app_name' => 'E-RESERVATION',
            'parent_id' => 0,
            'icon' => 'mdi-file-document',
            'icon_alt' => 'mdi-menu-down',
            'has_child' => 'Y',
            'has_route' => 'N',
            'order_line' => '3'
        ]);

        Menu::create([
            'menu' => 'Users',
            'parent_id' => 1,
            'icon' => 'mdi-account-circle-outline',
            'route_name' => '/master/user',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '1.1'
        ]);

        Menu::create([
            'menu' => 'Menu',
            'parent_id' => 1,
            'icon' => 'mdi-file-tree',
            'route_name' => '/master/menu',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '1.2'
        ]);

        Menu::create([
            'menu' => 'Master Role',
            'parent_id' => 1,
            'icon' => 'mdi-file-tree',
            'route_name' => '/master/role',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '1.3'
        ]);

        Menu::create([
            'menu' => 'Reservation Request',
            'app_name' => 'E-RESERVATION',
            'parent_id' => 2,
            'icon' => 'mdi-file-document-multiple',
            'route_name' => '/reservations',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '2.1'
        ]);

        Menu::create([
            'menu' => 'Approval List',
            'app_name' => 'E-RESERVATION',
            'parent_id' => 2,
            'icon' => 'mdi-file-document-multiple',
            'route_name' => '/approval-list',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '2.2'
        ]);

        Menu::create([
            'menu' => 'Good Issues',
            'app_name' => 'E-RESERVATION',
            'parent_id' => 3,
            'icon' => 'mdi-file-document',
            'route_name' => '/cancelgoodissues',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '3.1'
        ]);

        Menu::create([
            'menu' => 'Item Master Data',
            'app_name' => 'E-RESERVATION',
            'parent_id' => 3,
            'icon' => 'mdi-account-circle-outline',
            'route_name' => '/items',
            'has_child' => 'N',
            'has_route' => 'Y',
            'order_line' => '3.2'
        ]);

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


        Company::create([
            'db_code' => 'IMIP_TEST_1217',
            'db_name' => 'IMIP_TEST_1217'
        ]);

        Company::create([
            'db_code' => 'IMIP_LIVE',
            'db_name' => 'IMIP'
        ]);


        $permissions = [
            'role-list',
            'role-create',
            'role-edit',
            'role-delete',
            'permission-list',
            'permission-create',
            'permission-edit',
            'permission-delete'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $permissions = Permission::whereIn('name', [
            'role-list',
            'role-create',
            'role-edit',
            'role-delete',
            'app-list',
            'app-create',
            'app-edit',
            'app-delete',
            'permission-list',
            'permission-create',
            'permission-edit',
            'permission-delete'
        ])
            ->pluck('id', 'id')
            ->all();

        $superuser = Role::where('name', '=', 'Superuser')->first();
        $superuser->syncPermissions($permissions);
    }
}
