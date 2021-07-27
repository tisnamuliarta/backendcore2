<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterRolesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $options = json_decode($request->options);
        $year_local = date('Y');
        $pages = isset($options->page) ? (int)$options->page : 1;
        $filter = isset($request->filter) ? (string)$request->filter : $year_local;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "U_Name";
        $order = isset($options->sortDesc[0]) ? (string)$options->sortDesc[0] : "desc";
        $offset = ($pages - 1) * $row_data;

        $result = array();
        $query = Role::selectRaw("*, 'actions' as ACTIONS");

        $result["total"] = $query->count();

        $all_data = $query->offset($offset)
            ->orderBy($sorts, $order)
            ->limit($row_data)
            ->get();

        $all_rows = Role::all();
        $arr_rows = [];
        foreach ($all_rows as $item) {
            $arr_rows[] = [
                "U_Name" => $item->U_Name,
                "U_DocEntry" => $item->U_DocEntry,
            ];
        }

        $result = array_merge($result, [
            "rows" => $all_data,
            "all_data" => $arr_rows
        ]);
        return response()->json($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($this->validation($request)) {
            return response()->json([
                "errors" => true,
                "validHeader" => true,
                "message" => $this->validation($request)
            ]);
        }

        $form = $request->form;
        try {
            $data = new Role();
            $data->U_Name = $form['U_Name'];
            $data->U_Desc = $form['U_Desc'];
            $data->U_Locked = $form['U_Locked'];
            $data->save();

            return response()->json([
                "errors" => false,
                "message" => "Data inserted!"
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "errors" => true,
                "message" => $exception->getMessage(),
                "Trace" => $exception->getTrace()
            ]);
        }
    }

    /**
     * @param $request
     * @return false|string
     */
    protected function validation($request)
    {
        $messages = [
            'form.U_Name' => 'Name is required!',
            'form.U_Desc' => 'Description Status is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.U_Name' => 'required',
            'form.U_Desc' => 'required',
            'form.U_DeptEntry' => 'required',
        ], $messages);

        $string_data = "";
        if ($validator->fails()) {
            foreach (collect($validator->messages()) as $error) {
                foreach ($error as $items) {
                    $string_data .= $items . " \n  ";
                }
            }
            return $string_data;
        } else {
            return false;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): \Illuminate\Http\JsonResponse
    {
        $data = Role::where("U_DocEntry", "=", $id)->get();
        return response()->json([
            'rows' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if ($this->validation($request)) {
            return response()->json([
                "errors" => true,
                "validHeader" => true,
                "message" => $this->validation($request)
            ]);
        }

        $form = $request->form;
        try {
            $data = Role::where("U_DocEntry", "=", $id)->first();
            $data->U_Name = $form['U_Name'];
            $data->U_Desc = $form['U_Desc'];
            $data->U_Locked = $form['U_Locked'];
            $data->save();

            return response()->json([
                "errors" => false,
                "message" => "Data updated!"
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "errors" => true,
                "message" => $exception->getMessage(),
                "Trace" => $exception->getTrace()
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $details = Role::where("U_DocEntry", "=", $id)->first();
        if ($details) {
            Role::where("U_DocEntry", "=", $id)->delete();
            return response()->json([
                'message' => 'Row deleted'
            ]);
        }
        return response()->json([
            'message' => 'Row not found'
        ]);
    }
}
