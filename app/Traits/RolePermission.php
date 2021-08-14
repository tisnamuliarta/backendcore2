<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

trait RolePermission
{
    /**
     * @param $request
     * @param string $suffix
     * @param string $insert_role
     */
    protected function generatePermission($request, string $suffix = '-index', string $insert_role = 'N')
    {
        $data = [
            'name' => $request->name,
            'app_name' => $request->app_name,
            'menu_name' => $request->menu_name,
            'parent_id' => $request->parent_id,
            'icon' => $request->icon,
            'route_name' => $request->route_name,
            'has_child' => $request->has_child,
            'has_route' => $request->has_route,
            'order_line' => $request->order_line,
            'is_crud' => $request->is_crud,
            'guard_name' => $request->guard_name
        ];

        if ($request->is_crud == 'Y') {
            $suffix = ['index', 'store', 'edits', 'erase'];

            foreach ($suffix as $value) {
                $data['name'] = $request->name . '-' . $value;

                $check = Permission::where('name', '=', $request->name . '-' . $value)->first();
                if ($check) {
                    $permission = Permission::where('id', '=', $check->id)->update($data);
                } else {
                    $permission = Permission::create($data);
                }

                $this->assignPermissionToRole($permission, $insert_role, $request);
            }
        } else {
            $data['name'] = $request->name . $suffix;
            $check = Permission::where('name', '=', $request->name . $suffix)->first();

            if ($check) {
                $permission = Permission::where('id', '=', $check->id)->update($data);
            } else {
                $permission = Permission::create($data);
            }

            $this->assignPermissionToRole($permission, $insert_role, $request);
        }
    }

    /**
     * @param $permission
     * @param $insert_role
     * @param $request
     */
    protected function assignPermissionToRole($permission, $insert_role, $request)
    {
        if ($insert_role == 'Y') {
            foreach ($request->role as $item) {
                $role = Role::where('id', '=', $item)->first();
                $permissions = Permission::where('id', '=', $permission->id)->first();

                $permissions->assignRole($role->name);

                $this->assignPermissionToUser($permission, $role);
            }
        }
    }

    /**
     * @param $permission
     * @param $role
     */
    protected function assignPermissionToUser($permission, $role)
    {
        $users = User::leftJoin('model_has_roles', 'model_has_roles.model_id', 'users.id')
            ->select('users.*')
            ->where('model_has_roles.role_id', '=', $role->id)
            ->get();

        if ($users) {
            foreach ($users as $user) {
                $user->givePermissionTo($permission->name);
            }
        }
    }

    /**
     * @param $role
     * @param $detail
     * @param $key
     */
    protected function actionStoreRolePermission($role, $detail, $key)
    {
        $permission = Permission::where('name', $detail['permission'] . '-' . $key)
            ->first();

        if ($permission) {
            if ($detail[$key] == 'Y') {
                $role->givePermissionTo($detail['permission'] . '-' . $key);
            } else {
                $role->revokePermissionTo($detail['permission'] . '-' . $key);
            }
        }
    }
}
