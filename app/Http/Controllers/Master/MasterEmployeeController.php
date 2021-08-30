<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ViewEmployee;
use Illuminate\Http\Request;

class MasterEmployeeController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $company = (isset($request->company)) ? $request->company : '';

        $employee = ViewEmployee::select('*');
        if ($request->alias == 'stkpd') {
            $employee = $employee->where('WorkLocation', 'LIKE', '%JAKARTA%');
        } elseif ($request->alias == 'sim' || $request->alias == 'sik' || $request->alias == 'srm' ||
            $request->alias == 'srk') {
            $employee = $employee->where('WorkLocation', 'LIKE', '%MOROWALI%')
                ->where('Company', 'LIKE', '%' . $request->user()->company . '%');
        }
        $employee = $employee->where('Company', 'LIKE', '%' . $company . '%')
            ->get();

        return response()->json([
            'rows' => $employee
        ]);
    }
}
