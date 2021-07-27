<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resv\ReqItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ReqItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $options = json_decode($request->options);
        $year_local = date('Y');
        $pages = isset($options->page) ? (int)$options->page : 1;
        $filter = isset($request->filter) ? (string)$request->filter : $year_local;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "U_Description";
        $order = isset($options->sortDesc[0]) ? (string)$options->sortDesc[0] : "desc";
        $offset = ($pages - 1) * $row_data;

        $result = array();
        $query = ReqItem::selectRaw("*, 'actions' as ACTIONS");

        $result["total"] = $query->count();

        $all_data = $query->offset($offset)
            ->orderBy($sorts, $order)
            ->limit($row_data)
            ->get();

        $result = array_merge($result, [
            "rows" => $all_data,
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
            $data = new ReqItem();
            $data->U_Description = $form['U_Description'];
            $data->U_UoM = $form['U_UoM'];
            $data->U_Status = array_key_exists('U_Status', $form) ? $form['U_Status'] : 'Pending';
            $data->U_Remarks = $form['U_Remarks'];
            $data->U_Supporting = $form['U_Supporting'];
            $data->U_CreatedBy = $request->user()->U_UserID;
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
            'form.U_Description' => 'Name is required!',
            'form.U_UoM' => 'Description Status is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.U_Description' => 'required',
            'form.U_UoM' => 'required',
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
        $data = ReqItem::where("U_DocEntry", "=", $id)->get();
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
            $data = ReqItem::where("U_DocEntry", "=", $id)->first();
            $data->U_Description = $form['U_Description'];
            $data->U_UoM = $form['U_UoM'];
            $data->U_Status = array_key_exists('U_Status', $form) ? $form['U_Status'] : 'Pending';
            $data->U_Remarks = $form['U_Remarks'];
            $data->U_Supporting = $form['U_Supporting'];
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
        $details = ReqItem::where("U_DocEntry", "=", $id)->first();
        if ($details) {
            ReqItem::where("U_DocEntry", "=", $id)->delete();
            return response()->json([
                'message' => 'Row deleted'
            ]);
        }
        return response()->json([
            'message' => 'Row not found'
        ]);
    }
}
