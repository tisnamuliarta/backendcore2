<?php

namespace App\Http\Controllers;

use App\Models\Resv\ReservationHeader;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * @return string
     */
    public function downloadManual()
    {
        return response()->json([
            'url' => url('/attachment/E-RESV-MANUAL.zip')
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function homeData(Request $request): \Illuminate\Http\JsonResponse
    {
        $count_draft = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "-")->count();
        $count_pending = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "W")->count();
        $count_reject = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "N")->count();
        $count_approve = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "Y")->count();

        return response()->json([
            "rows" => [
                [
                    "text" => "Draft",
                    'value' => $count_draft,
                ],
                [
                    "text" => "Waiting",
                    'value' => $count_pending,
                ],
                [
                    "text" => "Rejected",
                    'value' => $count_reject,
                ],
                [
                    "text" => "Approved",
                    'value' => $count_approve,
                ]
            ]
        ]);
    }


    /**
     * @param $request
     *
     * @return mixed
     */
    protected function copyQuery($request)
    {
        return ReservationHeader::where("RESV_H.CreatedBy", "=", $request->user()->username);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function menus(Request $request)
    {
        $permissions = $request->user()
            ->getAllPermissions()
            ->where('parent_id', '=', '0')
            ->whereIn('app_name', [$request->appName, 'All']);

        $array = [];
        foreach ($permissions as $permission) {
            $children = $request->user()
                ->getAllPermissions()
                ->where('parent_id', '=', $permission->id)
                ->whereIn('app_name', [$request->appName, 'All']);

            $array_child = [];
            $prev_name = '';
            foreach ($children as $child) {
                if ($prev_name != $child->menu_name) {
                    $array_child[] = [
                        'menu' => $child->menu_name,
                        'icon' => $child->icon,
                        'route_name' => $child->route_name,
                    ];

                    $prev_name = $child->menu_name;
                }
            }

            $array[] = [
                'menu' => $permission->menu_name,
                'icon' => $permission->icon,
                'route_name' => $permission->route_name,
                'children' => $array_child
            ];
        }
        return $this->success([
            'menus' => $array
        ]);
    }
}
