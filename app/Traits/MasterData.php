<?php

namespace App\Traits;

use App\Models\Application;
use App\Models\Role;
use App\Models\UserApp;

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
            $user->assignRole($role_id);
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
