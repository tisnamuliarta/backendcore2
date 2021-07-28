<?php

namespace App\Http\Controllers;

use App\Models\Resv\ReservationHeader;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function homeData(Request $request): \Illuminate\Http\JsonResponse
    {
        $count_draft = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "-")->count();
        $count_pending = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "P")->count();
        $count_reject = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "N")->count();
        $count_approve = $this->copyQuery($request)->where("RESV_H.ApprovalStatus", "=", "Y")->count();

        return response()->json([
            "rows" => [
                [
                    "text" => "Draft",
                    'value' => $count_draft,
                ],
                [
                    "text" => "Pending",
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
        return ReservationHeader::where("Department", "=", $request->user()->department)
            ->where("RESV_H.CreatedBy", "=", $request->user()->username);
    }
}
