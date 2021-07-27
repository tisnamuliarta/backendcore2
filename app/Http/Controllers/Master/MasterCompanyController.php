<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MasterCompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $options = json_decode($request->options);
        $year_local = date('Y');
        $pages = isset($options->page) ? (int)$options->page : 1;
        $filter = isset($request->filter) ? (string)$request->filter : $year_local;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 1000;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "U_DbCode";
        $order = isset($options->sortDesc[0]) ? "DESC" : "ASC";

        $search = isset($request->q) ? (string)$request->q : "";
        $type = isset($request->type) ? $request->type : null;
        $select_data = isset($request->selectData) ? (string)$request->selectData : "DocNum";
        $offset = ($pages - 1) * $row_data;
        $username = $request->user()->U_UserCode;

        $result = array();
        $query = Company::selectRaw('"U_DbCode", "U_DbName",
                ROW_NUMBER() OVER (PARTITION BY "U_DbCode" ORDER BY "U_DbCode" DESC) AS row_num')
            ->orderBy($sorts, $order);
        if ($type == 'userCompany') {
            $user = isset($request->user) ? json_decode($request->user) : null;
            $query->whereRaw('"U_DbCode" NOT IN
            (SELECT "U_DbCode" FROM "OUSR_COMP" WHERE "U_UserID"=\''.$user->U_UserID.'\' )');
        }

        $result["total"] = $query->count();
        $all_data = $query->offset($offset)
            ->limit($row_data)
            ->get();

        $result = array_merge($result, [
            "rows" => $all_data,
            "department" => $all_data,
            "filter" => ['All'],
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
            $data = new Company();
            $data->U_DbName = $form['U_DbName'];
            $data->U_DbCode = $form['U_DbCode'];
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
            'form.U_DbCode' => 'DB Code is required!',
            'form.U_DbName' => 'DB Name is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.U_DbCode' => 'required',
            'form.U_DbName' => 'required',
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
        $data = Company::where("U_DbCode", "=", $id)->get();
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
            $data = Company::where("U_DbCode", "=", $id)->first();
            $data->U_Name = $form['U_Name'];
            $data->U_DbName = $form['U_DbName'];
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
        $details = Company::where("U_DbCode", "=", $id)->first();
        if ($details) {
            Company::where("U_DbCode", "=", $id)->delete();
            return response()->json([
                'message' => 'Row deleted'
            ]);
        }
        return response()->json([
            'message' => 'Row not found'
        ]);
    }
}
