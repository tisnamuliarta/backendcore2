<?php

namespace App\Traits;

use App\Models\Permission;

trait RolePermission
{
    /**
     * @param $request
     * @param string $suffix
     */
    protected function generatePermission($request, string $suffix = '-index')
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
        ];

        if ($request->is_crud == 'Y') {
            $suffix = ['index', 'store', 'edits', 'erase'];

            foreach ($suffix as $value) {
                $data['name'] = $request->name . '-' . $value;

                Permission::create($data);
            }
        } else {
            $data['name'] = $request->name . $suffix;
            Permission::create($data);
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
