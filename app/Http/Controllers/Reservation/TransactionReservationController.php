<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Traits\Approval;
use App\Traits\ConnectHana;
use App\Models\Resv\ReservationDetails;
use App\Models\Resv\ReservationHeader;
use App\Models\User;
use App\Models\UserCompany;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\UserWhs;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Jobs\RemoveAttachment;
use Illuminate\Support\Str;

class TransactionReservationController extends Controller
{
    use ConnectHana, Approval;

    /**
     * TransactionReservationController constructor.
     */
    public function __construct()
    {
        $this->middleware(['direct_permission:Reservation Request-index'])->only(['index', 'show', 'maxDocResv']);
        $this->middleware(['direct_permission:Reservation Request-store'])->only('store');
        $this->middleware(['direct_permission:Reservation Request-edits'])->only('update');
        $this->middleware(['direct_permission:Reservation Request-erase'])->only('destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
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
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "DocNum";
        $order = isset($options->sortDesc[0]) ? "ASC" : "DESC";
        $search = isset($request->search) ? (string)$request->search : "";
        $search_status = isset($request->searchStatus) ? (string)$request->searchStatus : "";
        $select_data = isset($request->searchItem) ? (string)$request->searchItem : "DocNum";
        $offset = ($pages - 1) * $row_data;
        $username = $request->user()->username;
        $user_id = $request->user()->username;
        // dd($user_id);

        // $db_name = env('DB_SAP');
        $schema = (env("DB_SCHEMA") !== null) ? env("DB_SCHEMA") : 'IMIP_ERESV';

        $result = array();
        $query = ReservationHeader::select(
            "RESV_H.*"
        )
            ->when($search, function ($query) use (
                $select_data,
                $search
            ) {
                $data_query = $query;
                switch ($select_data) {
                    case 'DocNum':
                        $data_query->whereRaw('"RESV_H"."DocNum" LIKE( \'%' . $search . '%\') ');
                        break;
                    case 'Company':
                        $data_query->whereRaw('"RESV_H"."Company" LIKE( \'%' . $search . '%\') ');
                        break;
                    case 'Req Name':
                        $data_query->whereRaw('"RESV_H"."RequesterName" LIKE( \'%' . $search . '%\') ');
                        break;
                    case 'Req Type':
                        $data_query->whereRaw('"RESV_H"."RequestType" LIKE( \'%' . $search . '%\') ');
                        break;
                    case 'Req Date':
                        $data_query->whereRaw('"RESV_H"."RequiredDate" LIKE( \'%' . $search . '%\') ');
                        break;
                    case 'App Status':
                        $data_query->whereRaw('"RESV_H"."ApprovalStatus" LIKE( \'%' . $search . '%\') ');
                        break;
                }

                return $data_query;
            })
            ->when($search_status, function ($query) use ($search_status) {
                $data_query = $query;
                switch ($search_status) {
                    case '-':
                        $data_query->whereRaw('"RESV_H"."ApprovalStatus" = \'-\' ');
                        break;
                    case 'Waiting':
                        $data_query->whereRaw('"RESV_H"."ApprovalStatus" = \'W\' ');
                        break;
                    case 'Approved':
                        $data_query->whereRaw('"RESV_H"."ApprovalStatus" = \'Y\' ');
                        break;
                    case 'Rejected':
                        $data_query->whereRaw('"RESV_H"."ApprovalStatus" = \'N\' ');
                        break;
                    case 'All':
                        $data_query->whereRaw('"RESV_H"."ApprovalStatus" LIKE \'%%\' ');
                        break;
                }
                return $data_query;
            })
            ->when($username, function ($query) use (
                $username,
                $user_id
            ) {
                $data_query = $query;
                if ($user_id != 'manager') {
                    $data_query->where("RESV_H.CreatedBy", "=", $user_id);
                }
                return $data_query;
            });

        $result["total"] = $query->count();

        $all_result = $query->offset($offset)
            ->limit($row_data)
            ->get();
        //dd($all_result);

        $single_data = [];
        $db_name = '';
        foreach ($all_result as $key => $value) {
            $db_name = $value->Company;
            $pr_no = ($value->SAP_PRNo) ? $value->SAP_PRNo : null;
            $single_data[] = $query->select(
                "RESV_H.*",
                DB::raw('( SELECT STRING_AGG(X."PR_NO", \', \')
                    FROM (

                        SELECT DISTINCT Q0."DocNum" AS "PR_NO"
                        FROM ' . $db_name . '."OPRQ" Q0
                        LEFT JOIN ' . $db_name . '."PRQ1" Q1 ON Q0."DocEntry" = Q1."DocEntry"
                        WHERE Q1."U_DGN_IReqId"  = RESV_H."SAP_GIRNo"  AND Q0."CANCELED" =\'N\'

                    ) AS X
                 )  AS "SAP_PRNo"'),
                DB::raw('(
                    SELECT "U_DocNum"
                    FROM ' . $db_name . '."@DGN_EI_OIGR"
                    where ' . $db_name . '."@DGN_EI_OIGR"."DocNum" = RESV_H."SAP_GIRNo") AS "SAP_GIRNo"'),
                DB::raw('
                    (
                        SELECT STRING_AGG(X."DocNum", \', \') as "GI_No"
                         FROM
                            ( SELECT DISTINCT T0."DocNum"
                                FROM ' . $db_name . '."@DGN_EI_OIGR" G0
                                 LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" G1 ON G0."DocEntry" = G1."DocEntry"
                                 LEFT JOIN ' . $db_name . '.IGE1 T1
                                           ON T1."U_DGN_IReqId" = G1."DocEntry" AND T1."U_DGN_IReqLineId" = G1."LineId"
                                 LEFT JOIN ' . $db_name . '.OIGE T0 ON T1."DocEntry" = T0."DocEntry"
                                WHERE G0."DocEntry" = RESV_H."SAP_GIRNo"
                            )  AS X
                      ) AS  "SAP_GINo"
                '),
                "RESV_H.Company as U_DbCode",
                DB::raw(
                    '(
                       SELECT STRING_AGG(X."PONum", \', \') AS "PONum"
                       FROM (
                                SELECT DISTINCT T1."DocNum" AS "PONum"
                                FROM ' . $db_name . '."POR1" AS T0
                                LEFT JOIN ' . $db_name . '."OPOR" AS T1 ON T0."DocEntry" = T1."DocEntry"
                                 WHERE T0."U_DGN_IReqId"  = RESV_H."SAP_GIRNo"
                                  AND T1."CANCELED" = \'N\'
                            ) AS X
                   ) AS "PONum"'
                ),
                DB::raw('(
                       SELECT STRING_AGG(X."GRPO_NO", \', \') AS "GRPO_NO"
                       FROM (
                                 SELECT DISTINCT T1."DocNum" AS "GRPO_NO"
                                FROM ' . $db_name . '."PDN1" AS T0
                                LEFT JOIN ' . $db_name . '."OPDN" AS T1 ON T0."DocEntry" = T1."DocEntry"
                                 WHERE T0."U_DGN_IReqId"  = RESV_H."SAP_GIRNo"
                                  AND T1."CANCELED" = \'N\'
                            ) AS X
                   ) AS "GRPONum"
                   '),
                DB::raw('(
                    SELECT STRING_AGG(X."TrfNo",\', \') AS "SAP_TrfNo"
                    FROM
                    (
                        SELECT DISTINCT  T2."DocNum" AS "TrfNo"
                        FROM ' . $schema . '."RESV_H" T0
                        LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" G1 ON T0."SAP_GIRNo" = G1."DocEntry"
                        LEFT JOIN  ' . $db_name . '."@DGN_EI_OIGR" G0 ON G0."DocEntry" = G1."DocEntry"
                        LEFT JOIN ' . $db_name . '."WTR1" T1  ON G1."DocEntry" = T1."U_DGN_IReqId" AND  T1."U_DGN_IReqLineId" = G1."LineId"
                        LEFT JOIN ' . $db_name . '."OWTR" T2 ON T1."DocEntry" = T2."DocEntry"
                        WHERE T0."U_DocEntry" = ' . $schema . '.RESV_H."U_DocEntry"
                    ) X
                ) AS "SAP_TrfNo"'),
                DB::raw('RESV_H."RequesterName" AS "RequestName"'),
                // DB::raw('CONCAT(OUSR_H."U_Division", CONCAT( \'/\', OUSR_H."U_Department")) AS "Departments"'),
                DB::raw('
                    CASE
                        WHEN RESV_H."DocStatus" = \'D\' THEN \'Draft\'
                        ELSE (
                            SELECT CASE
                                when G0."Status" = \'O\' THEN \'Open\'
                                when G0."Status" = \'C\' AND G0."Canceled" = \'Y\' THEN \'Cancel\'
                                ELSE \'Closed\'
                                END AS "GIR_status"
                            FROM ' . $db_name . '."@DGN_EI_OIGR" G0
                            WHERE G0."DocEntry" = RESV_H."SAP_GIRNo"
                        )
                    END AS "DocumentStatus"
                '),
                DB::raw('
                    CASE
                        WHEN RESV_H."ApprovalStatus" = \'W\' THEN \'Waiting\'
                        WHEN RESV_H."ApprovalStatus" = \'P\' THEN \'Pending\'
                        WHEN RESV_H."ApprovalStatus" = \'N\' THEN \'Rejected\'
                        WHEN RESV_H."ApprovalStatus" = \'Y\' THEN \'Approved\'
                        WHEN RESV_H."ApprovalStatus" = \'-\' THEN \'-\'
                    END AS "AppStatus"
                '),
                DB::raw('\'action\' AS "Action"'),
            );
        }

        $all_data = $query->orderBY('RESV_H.DocNum', $order)
            ->offset($offset)
            ->limit($row_data)
            ->get();

        $filter = ["DocNum", "Company", "Req Name", "Req Type", "Req Date", "App Status"];

        $document_status = ['All', 'Waiting', 'Approved', 'Rejected', '-'];

        $result = array_merge($result, [
            "rows" => $all_data,
            "filter" => $filter,
            'status' => $document_status
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
            return $this->error($this->validation($request), 422);
        }

        $details = collect($request->details);
        $form = $request->form;

        if ($form['RequestType'] == 'Urgent' && empty($form['UrgentReason'])) {
            return $this->error("Request Type Urgent Required Reason For That!", 422);
        }
        $doc_num = null;
        // get header
        DB::beginTransaction();
        try {
            $header = null;
            if ($form['Token']) {
                $header = ReservationHeader::where("Token", "=", $form['Token'])->first();
            }
            // set created at
            $created = (!empty($header)) ? $header->created_at : Carbon::now();
            if ($this->validateDetails($details, $form)['error']) {
                return $this->error($this->validateDetails($details, $form)['message'], '422');
            }

            $doc_entry = $this->processHeaderDoc($header, $created, $request);
            if ($doc_entry) {
                foreach ($details as $index => $items) {
                    $line = ($index + 1);
                    // Saved the data
                    $this->saveData(
                        $line,
                        $items,
                        $request,
                        $form,
                        $doc_entry
                    );
                } // Details
                $is_approval = $request->approval;
                DB::commit();
                if ($is_approval) {
                    $header = ReservationHeader::where('U_DocEntry', '=', $doc_entry)->first();
                    return $this->submitApproval($header, $details, $request);
                } else {
                    return $this->success([
                        "U_DocEntry" => $doc_entry
                    ], ($doc_entry != 'null') ? "Data updated!" : "Data inserted!");
                }
            } else {
                return $this->error("Failed process header!", 422);
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage(), '422', [
                'trace' => $exception->getTrace()
            ]);
        }
    }

    /**
     * @param $details
     * @param $form
     * @return array|false[]
     */
    protected function validateDetails($details, $form)
    {
        if ($details) {
            foreach ($details as $index => $items) {
                $line = ($index + 1);
                if (empty($items['ItemCode'])) {
                    return [
                        'error' => true,
                        'message' => "Line $line: Item Code cannot empty!"
                    ];
                }

                if (empty($items['WhsCode'])) {
                    return [
                        'error' => true,
                        'message' => "Line $line: WhsCode cannot empty!"
                    ];
                }

                if (empty($items['ReqQty'])) {
                    return [
                        'error' => true,
                        'message' => "Line $line: ReqQty cannot empty!"
                    ];
                }

                if ($items["ItemCategory"] == 'RS') {
                    if ($items['NPB'] == 'Y') {
                        if (isset($items['OtherResvNo'])) {
                            return [
                                'error' => true,
                                'message' => "Line $line: Cannot insert OtherResvNo!"
                            ];
                        }
                    }
                } elseif ($items["ItemCategory"] != 'RS') {
                    if ($items['NPB'] == 'Y') {
                        if ($items["ReqQty"] > $items["AvailableQty"] && !isset($items['OtherResvNo'])) {
                            return [
                                'error' => true,
                                'message' => "Line $line: Request Qty Cannot Greater Than Available Qty!"
                            ];
                        }

                        if ($items["OnHand"] < $items["ReqQty"] && !isset($items['OtherResvNo'])) {
                            return [
                                'error' => true,
                                'message' => "Line $line: On Hand Qty Cannot Greater Than Available Qty!"
                            ];
                        }
                    }

                    if ($items['NPB'] == 'Y' && ($form['RequestType'] == 'Normal'
                            || $form['RequestType'] == 'For Restock SubWH')) {
                        if (empty($items['OtherResvNo'])) {
                            return [
                                'error' => true,
                                'message' => "Line $line: Other Reservation No is required!"
                            ];
                        } else {
                            $check_docnum = ReservationHeader::where("DocNum", "=", $items['OtherResvNo'])->first();
                            if ($check_docnum) {
                                $check_details = ReservationDetails::where("U_DocEntry", "=", $check_docnum->U_DocEntry)
                                    ->where("ItemCode", "=", $items['ItemCode'])
                                    ->first();
                                if (!$check_details) {
                                    return [
                                        'error' => true,
                                        'message' => "Line $line: Other Reservation No with this
                                        itemcode is not valid!"
                                    ];
                                }
                            } else {
                                return [
                                    'error' => true,
                                    'message' => "Line $line: Other Reservation No is not valid!"
                                ];
                            }
                        }
                    }
                }
            }

            return [
                'error' => false,
            ];
        } else {
            return [
                'error' => true,
                'message' => "Document must have details!"
            ];
        }
    }

    /**
     * @param $request
     * @return false|string
     */
    protected function validation($request)
    {
        $messages = [
            'form.CompanyName' => 'Company Name is required!',
            'form.DocDate.required' => 'Request Date is required!',
            'form.RequiredDate.required' => 'Required Date is required!',
            'form.RequestType.required' => 'Request Type is required!',
            'form.U_NIK.required' => 'Requester NIK is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.CompanyName' => 'required',
            'form.DocDate' => 'required',
            'form.RequiredDate' => 'required',
            'form.RequestType' => 'required',
            'form.U_NIK' => 'required',
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
     * @param $id
     * @param Request $request
     * @return null
     */
    private function getHeaderDoc($id, Request $request)
    {
        $header = null;
        if ($id) {
            $header = ReservationHeader::where("U_DocEntry", "=", $id)->first();
        }
        return $header;
    }

    /**
     * @param $header
     * @param $created
     * @param $request
     *
     * @return int|mixed
     */
    protected function processHeaderDoc($header, $created, $request)
    {
        $data = [
            'Company' => $request->form['CompanyName'],
            'RequiredDate' => $request->form['RequiredDate'],
            'DocDate' => $request->form['DocDate'],
            'RequestType' => $request->form['RequestType'],
            'ItemType' => $request->form['ItemType'],
            'Memo' => $request->form['Memo'],
            'U_NIK' => $request->form['U_NIK'],
            'WhsCode' => $request->form['WhsCode'],
            'WhTo' => $request->form['WhTo'],
            'Requester' => $request->form['U_NIK'],
            'RequesterName' => $request->form['RequesterName'],
            'Division' => $request->form['Division'],
            'Department' => $request->form['Division'],
            'UrgentReason' => $request->form['UrgentReason'],
        ];
        if ($header) {
            $add_data = [
                'UpdateDate' => date('Y-m-d'),
                'UpdateTime' => date('H:i:s'),
                'UpdatedBy' => Auth::user()->username,
            ];
            DB::connection('laravelOdbc')
                ->table('RESV_H')
                ->where('U_DocEntry', '=', $header->U_DocEntry)
                ->update(array_merge($data, $add_data));

            $data_header = DB::connection('laravelOdbc')
                ->table('RESV_H')
                ->where('U_DocEntry', '=', $header->U_DocEntry)
                ->first();
            $doc_num = $data_header['U_DocEntry'];
        } else {
            $add_data = [
                'Token' => $request->form['Token'],
                'CreateDate' => date('Y-m-d'),
                'CreateTime' => date('H:i:s'),
                'DocNum' => $this->generateDocNum(date('Y-m-d H:i:s')),
                'CreatedBy' => Auth::user()->username,
                'CreatedName' => Auth::user()->name,
                'Canceled' => 'N',
                'DocStatus' => 'D',
                'ApprovalStatus' => '-',
            ];

            DB::connection('laravelOdbc')
                ->table('RESV_H')
                ->insert(array_merge($data, $add_data));

            $doc_entry = ReservationHeader::orderBy("U_DocEntry", "DESC")->first();
            $doc_num = $doc_entry->U_DocEntry;
        }
        return $doc_num;
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
            $first_date = "$full_year-$month-01";
            $second_date = "$full_year-$month-$end_date";
            $doc_num = ReservationHeader::selectRaw('IFNULL("DocNum", 0) as DocNum')
                ->whereBetween(DB::raw('"CreateDate"'), [$first_date, $second_date])
                ->orderBy("DocNum", "DESC")
                ->first();
            $number = (empty($doc_num)) ? '00000000' : $doc_num->DOCNUM;
            $clear_doc_num = (int)substr($number, 4, 7);
            $number = $clear_doc_num + 1;
            return (int)$year_val . $month . sprintf("%04s", $number);
        }
    }

    /**
     * @param $details
     * @param $doc_entry
     * @param $form
     * @param $request
     * @param bool $is_approval
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loopDetails($details, $doc_entry, $form, $request, bool $is_approval = false)
    {
        foreach ($details as $index => $items) {
            $line = ($index + 1);
            // Saved the data
            $this->saveData(
                $line,
                $items,
                $request,
                $form,
                $doc_entry
            );
        } // Details
        $is_approval = $request->approval;
        DB::commit();
        if ($is_approval) {
            $header = ReservationHeader::where('U_DocEntry', '=', $doc_entry)->first();
            return $this->submitApproval($header, $details, $request);
        } else {
            return $this->success([
                "U_DocEntry" => $doc_entry
            ], ($doc_entry != 'null') ? "Data updated!" : "Data inserted!");
        }
    }

    /**
     * @param $index
     * @param $items
     * @param $request
     * @param $form
     * @param $doc_entry
     * @return mixed
     */
    protected function saveData($index, $items, $request, $form, $doc_entry)
    {
        $last_data = (array_key_exists('LineEntry', $items)) ? $items["LineEntry"] : null;
        $docs = ReservationDetails::where("LineEntry", "=", $last_data)->first();
        if ($items["SPB"] == 'Y') {
            $request_type = 'SPB';
        } else {
            $request_type = 'NPB';
        }

        if ($docs) {
            $docs->U_DocEntry = $doc_entry;
            $docs->LineNum = $index;
            $docs->ItemCode = $items["ItemCode"];
            $docs->ItemName = $items["ItemName"];
            $docs->ItemCategory = $items["ItemCategory"];
            $docs->UoMCode = $items["UoMCode"];
            $docs->WhsCode = $items["WhsCode"];
            $docs->ReqQty = $items["ReqQty"];
            $docs->ReqDate = date('Y-m-d', strtotime($items["ReqDate"]));
            $docs->ReqNotes = $items["ReqNotes"];
            $docs->OtherResvNo = $items["OtherResvNo"];
            $docs->OIGRDocNum = $items["OIGRDocNum"];
            $docs->InvntItem = $items["InvntItem"];
            $docs->RequestType = $request_type;
            $docs->save();
            return $docs->LineEntry;
        } else {
            $line_entry = ReservationDetails::orderBy("LineEntry", "DESC")->first();

            $docs = new ReservationDetails();
            $docs->U_DocEntry = $doc_entry;
            $docs->LineNum = $index;
            //$docs->LineEntry = ($line_entry) ? ($line_entry->LineEntry + 1) : 1;
            $docs->ItemCode = $items["ItemCode"];
            $docs->ItemName = $items["ItemName"];
            $docs->ItemCategory = $items["ItemCategory"];
            $docs->UoMCode = $items["UoMCode"];
            $docs->WhsCode = $items["WhsCode"];
            $docs->ReqQty = $items["ReqQty"];
            $docs->ReqDate = date('Y-m-d', strtotime($items["ReqDate"]));
            $docs->ReqNotes = (array_key_exists('ReqNotes', $items)) ? $items["ReqNotes"] : null;
            $docs->OtherResvNo = (array_key_exists('OtherResvNo', $items)) ? $items["OtherResvNo"] : null;
            $docs->LineStatus = 'O';
            $docs->OIGRDocNum = (array_key_exists('OIGRDocNum', $items)) ? $items["OIGRDocNum"] : null;
            $docs->InvntItem = $items["InvntItem"];
            $docs->RequestType = $request_type;
            $docs->save();

            return $docs->LineEntry;
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchDocNum(): \Illuminate\Http\JsonResponse
    {
        $doc_num = ReservationHeader::select("DocNum")
            ->orderBY("DocNum", "DESC")
            ->first();

        $document = ($doc_num) ? (int)$doc_num->DocNum + 1 : $this->generateDocNum(date('Y-m-d H:i:s'));
        $token = Str::random(100);

        return response()->json([
            "DocNum" => $document,
            "token" => $token
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $header = ReservationHeader::select(
                "RESV_H.*",
                "RESV_H.Company As CompanyName",
                "U_WDD1.U_Status",
                DB::raw('
                    CASE
                        WHEN RESV_H."ApprovalStatus" = \'W\' THEN \'Waiting\'
                        WHEN RESV_H."ApprovalStatus" = \'P\' THEN \'Pending\'
                        WHEN RESV_H."ApprovalStatus" = \'N\' THEN \'Reject\'
                        WHEN RESV_H."ApprovalStatus" = \'Y\' THEN \'Approve\'
                        WHEN RESV_H."ApprovalStatus" = \'-\' THEN \'-\'
                    END AS "AppStatus"
                ')
            )
                ->leftJoin("U_OWDD", "U_OWDD.U_DocKey", "=", "RESV_H.U_DocEntry")
                ->leftJoin("U_WDD1", "U_WDD1.U_WddCode", "=", "U_OWDD.U_WddCode")
                ->where("RESV_H.U_DocEntry", "=", $id)
                ->orderBy("U_WDD1.U_CreateDate", "DESC")
                ->first();

            $arr_division = [
                "U_Name" => $header['U_Division'],
                "U_DocEntry" => (int)$header['Division'],
            ];

            $arr_user_nik = [
                "U_UserName" => $header['U_Division'],
                "U_NIK" => $header['U_NIK'],
            ];

            $item_whs = '';
            $user_whs = UserWhs::where("user_id", "=", $request->user()->id)->get();
            foreach ($user_whs as $user_wh) {
                $item_whs .= "'$user_wh->whs_code',";
            }

            $item_whs = rtrim($item_whs, ', ');

            $connect = $this->connectHana();

            $own_db_name = (env('LARAVEL_ODBC_USERNAME') !== null) ? env('LARAVEL_ODBC_USERNAME') : 'IMIP_ERESV';
            $data_details = ReservationDetails::where("U_DocEntry", "=", $header['U_DocEntry'])->get();
            // dd($data_details);
            $user_company = UserCompany::leftJoin('companies', 'companies.id', 'user_companies.company_id')
                ->where("db_code", "=", $header['Company'])
                ->first();

            $db_name = $user_company->db_code;
            $arr = [];
            foreach ($data_details as $key => $detail) {
                $sql = '
                        SELECT DISTINCT T0.*,
                            IFNULL(
                                (SELECT SUM( X."OnHand")
                                    FROM ' . $db_name . '.OITW X
                                    WHERE X."ItemCode" = T0."ItemCode" AND X."WhsCode" IN ( ' . $item_whs . ')
                                ), 0) AS "OnHand",
                            IFNULL(
                                (SELECT SUM( X."OnHand")
                                FROM ' . $db_name . '.OITW X
                                WHERE X."ItemCode" = T0."ItemCode" AND X."WhsCode" IN ( ' . $item_whs . ')
                                ),0) - IFNULL(GIR."PendingQty",0) AS "AvailableQty",
                            T2."InvntryUom",
                            IFNULL(T2."U_ItemType", \'RS\') AS "ItemCategory",
                            T4."ReqDate" as "LastReqDate",
                            T4."ReqNotes" as "LastReqNote",
                            T4."RequesterName" as "LastReqBy",
                            T2."U_ItemType"
                        FROM ' . $own_db_name . '."RESV_D" As T0
                        LEFT JOIN ' . $db_name . '."OITM" AS T2 ON T2."ItemCode" = T0."ItemCode"
                        LEFT JOIN
                        (
                            SELECT X1."U_ItemCode",
                                    SUM (X1."U_ReqQty"- IFNULL(X1."U_Issued",0) ) AS "PendingQty"
                            FROM ' . $db_name . '."@DGN_EI_OIGR" As X0
                            LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" AS X1 ON X0."DocEntry" = X1."DocEntry"
                            WHERE X0."Canceled" = \'N\' AND X0."Status" =\'O\'
                            AND X1."U_WhsCode" IN ( ' . $item_whs . ')
                            AND X1."U_ReqQty" > IFNULL(X1."U_Issued",0)
                            GROUP BY  X1."U_ItemCode"
                        ) AS GIR ON  T0."ItemCode" = GIR."U_ItemCode"
                        LEFT JOIN (
                                SELECT A."ReqDate", A."ReqNotes",B."U_DocEntry", B."RequesterName", A."ItemCode" , B."ApprovalStatus", A."LineEntry"
                                FROM ' . $own_db_name . '."RESV_D" AS A
                                left join ' . $own_db_name . '."RESV_H" AS B ON A."U_DocEntry" = B."U_DocEntry"
                                WHERE B."ApprovalStatus" NOT IN (\'-\', \'N\', \'W\')
                                --AND A."ReqDate" < \'' . $detail->ReqDate . '\'
                                AND A."WhsCode" = \'' . $detail->WhsCode . '\'
                                AND A."ItemCode" = \'' . $detail->ItemCode . '\'
                                ORDER BY A."U_DocEntry" DESC
                                LIMIT 1
                        ) AS T4 ON T4."ItemCode" = T0."ItemCode"
                        WHERE T0."LineEntry" = ' . $detail->LineEntry . '
                        ORDER BY T0."LineNum" ASC
                    ';
                // dd($sql);
                $rs = odbc_exec($connect, $sql);

                if (!$rs) {
                    exit("Error in SQL");
                }

                while (odbc_fetch_row($rs)) {
                    $arr[] = [
                        "U_DocEntry" => odbc_result($rs, "U_DocEntry"),
                        "LineNum" => odbc_result($rs, "LineNum"),
                        "ItemCode" => odbc_result($rs, "ItemCode"),
                        "ItemName" => mb_convert_encoding(odbc_result($rs, "ItemName"), 'UTF-8', 'UTF-8'),
                        "WhsCode" => odbc_result($rs, "WhsCode"),
                        "UoMCode" => odbc_result($rs, "UoMCode"),
                        "UoMName" => odbc_result($rs, "UoMName"),
                        "ReqQty" => odbc_result($rs, "ReqQty"),
                        "ReqDate" => odbc_result($rs, "ReqDate"),
                        "ReqNotes" => odbc_result($rs, "ReqNotes"),
                        "OtherResvNo" => odbc_result($rs, "OtherResvNo"),
                        "QtyReadyIssue" => odbc_result($rs, "QtyReadyIssue"),
                        "LineStatus" => odbc_result($rs, "LineStatus"),
                        "SAP_GIRNo" => odbc_result($rs, "SAP_GIRNo"),
                        "SAP_TrfNo" => odbc_result($rs, "SAP_TrfNo"),
                        "SAP_PRNo" => odbc_result($rs, "SAP_PRNo"),
                        "LineEntry" => odbc_result($rs, "LineEntry"),
                        "ItemCategory" => odbc_result($rs, "ItemCategory"),
                        "OIGRDocNum" => odbc_result($rs, "OIGRDocNum"),
                        "AvailableQty" => odbc_result($rs, "AvailableQty"),
                        "OnHand" => odbc_result($rs, "OnHand"),
                        "LastReqDate" => odbc_result($rs, "LastReqDate"),
                        "LastReqNote" => odbc_result($rs, "LastReqNote"),
                        "LastReqBy" => odbc_result($rs, "LastReqBy"),
                        "U_ItemType" => odbc_result($rs, "U_ItemType"),
                        "InvntItem" => odbc_result($rs, "InvntItem"),
                        "SPB" => ((odbc_result($rs, "RequestType")) == 'SPB' ? 'Y' : 'N'),
                        "NPB" => ((odbc_result($rs, "RequestType")) == 'NPB' ? 'Y' : 'N'),
                    ];
                }
            }

            // dd($arr);

            $attachment = Attachment::where('type', 'reservation')
                ->where('source_id', $id)
                ->count();

            return response()->json([
                "header" => $header,
                "rows" => $arr,
                "division" => $arr_division,
                "user_nik" => $arr_user_nik,
                "count_attachment" => $attachment
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
                //"trace" => $exception->getTrace(),
            ], 422);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        if ($this->validation($request)) {
            return response()->json([
                "errors" => true,
                "validHeader" => true,
                "message" => $this->validation($request)
            ]);
        }

        $details = collect($request->details);
        $form = $request->form;

        if ($form['RequestType'] == 'Urgent' && empty($form['UrgentReason'])) {
            return response()->json([
                "errors" => true,
                "message" => "Request Type Urgent Required Reason For That!"
            ]);
        }
        $doc_num = null;
        // get header
        $header = $this->getHeaderDoc($id, $request);
        if ($header) {
            if ($header->ApprovalStatus != '-' && $header->ApprovalStatus != 'N') {
                return response()->json([
                    "errors" => true,
                    "message" => "Document is still waiting for approval!"
                ]);
            }
        }

        DB::beginTransaction();
        try {
            if ($this->validateDetails($details, $form)['error']) {
                return $this->error($this->validateDetails($details, $form)['message'], '422');
            }
            
            // set created at
            $created = (!empty($header)) ? $header->created_at : Carbon::now();
            $doc_entry = $this->processHeaderDoc($header, $created, $request);
            //return response()->json($doc_entry);
            if ($doc_entry) {
                foreach ($details as $index => $items) {
                    $line = ($index + 1);
                    // Saved the data
                    $this->saveData(
                        $line,
                        $items,
                        $request,
                        $form,
                        $doc_entry
                    );
                } // Details
                $is_approval = $request->approval;
                DB::commit();
                if ($is_approval) {
                    $header = ReservationHeader::where('U_DocEntry', '=', $doc_entry)->first();
                    return $this->submitApproval($header, $details, $request);
                } else {
                    return $this->success([
                        "U_DocEntry" => $doc_entry
                    ], ($doc_entry != 'null') ? "Data updated!" : "Data inserted!");
                }
            } else {
                $this->error("Failed process header!", '422');
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage(), '422', [
                'trace' => $exception->getTrace()
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
        $details = ReservationDetails::where("LineEntry", "=", $id)->first();
        if ($details) {
            $header = ReservationHeader::where("U_DocEntry", "=", $details->U_DocEntry)->first();
            if ($header->ApprovalStatus == '-') {
                if (ReservationDetails::where("LineEntry", "=", $id)->first()) {
                    ReservationDetails::where("LineEntry", "=", $id)->delete();
                }
                // ReservationHeader::where("U_DocEntry", "=", $id)->delete();
                return response()->json([
                    'message' => 'Row deleted'
                ]);
            } else {
                return response()->json([
                    'message' => 'Cannot delete row'
                ]);
            }
        }
        return response()->json([
            'message' => 'Row not found'
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function maxDocResv(Request $request): \Illuminate\Http\JsonResponse
    {
        $max_num = ReservationHeader::selectRaw('IFNULL(MAX("U_DocEntry"), 1) as "DocEntry"')->first();
        $token = Str::random(100);
        return response()->json([
            'max_num' => $max_num,
            'token' => $token,
        ]);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAll($id): \Illuminate\Http\JsonResponse
    {
        $header = ReservationHeader::where("U_DocEntry", "=", $id)->first();
        if ($header->ApprovalStatus == '-') {
            if (ReservationDetails::where("U_DocEntry", "=", $id)->first()) {
                ReservationDetails::where("U_DocEntry", "=", $id)->delete();
                return response()->json([
                    'message' => 'Records deleted!'
                ]);
            } else {
                return response()->json([
                    'message' => 'Row not found'
                ]);
            }
        } else {
            return response()->json([
                'message' => 'Cannot delete row'
            ]);
        }
    }

    /**
     * @param $header
     * @param $details
     * @param $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitApproval($header, $details, $request)
    {
        $form = $header;
        $cherry_token = $request->user()->cherry_token;
        $list_code = Http::post(env('CHERRY_REQ'), [
            'CommandName' => 'GetList',
            'ModelCode' => 'ExternalDocuments',
            'UserName' => $request->user()->username,
            'Token' => $cherry_token,
            'ParameterData' => []
        ]);

        $reservation_code = '';
        //return response()->json($list_code->collect()['Data'] );
        foreach ($list_code->collect()['Data'] as $datum) {
            if ($datum['Name'] == 'E-RESERVATION NPB' && $form->RequestType == 'Ready Stock') {
                $reservation_code = $datum['Code'];
            } else {
                $reservation_code = $datum['Code'];
            }

//            if ($datum['Name'] == 'E-RESERVATION URGENT' && $form->RequestType == 'Urgent') {
//                $reservation_code = $datum['Code'];
//            } elseif ($datum['Name'] == 'E-RESERVATION NORMAL') {
//                $reservation_code = $datum['Code'];
//            }
        }

        $username = $request->user()->username;
        $company_code = $request->user()->company_code;

        $response = Http::get(env('CHERRY_CHECK_EMPLOYEE'), [
            'username' => $username,
            'token' => $cherry_token,
            'companyCode' => $company_code,
            'q' => $form->RequesterName
        ]);

        $employee_code = '';
        foreach ($response->collect() as $item) {
            if ($item['Nik'] == $form->Requester) {
                $employee_code = $item['EmployeeCode'];
            }
        }

        $document_content = view('email.approval_resv', compact('details'))->render();

        //return response()->json($document_content);
        //return response()->json($reservation_code);
        $response = Http::post(env('CHERRY_REQ'), [
            'CommandName' => 'Submit',
            'ModelCode' => 'GADocuments',
            'UserName' => $username,
            'Token' => $cherry_token,
            'ParameterData' => [],
            'ModelData' => [
                'TypeCode' => $reservation_code,
                'CompanyCode' => $company_code,
                'Date' => date('m/d/Y'),
                'EmployeeCode' => $employee_code,
                'DocumentReferenceID' => $form->DocNum,
                'CallBackAccessToken' => 'http://sbo2.imip.co.id:3000/backendcore/api/callback',
                'DocumentContent' => $document_content,
                'Notes' => $form->Memo
            ]
        ]);
        if ($response['MessageType'] == 'error') {
            return $this->error($response->collect()['Message'], '422');
        }

        ReservationHeader::where('U_DocEntry', '=', $form->U_DocEntry)
            ->update([
                'ApprovalStatus' => 'W'
            ]);

        DB::commit();

        return $this->success([
            "U_DocEntry" => $form->U_DocEntry
        ], ($form->U_DocEntry != 'null' ? "Data updated!" : "Data inserted!"));
    }

    /**
     * @param array $data
     */
    protected function createNotification(array $data)
    {
        UserNotification::insert($data);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function printDocument(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $form = json_decode($request->form);
            $data_header = ReservationHeader::select("*")
                ->where("RESV_H.U_DocEntry", "=", $form->U_DocEntry)
                ->first();

            $db_name = $data_header->Company;

            $header = ReservationHeader::select(
                "RESV_H.*",
                DB::raw('( SELECT STRING_AGG(X."PR_NO", \', \')
                    FROM (

                        SELECT DISTINCT Q0."DocNum" AS "PR_NO"
                        FROM ' . $db_name . '."OPRQ" Q0
                        LEFT JOIN ' . $db_name . '."PRQ1" Q1 ON Q0."DocEntry" = Q1."DocEntry"
                        WHERE Q1."U_DGN_IReqId"  = RESV_H."SAP_GIRNo"  AND Q0."CANCELED" =\'N\'

                    ) AS X
                 )  AS "SAP_PRNo"'),
                DB::raw('(
                    SELECT "U_DocNum"
                    FROM ' . $db_name . '."@DGN_EI_OIGR"
                    where ' . $db_name . '."@DGN_EI_OIGR"."DocNum" = RESV_H."SAP_GIRNo") AS "SAP_GIRNo"'),
                DB::raw('
                    (
                        SELECT STRING_AGG(X."DocNum", \', \') as "GI_No"
                         FROM
                            ( SELECT DISTINCT T0."DocNum"
                                FROM ' . $db_name . '."@DGN_EI_OIGR" G0
                                 LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" G1 ON G0."DocEntry" = G1."DocEntry"
                                 LEFT JOIN ' . $db_name . '.IGE1 T1
                                           ON T1."U_DGN_IReqId" = G1."DocEntry" AND T1."U_DGN_IReqLineId" = G1."LineId"
                                 LEFT JOIN ' . $db_name . '.OIGE T0 ON T1."DocEntry" = T0."DocEntry"
                                WHERE G0."DocEntry" = RESV_H."SAP_GIRNo"
                            )  AS X
                      ) AS  "SAP_GINo"
                '),
            )
                ->where("RESV_H.U_DocEntry", "=", $form->U_DocEntry)
                ->first();

            $details = ReservationDetails::where("U_DocEntry", "=", $header->U_DocEntry)
                // ->where("RequestType", "=", "NPB")
                ->get();

            $data_letter = [];
            foreach ($details as $index => $item) {
                $data_letter [] = [
                    "NO" => ($index + 1),
                    "ITEMCODE" => $item->ItemCode,
                    "ITEMNAME" => $item->ItemName,
                    "UOM" => $item->UoMCode,
                    "QTY" => $item->ReqQty,
                    "DATE" => $item->ReqDate,
                    "NOTES" => $item->ReqNotes,
                ];
            }

            //return response()->json($data_letter);

            $letter_template = new TemplateProcessor(
                public_path(
                    'template/NPB.docx'
                )
            );

            $letter_template->setValue('REQUESTOR', $header->RequesterName);
            $letter_template->setValue('NOERESVE', $header->DocNum);
            $letter_template->setValue('NOGIR', $header->SAP_GIRNo);
            $letter_template->setValue('REQUESTDATE', $header->DocDate);
            $letter_template->setValue('DEPARTMENT', $header->Department);
            $letter_template->setValue('REQUEST_TYPE', $header->RequestType);
            $letter_template->setValue('REQUIRED_DATE', $header->RequiredDate);
            $letter_template->setValue('WHSCODE', $header->WhsCode);
            $letter_template->setValue('REMARKS', $header->Memo);
            $letter_template->setValue('DATETIME', 'Print Date: ' . date('Y-m-d H:i:s'));

            $letter_template->cloneRowAndSetValues('NO', $data_letter);

            $file_path_name = public_path() . '/attachment/NPB/' . $request->user()->username;

            if (!file_exists($file_path_name)) {
                if (!mkdir($file_path_name, 0777, true) && !is_dir($file_path_name)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Directory "%s" was not created',
                            $file_path_name
                        )
                    );
                }
            }

            $file_str = Str::random(10);
            $file_name = $file_path_name . '/' . $file_str . '.docx';

            //return response()->json($file_name);
            $letter_template->saveAs($file_name);
            //return response()->json($file_path_name);
            $pdf_file = $file_path_name . '/' . $file_str . ".pdf";
            $word_file = new \COM('Word.Application') or die('Could not initialise Object.');
            $word_file->Visible = 0;
            $word_file->DisplayAlerts = 0;
            $word_file->Documents->Open(
                $file_name
            );
            $word_file->ActiveDocument->ExportAsFixedFormat(
                $pdf_file,
                17,
                false,
                0,
                0,
                0,
                0,
                7,
                true,
                true,
                2,
                true,
                true,
                false
            );
            // quit the Word process
            $word_file->Quit(false);
            // clean up
            unset($word_file);
            $all_files = [
                $file_name,
                $pdf_file
            ];
            // Remove Attachment
            RemoveAttachment::dispatch($all_files)->delay(now()->addMinutes(5));

            return $this->success([], url('/attachment/NPB/' . $request->user()->username . '/' . $file_str . ".pdf"));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }
}
