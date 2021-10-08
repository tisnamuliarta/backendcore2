<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ViewEmployee;
use App\Models\ViewEmployeeLeave;
use App\Traits\RolePermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterEmployeeController extends Controller
{
    use RolePermission;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $company = (isset($request->company)) ? $request->company : '';
        $plucked = '';
        $employee = ViewEmployee::select('*');
        if ($request->alias == 'stkpd') {
            $employee = $employee->where('WorkLocation', 'LIKE', '%JAKARTA%')
                ->get();
        } elseif ($request->alias == 'sim' || $request->alias == 'sik' || $request->alias == 'srm' ||
            $request->alias == 'srk') {
            $employee = $employee->where('Company', 'LIKE', '%' . $request->user()->company . '%')
                ->where('WorkLocation', 'LIKE', $request->user()->location)
                ->where('Department', 'LIKE', '%' . $request->user()->department . '%');

            if ($request->user()->hasAnyRole(['Personal'])) {
                $employee = $employee->where('Name', '=', $request->user()->name);
            }
        } elseif ($request->alias == 'fsr' || $request->alias == 'gsv') {
            $employee = $employee->where('WorkLocation', 'LIKE', '%JAKARTA%')
                ->where('Company', 'LIKE', '%' . $request->user()->company . '%');
        } else {
            if(!$request->user()->role('Superuser')) {
                $employee = $employee->limit(10);
            }
        }

        if ($request->alias != 'stkpd') {
            $employee = $employee->where('Company', 'LIKE', '%' . $company . '%')
                ->get();

            $plucked = $employee->pluck('Name');
        }

        return response()->json([
            'rows' => $employee,
            'plucked' => $plucked
        ]);
    }

    /**
     * @param Request $request
     * @param $nik
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function leave(Request $request, $nik)
    {
        $date = Carbon::now()->subDays(100)->format('Ymd');
        $employee_leave = ViewEmployeeLeave::select("DocumentReferenceNumber",
            DB::raw("concat(FORMAT(DateFrom, 'dd MMMM yyyy'), ' - ', FORMAT(DateTo, 'dd MMMM yyyy')) as date_from_to"),
            'Jenis Cuti AS jenisCuti'
        )
            ->where('Nik', '=', $nik)
            ->where(DB::raw('CAST(DateFrom AS Date)'), '>=', $date)
            ->orderBy('DateFrom', 'desc')
            ->get();

        return $this->success($employee_leave);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function employeeByName(Request $request)
    {
        $name = $request->name;
        $employee = ViewEmployee::where('Name', '=', $name)->first();
        if ($employee) {
            return response()->json([
                'IdNumber' => $employee->IdNumber,
                'Company' => $employee->Company,
            ]);
        } else {
            return response()->json('');
        }
    }
}
