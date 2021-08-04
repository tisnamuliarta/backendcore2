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
        $employee = ViewEmployee::select('*');
        if ($request->alias === 'stkpd') {
            $employee = $employee->where('WorkLocation', 'LIKE', '%JAKARTA%');
        }
        $employee = $employee->get();

        return response()->json([
            'rows' => $employee
        ]);
    }
}
