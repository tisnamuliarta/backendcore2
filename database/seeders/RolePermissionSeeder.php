<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

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
    }
}
