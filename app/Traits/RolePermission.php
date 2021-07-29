<?php

namespace App\Traits;

use App\Models\Permission;

trait RolePermission
{
    /**
     * @param $request
     */
    protected function generatePermission($request)
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
            Permission::create($data);
        }
    }
}
