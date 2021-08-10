<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ViewEmployee;
use App\Traits\ConnectHana;
use App\Models\UserCompany;
use App\Models\UserItmGrp;
use App\Models\UserWhs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    use ConnectHana;

    protected $connect;

    public function __construct()
    {
        $this->connect = $this->connectHana();
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemMasterData(Request $request): \Illuminate\Http\JsonResponse
    {
        $connect = $this->connectHana();

        $options = json_decode($request->options);
        $pages = isset($options->page) ? (int)$options->page : 1;
        $selectedItem = $request->itemGroups ?? null;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 10;
        $form = json_decode($request->form);
        $search = isset($request->search) ? (string)$request->search : "";
        $item_type = isset($form->ItemType) ? (string)$form->ItemType : "";
        $select_type = isset($request->searchType) ? (string)$request->searchType : null;
        $offset = ($pages - 1) * $row_data;
        $result = array();

        if ($item_type == 'Ready Stock') {
            $item_type = "('RS')";
        } else {
            $item_type = "('NRS')";
        }

        $item_whs = '';
        $item_itm = '';

        $user_whs = UserWhs::where("user_id", "=", $request->user()->id)->get();
        $user_item_code = UserItmGrp::where("user_id", "=", $request->user()->id)->get();
        $user_company = UserCompany::where("user_id", "=", $request->user()->id)->first();

        foreach ($user_whs as $user_wh) {
            $item_whs .= "'$user_wh->whs_code',";
        }

        foreach ($user_item_code as $user_item) {
            $item_itm .= "'$user_item->item_group',";
        }

        $item_whs = rtrim($item_whs, ', ');
        $item_itm = rtrim($item_itm, ', ');
        if ($selectedItem) {
            $item_itm = $selectedItem;
        }

        $db_name = (isset($form->CompanyName)) ? $form->CompanyName : $user_company->company->db_code;
        $whs = (isset($form->WhsCode)) ? "'$form->WhsCode'" : $item_whs;

        $sql = '
        SELECT T0."ItemCode",
            T0."ItemName",
            T0."InvntryUom",
            T0."InvntItem",
            IFNULL(T0."U_ItemType", \'RS\') AS "U_ItemType",
            T0."DfltWH",
            IFNULL(
                (SELECT SUM( X."OnHand")
                    FROM ' . $db_name . '.OITW X
                    WHERE X."ItemCode" = T0."ItemCode" AND X."WhsCode" IN ( ' . $whs . ')
                    ), 0
                ) AS "OnHand",
            IFNULL( (SELECT SUM( X."OnHand") FROM ' . $db_name . '.OITW X  WHERE X."ItemCode" = T0."ItemCode"
                    AND X."WhsCode" IN ( ' . $whs . ')
                    ),0) - IFNULL(GIR."PendingQty",0) AS "Available"
            FROM ' . $db_name . '."OITM" AS T0
            LEFT JOIN
            (
                SELECT X1."U_ItemCode",
                        SUM (X1."U_ReqQty"- IFNULL(X1."U_Issued",0) ) AS "PendingQty"
                FROM ' . $db_name . '."@DGN_EI_OIGR" As X0
                LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" AS X1 ON X0."DocEntry" = X1."DocEntry"
                WHERE X0."Canceled" = \'N\' AND X0."Status" =\'O\'
                AND X1."U_WhsCode" IN ( ' . $whs . ')
                AND X1."U_ReqQty" > IFNULL(X1."U_Issued",0)
                GROUP BY  X1."U_ItemCode"
            ) AS GIR ON  T0."ItemCode" = GIR."U_ItemCode"
            WHERE
            T0."ItmsGrpCod" IN ( ' . $item_itm . ') AND IFNULL(T0."U_ItemType", \'RS\') IN ' . $item_type . '

        ';

        if ($select_type == 'Item Code') {
            $sql .= ' AND T0."ItemCode" LIKE( \'%' . $search . '%\' ) ';
        } elseif ($select_type == 'Item Name') {
            $sql .= ' AND T0."ItemName" LIKE( \'%' . $search . '%\' )';
        } elseif ($select_type == 'Whs') {
            $sql .= ' AND  T0."DfltWh" LIKE( \'%' . $search . '%\' )';
        } elseif ($select_type == 'Category') {
            $sql .= ' AND T0."U_ItemType" LIKE( \'%' . $search . '%\' )';
        }
        //return response()->json($sql);
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

        if ($row_data != '-1') {
            $sql .= ' LIMIT ' . $row_data . '
                    OFFSET ' . $offset . '
                    ';
        }

        $rs = odbc_exec($connect, $sql);

        if (!$rs) {
            exit("Error in SQL");
        }
        $arr = [];
        while (odbc_fetch_row($rs)) {
            $arr[] = [
                "Keys" => odbc_result($rs, "ItemCode") . odbc_result($rs, "ItemCode"),
                "ItemCode" => odbc_result($rs, "ItemCode"),
                "ItemName" => mb_convert_encoding(odbc_result($rs, "ItemName"), 'UTF-8', 'UTF-8'),
                "DfltWH" => odbc_result($rs, "DfltWH"),
                "OnHand" => odbc_result($rs, "OnHand"),
                // "U_Issued" => odbc_result($rs, "U_Issued"),
                "Available" => odbc_result($rs, "Available"),
                "InvntryUom" => odbc_result($rs, "InvntryUom"),
                "InvntItem" => odbc_result($rs, "InvntItem"),
                // "DocEntry" => odbc_result($rs, "DocEntry"),
                "U_ItemType" => (odbc_result($rs, "U_ItemType")) ? odbc_result($rs, "U_ItemType") : 'RS'
            ];
        }

        $item_groups = UserItmGrp::where('user_id', '=', $request->user()->id)->get();
        $arr_item_groups = [];
        $user_db = env('LARAVEL_ODBC_USERNAME');

        foreach ($item_groups as $item_group) {
            $sql = 'SELECT T0."ItmsGrpNam"
                FROM ' . $db_name . '."OITB" AS T0
                WHERE T0."ItmsGrpCod" = ' . $item_group->item_group . ' LIMIT 1';
            $rs = odbc_exec($connect, $sql);

            if (!$rs) {
                exit("Error in SQL");
            }
            $arr_itms = '';
            while (odbc_fetch_row($rs)) {
                $arr_itms = odbc_result($rs, "ItmsGrpNam");
            }
            $item_group_name =
            $arr_item_groups[] = [
                "ItmsGrpNam" => $arr_itms,
                "U_ItmsGrpCod" => $item_group->item_group,
            ];
        }

        $result = array_merge($result, [
            "rows" => $arr,
            "item_groups" => $arr_item_groups,
        ]);

        return response()->json($result);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemGroupCode(Request $request)
    {
        $user_company = UserCompany::where("user_id", "=", $request->user()->id)->first();
        $db_name = $user_company->company->db_code;
        $sql = '
        SELECT T0.*
            FROM ' . $db_name . '."OITB" AS T0
           ';

        $rs = odbc_exec($this->connect, $sql);

        if (!$rs) {
            exit("Error in SQL");
        }
        $arr = [];
        while (odbc_fetch_row($rs)) {
            $arr[] = [
                "item_group_code" => odbc_result($rs, "ItmsGrpCod"),
                "item_group_name" => odbc_result($rs, "ItmsGrpNam"),
            ];
        }
        return $this->success([
            'rows' => $arr
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLatestRequest(Request $request)
    {
        $req_date = $request->ReqDate;
        $whs_code = $request->WhsCode;
        $item_code = $request->ItemCode;

        $last_request = DB::connection('laravelOdbc')
            ->table("RESV_D")
            ->leftJoin("RESV_H", "RESV_D.U_DocEntry", "RESV_H.U_DocEntry")
            ->select("RESV_D.ReqDate", "RESV_D.ReqNotes", "RESV_H.Requester")
            ->whereNotIn("RESV_H.ApprovalStatus", ["-", "N", "W"])
            ->where("RESV_D.ReqDate", "<", $req_date)
            ->where("RESV_D.WhsCode", "=", $whs_code)
            ->where("RESV_D.ItemCode", "=", $item_code)
            ->orderBy("RESV_D.LineNum", "DESC")
            ->first();

        if ($last_request) {
            $user = ViewEmployee::where('Nik', '=', $last_request['Requester'])
                ->first();

            $data = [
                'ReqDate' => $last_request['ReqDate'],
                'ReqNotes' => $last_request['ReqNotes'],
                'U_UserName' => ($user) ? $user->Name : '',
            ];
        } else {
            $data = [
                'ReqDate' => '',
                'ReqNotes' => '',
                'U_UserName' => '',
            ];
        }

        return response()->json([
            "rows" => $data
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListRequest(Request $request)
    {
        $options = json_decode($request->options);
        $pages = isset($options->page) ? (int)$options->page : 1;
        $selectedItem = isset($request->itemGroups) ? $request->itemGroups : null;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 10;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "U_ItemCode";
        $order = isset($options->sortDesc[0]) ? (string)$options->sortDesc[0] : "desc";
        $offset = ($pages - 1) * $row_data;

        $req_date = $request->reqDate;
        $whs_code = $request->whsCode;
        $item_code = $request->itemCode;

        $last_requests = DB::connection('laravelOdbc')
            ->table("RESV_D")
            ->leftJoin("RESV_H", "RESV_D.U_DocEntry", "RESV_H.U_DocEntry")
            ->select(
                "RESV_D.ReqDate",
                "RESV_D.ReqQty",
                "RESV_D.ReqNotes",
                "RESV_H.U_DocEntry",
                "RESV_H.DocNum",
                "RESV_D.LineNum",
                "RESV_H.Requester"
            )
            ->whereNotIn("RESV_H.ApprovalStatus", ["-", "N", "W"])
            //->where("RESV_D.ReqDate", "<", $req_date)
            ->where("RESV_D.WhsCode", "=", $whs_code)
            ->where("RESV_D.ItemCode", "=", $item_code)
            ->orderBy("RESV_D.ReqDate", "DESC")
            ->offset($offset)
            ->limit($row_data);

        $data = [];
        foreach ($last_requests->get() as $item) {
            $user = ViewEmployee::where('Nik', '=', $item['Requester'])
                ->first();

            $data[] = [
                'ReqDate' => $item['ReqDate'],
                'ReqNotes' => $item['ReqNotes'],
                'ReqQty' => $item['ReqQty'],
                'U_DocEntry' => $item['U_DocEntry'],
                'DocNum' => $item['DocNum'],
                'LineNum' => $item['LineNum'],
                'U_UserName' => ($user) ? $user->Name : '',
            ];
        }

        return response()->json([
            "total" => $last_requests->count(),
            "rows" => $data,
        ]);
    }
}
