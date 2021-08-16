<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ViewEmployee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MasterCompanyController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function division(Request $request)
    {
        $department = substr($request->user()->department, 0, 4);
        $all_division = ViewEmployee::where('Department', 'LIKE', '%' . $department . '%')
            ->select('Department')
            ->distinct()
            ->get();

        $arr_division = [];
        foreach ($all_division as $item) {
            $arr_division[] = $item->Department;
        }

        return response()->json([
            'all_division' => $arr_division
        ]);
    }
}
