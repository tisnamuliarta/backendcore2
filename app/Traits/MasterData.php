<?php

namespace App\Traits;

use App\Models\Application;
use App\Models\Role;
use App\Models\UserApp;
use App\Models\UserDivision;
use App\Models\UserWhs;
use Illuminate\Support\Facades\DB;

trait MasterData
{
    /**
     * @param $request
     * @param $user
     */
    protected function storeUserRole($request, $user)
    {
        $roles = $request->form['role'];
        foreach ($roles as $role) {
            $id = array_key_exists('id', (array)$role) ? $role['id'] : $role;
            $role_id = Role::where('id', '=', $id)->first();
            $permissions = DB::select('EXEC sp_role_permissions ' . $role_id->id);
            $user->assignRole($role_id->name);

            foreach ($permissions as $permission) {
                $this->actionStoreRolePermission($user, (array)$permission, 'index');
                $this->actionStoreRolePermission($user, (array)$permission, 'store');
                $this->actionStoreRolePermission($user, (array)$permission, 'edits');
                $this->actionStoreRolePermission($user, (array)$permission, 'erase');
            }
        }
    }

    /**
     * @param $request
     * @param $user
     */
    protected function storeUserApps($request, $user)
    {
        $apps = $request->form['apps'];
        foreach ($apps as $app) {
            if ($app) {
                $id = array_key_exists('id', (array)$app) ? $app['id'] : $app;
                UserApp::updateOrCreate([
                    'user_id' => $user->id,
                    'app_id' => $id
                ]);
            }
        }
    }

    /**
     * @param $request
     * @param $user
     */
    protected function storeUseDivision($request, $user)
    {
        $data = $request->form['division'];
        foreach ($data as $app) {
            if ($app) {
                $id = array_key_exists('name', (array)$app) ? $app['name'] : $app;
                UserDivision::updateOrCreate([
                    'user_id' => $user->id,
                    'division_name' => $id
                ]);
            }
        }
    }

    /**
     * @param $request
     * @param $user
     */
    protected function storeUseWhs($request, $user)
    {
        $data = $request->form['whs'];
        foreach ($data as $app) {
            if ($app) {
                $id = array_key_exists('name', (array)$app) ? $app['name'] : $app;
                UserWhs::updateOrCreate([
                    'user_id' => $user->id,
                    'db_code' => env('DB_SAP'),
                    'whs_code' => $id
                ]);
            }
        }
    }
}
