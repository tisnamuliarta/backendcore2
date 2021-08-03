<?php

namespace App\Traits;

use App\Models\Application;
use App\Models\Role;
use App\Models\UserApp;
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
            $role_id = Role::where('id', '=', $role)->first();
            $permissions = DB::select('EXEC sp_role_permissions ' . $role_id->id);
            $user->assignRole($role_id);

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
        $apps = Application::where('app_name', $request->form['apps'])->first();
        foreach ($apps as $app) {
            UserApp::updateOrCreate([
                'user_id' => $user->id,
                'app_id' => $app
            ]);
        }
    }
}
