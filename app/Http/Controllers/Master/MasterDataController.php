<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Traits\ConnectHana;
use App\Models\UserCompany;
use App\Models\UserItmGrp;
use App\Models\UserWhs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    use ConnectHana;

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
        $select_type = isset($request->searchType) ? (string)$request->searchType : null;
        $offset = ($pages - 1) * $row_data;
        $result = array();

        $item_whs = '';
        $item_itm = '';

        $user_whs = UserWhs::where("user_id", "=", $request->user()->username)->get();
        $user_item_code = UserItmGrp::where("user_id", "=", $request->user()->username)->get();
        $user_company = UserCompany::where("user_id", "=", $request->user()->username)->first();

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
        $whs = ($form) ? $form->WhsCode : $item_whs;

        $sql_count = '
					SELECT COUNT(*) AS "CountData"
					FROM ' . $db_name . '."OITM" AS T0
                    LEFT JOIN
                    (
                         SELECT X1."U_ItemCode",

                                 SUM (X1."U_ReqQty"- IFNULL(X1."U_Issued",0) ) AS "PendingQty"

                                FROM ' . $db_name . '."@DGN_EI_OIGR" As X0
                                LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" AS X1 ON X0."DocEntry" = X1."DocEntry"
                                WHERE X0."Canceled" = \'N\' AND X0."Status" =\'O\'
                                AND X1."U_WhsCode" IN ( \'' . $whs . '\')
                                AND X1."U_ReqQty" > IFNULL(X1."U_Issued",0)
                         GROUP BY  X1."U_ItemCode"


                    ) AS GIR ON  T0."ItemCode" = GIR."U_ItemCode"
                    WHERE
                     T0."ItmsGrpCod" IN ( ' . $item_itm . ')
				';


        if ($select_type == 'Item Code') {
            $sql_count .= ' AND T0."ItemCode" LIKE( \'%' . $search . '%\' ) ';
        } elseif ($select_type == 'Item Name') {
            $sql_count .= ' AND T0."ItemName" LIKE( \'%' . $search . '%\' )';
        } elseif ($select_type == 'Whs') {
            $sql_count .= ' AND  T0."DfltWh" LIKE( \'%' . $search . '%\' )';
        } elseif ($select_type == 'Category') {
            $sql_count .= ' AND T0."U_ItemType" LIKE( \'%' . $search . '%\' )';
        }


        $rs = odbc_exec($connect, $sql_count);
        $arr = odbc_fetch_array($rs);
        $result["total"] = (int)$arr['CountData'];

        $sql = '
        SELECT T0."ItemCode",
            T0."ItemName",
            T0."InvntryUom",
            T0."InvntItem",
            T0."U_ItemType",
            T0."DfltWH",
            IFNULL(
                (SELECT SUM( X."OnHand")
                    FROM ' . $db_name . '.OITW X
                    WHERE X."ItemCode" = T0."ItemCode" AND X."WhsCode" IN ( \'' . $whs . '\')
                    ), 0
                ) AS "OnHand",
            IFNULL( (SELECT SUM( X."OnHand") FROM ' . $db_name . '.OITW X  WHERE X."ItemCode" = T0."ItemCode"
                    AND X."WhsCode" IN ( \'' . $whs . '\')
                    ),0) - IFNULL(GIR."PendingQty",0) AS "Available"
            FROM ' . $db_name . '."OITM" AS T0
            LEFT JOIN
            (
                SELECT X1."U_ItemCode",
                        SUM (X1."U_ReqQty"- IFNULL(X1."U_Issued",0) ) AS "PendingQty"
                FROM ' . $db_name . '."@DGN_EI_OIGR" As X0
                LEFT JOIN ' . $db_name . '."@DGN_EI_IGR1" AS X1 ON X0."DocEntry" = X1."DocEntry"
                WHERE X0."Canceled" = \'N\' AND X0."Status" =\'O\'
                AND X1."U_WhsCode" IN ( \'' . $whs . '\')
                AND X1."U_ReqQty" > IFNULL(X1."U_Issued",0)
                GROUP BY  X1."U_ItemCode"
            ) AS GIR ON  T0."ItemCode" = GIR."U_ItemCode"
            WHERE
            T0."ItmsGrpCod" IN ( ' . $item_itm . ')

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

        if ($row_data != '-1') {
            $sql .= ' LIMIT ' . $row_data . '
                    OFFSET ' . $offset . '
                    ';
        }
        // dd($sql);
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
                "U_ItemType" => (odbc_result($rs, "U_ItemType")) ? odbc_result($rs, "U_ItemType") : 'RS',
            ];
        }

        $item_groups = UserItmGrp::where('user_id', '=', $request->user()->username)->get();
        $arr_item_groups = [];
        $user_db = env('LARAVEL_ODBC_USERNAME');

        foreach ($item_groups as $item_group) {
            $arr_item_groups[] = [
                "ItmsGrpNam" => $item_group->item_group_name,
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLatestRequest(Request $request)
    {
        $req_date = $request->ReqDate;
        $whs_code = $request->WhsCode;
        $item_code = $request->ItemCode;

        $last_request = DB::table("resv_details")
            ->leftJoin("resv_headers", "resv_details.doc_num", "resv_headers.id")
            ->leftJoin("users", "resv_headers.requester_id", "users.id")
            ->select("resv_details.req_date", "resv_details.req_note", "users.name")
            ->whereNotIn("resv_headers.approval_status", ["-", "N", "W"])
            ->where("resv_details.req_date", "<", $req_date)
            ->where("resv_details.whs_code", "=", $whs_code)
            ->where("resv_details.item_code", "=", $item_code)
            ->orderBy("resv_details.line_num", "DESC")
            ->first();

        return response()->json([
            "rows" => $last_request
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

        $last_request = DB::table("resv_details")
            ->leftJoin("resv_headers", "resv_details.doc_num", "resv_headers.id")
            ->leftJoin("users", "resv_headers.requester_id", "users.id")
            ->select(
                "resv_details.req_date",
                "resv_details.req_qty",
                "resv_details.req_note",
                "users.name",
                "resv_headers.doc_num",
                "resv_details.line_num"
            )
            ->whereNotIn("resv_headers.approval_status", ["-", "N", "W"])
            //->where("resv_details.ReqDate", "<", $req_date)
            ->where("resv_details.whs_code", "=", $whs_code)
            ->where("resv_details.item_code", "=", $item_code)
            ->orderBy("resv_details.req_date", "DESC")
            ->offset($offset)
            ->limit($row_data);

        return response()->json([
            "total" => $last_request->count(),
            "rows" => $last_request->get(),
        ]);
    }
}
