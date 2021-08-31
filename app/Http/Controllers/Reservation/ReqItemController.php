<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Traits\ConnectHana;
use Illuminate\Http\Request;
use App\Models\Resv\ReqItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReqItemController extends Controller
{
    use ConnectHana;

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
        $search_status = isset($request->searchStatus) ? (string)$request->searchStatus : "";
        $offset = ($pages - 1) * $row_data;

        if ($search_status == 'All') {
            $search_status = '';
        }

        $result = array();
        $db_name = (env('DB_SAP') !== null) ? env('DB_SAP') : 'IMIP_TEST_1217';
        $connect = $this->connectHana();
        $own_db_name = (env('LARAVEL_ODBC_USERNAME') !== null) ? env('LARAVEL_ODBC_USERNAME') : 'IMIP_ERESV';

        $sql = '
                        SELECT DISTINCT T0.*,
                            T2."ItemCode",
                            T2."ItemName",
                            CASE
                                WHEN T2."ItemCode" IS NULL THEN \'Pending\'
                                ELSE \'Approved\'
                            END AS "U_DocStatus"
                        FROM ' . $own_db_name . '."U_OITM" As T0
                        LEFT JOIN ' . $db_name . '."OITM" AS T2 ON T2."U_ItemReqNo" = T0."U_DocEntry"
                        WHERE (
                            CASE
                                WHEN T2."ItemCode" IS NULL THEN \'Pending\'
                                ELSE \'Approved\'
                            END
                        ) LIKE \'%' . $search_status . '%\'
                        LIMIT \''.$row_data.'\'
                        OFFSET \''.$offset.'\'
                    ';
        // dd($sql);
        $rs = odbc_exec($connect, $sql);

        if (!$rs) {
            exit("Error in SQL");
        }

        $arr = [];
        while (odbc_fetch_row($rs)) {
            $arr[] = [
                "U_Description" => odbc_result($rs, "U_Description"),
                "U_UoM" => odbc_result($rs, "U_UoM"),
                "U_Status" => odbc_result($rs, "U_Status"),
                "U_Remarks" => odbc_result($rs, "U_Remarks"),
                "U_Supporting" => odbc_result($rs, "U_Supporting"),
                "U_CreatedBy" => odbc_result($rs, "U_CreatedBy"),
                "U_DocEntry" => odbc_result($rs, "U_DocEntry"),
                "U_Comments" => odbc_result($rs, "U_Comments"),
                "U_CreatedAt" => odbc_result($rs, "U_CreatedAt"),
                "U_ItemType" => odbc_result($rs, "U_ItemType"),
                "ItemCode" => odbc_result($rs, "ItemCode"),
                "ItemName" => odbc_result($rs, "ItemName"),
                "U_DocStatus" => odbc_result($rs, "U_DocStatus"),
                "count_attachment" => Attachment::where('source_id', '=', odbc_result($rs, "U_DocEntry"))
                    ->where('type', '=', 'item')
                    ->count()
            ];
        }

        $sql_count = $sql;
        $rs2 = odbc_exec($connect, $sql_count);

        if (!$rs2) {
            exit("Error in SQL");
        }

        $items = 0;
        while ($row = odbc_fetch_array($rs2)) {
            $items++;
        }

        $result["total"] = $items;


        $result = array_merge($result, [
            "rows" => $arr,
            'documentStatus' => [
                'All', 'Pending', 'Approved'
            ],
            'filter' => [
                'Item Name', 'Item Code', 'Specification', 'UoM', 'Created By'
            ]
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
            $data->U_ItemType = $form['U_ItemType'];
            $data->U_UoM = $form['U_UoM'];
            $data->U_Status = array_key_exists('U_Status', $form) ? $form['U_Status'] : 'Pending';
            $data->U_Remarks = $form['U_Remarks'];
            $data->U_Supporting = $form['U_Supporting'];
            $data->U_CreatedBy = $request->user()->name;
            $data->U_DocEntry = $this->generateDocNum(date('Y-m-d H:i:s'));
            $data->save();

            return $this->success([
                "errors" => false,
            ], "Data inserted!");
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), '422', [
                "errors" => true,
                "Trace" => $exception->getTrace()
            ]);
        }
    }

    /**
     * @param $sysDate
     * @return string
     */
    protected function generateDocNum($sysDate): string
    {
        $data_date = strtotime($sysDate);
        $year_val = date('y', $data_date);
        $full_year = date('Y', $data_date);
        $month = date('m', $data_date);
        $day_val = date('j', $data_date);
        $end_date = date('t', $data_date);

        if ($day_val == 1) {
            return (int)$year_val . $month . sprintf("%04s", "1");
        } else {
            $first_date = "$full_year-$month-01 00:00:00";
            $second_date = "$full_year-$month-$end_date 00:00:00";
            $doc_num = ReqItem::selectRaw('IFNULL("U_DocEntry", 0) as DocNum')
                ->whereBetween(DB::raw('"U_CreatedAt"'), [$first_date, $second_date])
                ->orderBy("U_DocEntry", "DESC")
                ->first();
            $number = (empty($doc_num)) ? '00000000' : $doc_num->DOCNUM;
            $clear_doc_num = (int)substr($number, 4, 7);
            $number = $clear_doc_num + 1;
            return (int)$year_val . $month . sprintf("%04s", $number);
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
            'form.U_ItemType' => 'Item Type is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.U_Description' => 'required',
            'form.U_UoM' => 'required',
            'form.U_ItemType' => 'required',
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
            DB::connection('laravelOdbc')
                ->table('U_OITM')
                ->where("U_DocEntry", "=", $form['U_DocEntry'])
                ->update([
                    'U_ItemType' => $form['U_ItemType'],
                    'U_Description' => $form['U_Description'],
                    'U_UoM' => $form['U_UoM'],
                    'U_Status' => array_key_exists('U_Status', $form) ? $form['U_Status'] : 'Pending',
                    'U_Remarks' => $form['U_Remarks'],
                    'U_Supporting' => $form['U_Supporting'],
                ]);

            return $this->success([
                "errors" => false,
            ], "Data updated!");
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), '422', [
                "errors" => true,
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
