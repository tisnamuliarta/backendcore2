<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
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
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "DocNum";
        $order = isset($options->sortDesc[0]) ? "DESC" : "ASC";
        $search = isset($request->search) ? (string)$request->search : "";
        $select_data = isset($request->searchItem) ? (string)$request->searchItem : "DocNum";
        $offset = ($pages - 1) * $row_data;
        $username = $request->user()->username;
        $user_id = $request->user()->username;
        // dd($user_id);

        // $db_name = env('DB_SAP');
        $schema = env("DB_SCHEMA");

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
            ->when($username, function ($query) use (
                $username,
                $user_id
            ) {
                // dd($username);
                $data_query = $query;
//                if ($username != '88101989') {
//                    $data_query->where("RESV_H.CreatedBy", "=", $user_id);
//                }
                $data_query->where("RESV_H.CreatedBy", "=", $user_id);
                return $data_query;
            })
            ->orderBY($sorts, $order);

        $result["total"] = $query->count();

        $all_result = $query->offset($offset)
            ->limit($row_data)
            ->get();
        //dd($all_result);

        $single_data = [];
        foreach ($all_result as $key => $value) {
            $db_name = $value->Company;
            $pr_no = ($value->SAP_PRNo) ? $value->SAP_PRNo : null;
            $single_data[] = $query->select(
                "RESV_H.*",
                // DB::raw('(
                //     SELECT "DocNum"
                //     FROM ' . $db_name . '."OPRQ"
                //     where ' . $db_name . '."OPRQ"."DocEntry" = RESV_H."SAP_PRNo") AS "SAP_PRNo"'),
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
                // DB::raw('(SELECT STRING_AGG(T0."DocNum",\', \') as "GI_No"
                //     FROM ' . $db_name . '."@DGN_EI_OIGR" G0
                //     LEFT JOIN  ' . $db_name . '."@DGN_EI_IGR1"  G1  ON G0."DocEntry" = G1."DocEntry"
                //     LEFT JOIN  ' . $db_name . '.IGE1 T1 ON T1."U_DGN_IReqId" = G1."DocEntry" AND T1."U_DGN_IReqLineId" = G1."LineId"
                //     LEFT JOIN  ' . $db_name . '.OIGE T0 ON T1."DocEntry" = T0."DocEntry"
                //     WHERE G0."DocEntry" = RESV_H."SAP_GIRNo") AS "SAP_GINo"'),
                "RESV_H.Company as U_DbCode",
                // DB::raw(
                //     '(
                //         SELECT STRING_AGG(X."PONum",\', \') AS "PONum"
                //         FROM
                //         (
                //             SELECT DISTINCT T1."DocNum" AS "PONum"
                //             FROM  ' . $db_name . '."POR1" AS T0
                //             LEFT JOIN ' . $db_name . '."OPOR" AS T1 ON T0."DocEntry" = T1."DocEntry"
                //             WHERE T0."BaseType" = \'1470000113\'
                //             AND T0."BaseEntry" = ' . $schema . '.RESV_H."SAP_PRNo"
                //             AND T1."CANCELED" =\'N\'
                //         ) AS  X

                //     ) AS "PONum"'
                // ),
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
                // DB::raw('(
                //     SELECT STRING_AGG(X."GRPO_NO",\', \') AS "GRPO_NO"
                //     FROM
                //     (
                //         SELECT DISTINCT  T2."DocNum" AS "GRPO_NO"
                //         FROM ' . $schema . '."RESV_H" R1
                //         LEFT JOIN ' . $schema . '."RESV_D" R2 ON R1."U_DocEntry" = R2."U_DocEntry"
                //         LEFT JOIN ' . $db_name . '."POR1" T0  ON R1."SAP_PRNo" = T0."BaseEntry" AND  T0."BaseType"  = \'1470000113\'  AND R2."ItemCode" = T0."ItemCode"
                //         LEFT JOIN ' . $db_name . '."PDN1" T1 ON T0."DocEntry" = T1."BaseEntry" AND T1."BaseType" = T0."ObjType" AND T1."BaseLine" = T0."LineNum"
                //         LEFT JOIN ' . $db_name . '."OPDN" T2 ON T1."DocEntry" = T2."DocEntry"
                //         WHERE T0."BaseType" = \'1470000113\'
                //         AND R1."U_DocEntry" = ' . $schema . '.RESV_H."U_DocEntry"
                //         AND T0."BaseEntry" = ' . $schema . '.RESV_H."SAP_PRNo"   --- Ini nomor di RESV_H.SAP_PRNo
                //         AND T2."CANCELED" =\'N\'
                //     ) AS  X
                // ) AS "GRPONum"'),
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
                                ELSE \'Closed\'
                                END AS "GIR_status"
                            FROM ' . $db_name . '."@DGN_EI_OIGR" G0
                            WHERE G0."DocEntry" = RESV_H."SAP_GIRNo"
                        )
                        --WHEN RESV_H."DocStatus" = \'O\' THEN \'Open\'
                        --WHEN RESV_H."DocStatus" = \'C\' THEN \'Closed\'
                    END AS "DocumentStatus"
                '),
                DB::raw('
                    CASE
                        WHEN RESV_H."ApprovalStatus" = \'W\' THEN \'Waiting\'
                        WHEN RESV_H."ApprovalStatus" = \'P\' THEN \'Pending\'
                        WHEN RESV_H."ApprovalStatus" = \'N\' THEN \'Reject\'
                        WHEN RESV_H."ApprovalStatus" = \'Y\' THEN \'Approve\'
                        WHEN RESV_H."ApprovalStatus" = \'-\' THEN \'-\'
                    END AS "AppStatus"
                '),
                DB::raw('\'action\' AS "Action"'),
            );
        }

        $all_data = $query->offset($offset)
            ->limit($row_data)
            ->get();

        $filter = ["DocNum", "Company", "Req Name", "Req Type", "Req Date", "App Status"];

        $result = array_merge($result, [
            "rows" => $all_data,
            "filter" => $filter,
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

        $details = collect($request->details);
        $form = $request->form;
        $doc_num = null;
        // get header
        $header = null;
        if ($form['Token']) {
            $header = ReservationHeader::where("Token", "=", $form['Token'])->first();
        }
        // set created at
        $created = (!empty($header)) ? $header->created_at : Carbon::now();
        // process header
//        $division = Division::where("U_DocEntry", "=", $request->Division['U_DocEntry'])->first();
//        $department = Department::where("U_DocEntry", "=", $division->U_DeptEntry)->first();
//        if (!$department) {
//            return response()->json([
//                "errors" => true,
//                "message" => "Cannot Find Department!"
//            ]);
//        }
        $doc_entry = $this->processHeaderDoc($header, $created, $request);
        if ($doc_entry) {
            //ToDo
            return $this->loopDetails($details, $doc_entry, $form, $request);
        } else {
            return response()->json([
                "errors" => true,
                "message" => "Failed process header!"
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
    protected function processHeaderDoc($header, $created, $request): int
    {
        if ($header) {
            DB::connection('laravelOdbc')
                ->table('RESV_H')
                ->where('U_DocEntry', '=', $header->U_DocEntry)
                ->update([
                    'Company' => $request->form['CompanyName'],
                    'RequiredDate' => $request->form['RequiredDate'],
                    'DocDate' => $request->form['DocDate'],
                    'RequestType' => $request->form['RequestType'],
                    'Memo' => $request->form['Memo'],
                    'U_NIK' => $request->form['U_NIK'],
                    'WhsCode' => $request->form['WhsCode'],
                    'WhTo' => $request->form['WhTo'],
                    'UpdateDate' => date('Y-m-d'),
                    'UpdateTime' => date('H:i:s'),
                    'UpdatedBy' => Auth::user()->username,
                    'Requester' => $request->form['U_NIK'],
                    'RequesterName' => $request->form['RequesterName'],
                    'Division' => $request->form['Division'],
                    'Department' => $request->form['Division'],
                ]);

            $doc_num = $header->U_DocEntry;
        } else {
            $doc_entry = ReservationHeader::orderBy("U_DocEntry", "DESC")->first();

            $header = new ReservationHeader();
            //$header->U_DocEntry = ($doc_entry) ? ($doc_entry->U_DocEntry + 1) : 1;
            $header->Company = $request->form['CompanyName'];
            $header->RequiredDate = $request->form['RequiredDate'];
            $header->DocDate = $request->form['DocDate'];
            $header->RequestType = $request->form['RequestType'];
            $header->Memo = $request->form['Memo'];
            $header->U_NIK = $request->form['U_NIK'];
            $header->WhsCode = $request->form['WhsCode'];
            $header->WhTo = $request->form['WhTo'];
            $header->Token = $request->form['Token'];
            $header->CreateDate = date('Y-m-d');
            $header->CreateTime = date('H:i:s');
            $header->DocNum = $this->generateDocNum(date('Y-m-d H:i:s'));
            $header->CreatedBy = Auth::user()->username;
            $header->CreatedName = Auth::user()->name;
            $header->Requester = $request->form['U_NIK'];
            $header->RequesterName = $request->form['RequesterName'];
            $header->Division = $request->form['Division'];
            $header->Department = $request->form['Division'];
            $header->Canceled = 'N';
            $header->DocStatus = 'D';
            $header->ApprovalStatus = '-';
            $header->save();

            $doc_num = $header->U_DocEntry;
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function loopDetails($details, $doc_entry, $form, $request): \Illuminate\Http\JsonResponse
    {
        if ($details) {
            foreach ($details as $index => $items) {
                $line = ($index + 1);
                if (empty($items['ItemCode'])) {
                    return response()->json([
                        "errors" => true,
                        "message" => "Line $line: Item Code cannot empty!",
                    ]);
                }

                if (empty($items['WhsCode'])) {
                    return response()->json([
                        "errors" => true,
                        "message" => "Line $line: WhsCode cannot empty!",
                    ]);
                }

                if (empty($items['ReqQty'])) {
                    return response()->json([
                        "errors" => true,
                        "message" => "Line $line: ReqQty cannot empty!",
                    ]);
                }

                if ($items["ItemCategory"] == 'RS') {
                    if ($items['NPB'] == 'Y') {
                        if (isset($items['OtherResvNo'])) {
                            return response()->json([
                                "errors" => true,
                                "message" => "Line $line: Cannot insert OtherResvNo!",

                            ]);
                        }

//                        if ($items["ReqQty"] > $items["AvailableQty"]) {
//                            return response()->json([
//                                "errors" => true,
//                                "message" => "Line $line: Request Qty Cannot Greater Than Available Qty!",
//
//                            ]);
//                        }
                    }
                } elseif ($items["ItemCategory"] != 'RS') {
                    if ($items['NPB'] == 'Y') {
                        if ($items["ReqQty"] > $items["AvailableQty"] && !isset($items['OtherResvNo'])) {
                            return response()->json([
                                "errors" => true,
                                "message" => "Line $line: Request Qty Cannot Greater Than Available Qty!",

                            ]);
                        }

                        if ($items["OnHand"] < $items["ReqQty"] && !isset($items['OtherResvNo'])) {
                            return response()->json([
                                "errors" => true,
                                "message" => "Line $line: On Hand Qty Cannot Greater Than Available Qty!",

                            ]);
                        }
                    }

                    if ($items['NPB'] == 'Y' && ($form['RequestType'] == 'Normal' || $form['RequestType'] == 'For Restock SubWH')) {
                        if (empty($items['OtherResvNo'])) {
                            return response()->json([
                                "errors" => true,
                                "message" => "Line $line: Other Reservation No is required!",
                            ]);
                        } else {
                            $check_docnum = ReservationHeader::where("DocNum", "=", $items['OtherResvNo'])->first();
                            if ($check_docnum) {
                                $check_details = ReservationDetails::where("U_DocEntry", "=", $check_docnum->U_DocEntry)
                                    ->where("ItemCode", "=", $items['ItemCode'])
                                    ->first();
                                if (!$check_details) {
                                    return response()->json([
                                        "errors" => true,
                                        "message" => "Line $line: Other Reservation No with this itemcode is not valid!",
                                    ]);
                                }
                            } else {
                                return response()->json([
                                    "errors" => true,
                                    "message" => "Line $line: Other Reservation No is not valid!",
                                ]);
                            }
                        }
                    }
                }

                // dd('ok');
                // Saved the data
                $this->saveData(
                    $line,
                    $items,
                    $request,
                    $form,
                    $doc_entry
                );
            } // Details
        }

        return response()->json([
            "errors" => false,
            "U_DocEntry" => $doc_entry,
            "message" => ($doc_entry != 'null') ? "Data updated!" : "Data inserted!"
        ]);
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
            $schema = env("DB_SCHEMA");
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
            $user_whs = UserWhs::where("user_id", "=", $request->user()->username)->get();
            foreach ($user_whs as $user_wh) {
                $item_whs .= "'$user_wh->whs_code',";
            }

            $item_whs = rtrim($item_whs, ', ');

            $connect = $this->connectHana();

            $own_db_name = env('LARAVEL_ODBC_USERNAME');
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
                            T4."U_UserName" as "LastReqBy",
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
                                SELECT A."ReqDate", A."ReqNotes",B."U_DocEntry", C."U_UserName", A."ItemCode" , B."ApprovalStatus", A."LineEntry"
                                FROM ' . $own_db_name . '."RESV_D" AS A
                                left join ' . $own_db_name . '."RESV_H" AS B ON A."U_DocEntry" = B."U_DocEntry"
                                left join ' . $own_db_name . '."OUSR_H" AS C ON B."Requester" = C."U_UserID"
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


            $details = ReservationDetails::where("U_DocEntry", "=", $id)->get();
            return response()->json([
                "header" => $header,
                "rows" => $arr,
                "division" => $arr_division,
                "user_nik" => $arr_user_nik,
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
                //"trace" => $exception->getTrace(),
            ]);
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
        // set created at
        $created = (!empty($header)) ? $header->created_at : Carbon::now();
        // process header
//        $division = Division::where("U_DocEntry", "=", $request->Division['U_DocEntry'])->first();
//        $department = Department::where("U_DocEntry", "=", $division->U_DeptEntry)->first();
//        if (!$department) {
//            return response()->json([
//                "errors" => true,
//                "message" => "Cannot Find Department!"
//            ]);
//        }
        $doc_entry = $this->processHeaderDoc($header, $created, $request);
        if ($doc_entry) {
            //ToDo
            return $this->loopDetails($details, $doc_entry, $form, $request);
        } else {
            return response()->json([
                "errors" => true,
                "message" => "Failed process header!"
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
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitApproval(Request $request)
    {
        $form = $request->form;
        $cherry_token = $request->cherry_token;
        $list_code = Http::post(env('CHERRY_REQ'), [
            'CommandName' => 'GetList',
            'ModelCode' => 'ExternalDocuments',
            'UserName' => $request->user()->username,
            'Token' => $cherry_token,
            'ParameterData' => []
        ]);

        $resv_code = '';
        foreach ($list_code['Data'] as $datum) {
            if ($datum['Name'] == 'E-RESERVATION') {
                $resv_code = $datum['Code'];
            }
        }
        $username = $request->user()->username;
        $company_code = $request->user()->company_code;

        $response = Http::get(env('CHERRY_CHECK_EMPLOYEE'), [
            'username' => $username,
            'token' => $cherry_token,
            'companyCode' => $company_code,
            'q' => $form['RequesterName']
        ]);

        $employee_code = '';
        foreach ($response->collect() as $item) {
            if ($item['Nik'] == $form['Requester']) {
                $employee_code = $item['EmployeeCode'];
            }
        }

        $response = Http::post(env('CHERRY_REQ'), [
            'CommandName' => 'Submit',
            'ModelCode' => 'GADocuments',
            'UserName' => $username,
            'Token' => $cherry_token,
            'ParameterData' => [],
            'ModelData' => [
                'TypeCode' => $resv_code,
                'CompanyCode' => $company_code,
                'Date' => date('m/d/Y'),
                'EmployeeCode' => $employee_code,
                'DocumentReferenceID' => $form['DocNum'],
                'CallBackAccessToken' => 'http://sbo2.imip.co.id:3000/e-resv/api/callback',
                'DocumentContent' => '
                    <table>
                        <tr>
                            <td>DocNum</td>
                            <td>' . $form['DocNum'] . '</td>
                        </tr>
                        <tr>
                            <td>Request Type</td>
                            <td>' . $form['RequestType'] . '</td>
                        </tr>
                        <tr>
                            <td>Request Date</td>
                            <td>' . $form['DocDate'] . '</td>
                        </tr>
                    </table>
                ',
                'Notes' => $form['Memo']
            ]
        ]);
        if ($response['MessageType'] == 'error') {
            return response()->json($response);
        }

        ReservationHeader::where('U_DocEntry', '=', $form['U_DocEntry'])
            ->update([
                'ApprovalStatus' => 'W'
            ]);

        return response()->json([
            "errors" => false,
            "U_DocEntry" => $form['U_DocEntry'],
            "message" => ($form['U_DocEntry'] != 'null') ? "Data updated!" : "Data inserted!"
        ]);

        // App/Httt/Traits/Approval
        // return $this->actionApproval($request, $form);
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
        $form = json_decode($request->form);
        $data_header = ReservationHeader::select("OUSR_COMP.U_DbCode")
            ->leftJoin("OUSR_COMP", "RESV_H.Company", "OUSR_COMP.U_DocEntry")
            ->where("RESV_H.U_DocEntry", "=", $form->U_DocEntry)->get();
        foreach ($data_header as $key => $value) {
            $schema = env("DB_SCHEMA");
            $db_name = $value->U_DbCode;
            $header = ReservationHeader::select(
                "RESV_H.*",
                "OUSR_COMP.U_DbCode",
                "OUSR_COMP.U_DbCode As CompanyName",
                "OUSR_H.U_Division",
                "OUSR_H.U_UserName",
                DB::raw('(
                    SELECT ' . $schema . '.OUSR_H."U_UserName"
                    FROM ' . $schema . '.OUSR_H
                    where ' . $schema . '.OUSR_H."U_UserID" = ' . $schema . '.RESV_H."CreatedBy"
                    ) AS "CreatedName"'),
                DB::raw('(
                    SELECT "U_UserName"
                    FROM ' . $schema . '."OUSR_H"
                    where ' . $schema . '."OUSR_H"."U_UserID" = RESV_H."CreatedBy") AS "CreatedUserBy"'),
                // DB::raw('(
                //     SELECT "DocNum"
                //     FROM ' . $db_name . '."OPRQ"
                //     where ' . $db_name . '."OPRQ"."DocEntry" = RESV_H."SAP_PRNo") AS "SAP_PRNo"'),

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
                // DB::raw('(SELECT STRING_AGG(T0."DocNum",\', \') as "GI_No"
                //     FROM ' . $db_name . '."@DGN_EI_OIGR" G0
                //     LEFT JOIN  ' . $db_name . '."@DGN_EI_IGR1"  G1  ON G0."DocEntry" = G1."DocEntry"
                //     LEFT JOIN  ' . $db_name . '.IGE1 T1 ON T1."U_DGN_IReqId" = G1."DocEntry" AND T1."U_DGN_IReqLineId" = G1."LineId"
                //     LEFT JOIN  ' . $db_name . '.OIGE T0 ON T1."DocEntry" = T0."DocEntry"
                //     WHERE G0."DocEntry" = RESV_H."SAP_GIRNo") AS "SAP_GINo"'),
                DB::raw('CONCAT(OUSR_H."U_UserName", CONCAT( \'/\', OUSR_H."U_NIK")) AS "UserName"'),
                DB::raw('CONCAT(OUSR_H."U_Division", CONCAT( \'/\', OUSR_H."U_Department")) AS "Department"')
            )
                ->leftJoin("OUSR_COMP", "RESV_H.Company", "OUSR_COMP.U_DocEntry")
                ->leftJoin("OUSR_H", "RESV_H.Requester", "OUSR_H.U_UserID")
                ->where("RESV_H.U_DocEntry", "=", $form->U_DocEntry)
                ->first();

            $details = ReservationDetails::where("U_DocEntry", "=", $header->U_DocEntry)
                // ->where("RequestType", "=", "NPB")
                ->get();

            $data_letter = [];
            foreach ($details as $key => $value) {
                $data_letter [] = [
                    "NO" => ($key + 1),
                    "ITEMCODE" => $value->ItemCode,
                    "ITEMNAME" => $value->ItemName,
                    "UOM" => $value->UoMCode,
                    "QTY" => $value->ReqQty,
                    "DATE" => $value->ReqDate,
                    "NOTES" => $value->ReqNotes,
                ];
            }

            $letter_template = new TemplateProcessor(
                public_path(
                    'template/NPB.docx'
                )
            );

            $letter_template->setValue('REQUESTOR', $header->UserName);
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
            $file_path_name = public_path(
                '/Attachment/NPB/'
            );

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
            $file_name = $file_path_name . $request->user()->U_UserID . strtotime(date('Y-m-d')) . '.docx';

            $letter_template->saveAs($file_name);
            $pdf_file = $file_path_name . $request->user()->U_UserID . strtotime(date('Y-m-d')) . ".pdf";
            $word_file = new \COM('Word.Application') or die('Could not initialise Object.');
            $word_file->Visible = 0;
            $word_file->DisplayAlerts = 0;
            $word_file->Documents->Open(
                $file_path_name . $request->user()->U_UserID . strtotime(date('Y-m-d')) . '.docx'
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
            RemoveAttachment::dispatch($all_files)->delay(now()->addMinutes(3));

            return response()->json([
                'url' => url('/Attachment/NPB/' . $request->user()->U_UserID . strtotime(date('Y-m-d')) . ".pdf")
            ]);
        }
    }
}