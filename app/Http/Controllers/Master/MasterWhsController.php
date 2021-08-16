<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Traits\ConnectHana;
use Illuminate\Http\Request;

class MasterWhsController extends Controller
{
    use ConnectHana;
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $connect = $this->connectHana();
        $db_name = (env('DB_SAP') !== null) ? env('DB_SAP') : 'IMIP_TEST_1217';

        $sql = '
					select "WhsCode", "WhsName",
					       ROW_NUMBER() OVER (PARTITION BY "WhsCode" ORDER BY "WhsCode" DESC) AS "LineNum"
					from ' . $db_name . '.OWHS
				';
        $rs = odbc_exec($connect, $sql);

        if (!$rs) {
            exit("Error in SQL");
        }
        $arr = [];
        $index = 1;
        while (odbc_fetch_row($rs)) {
            $arr[] = [
                "name" => odbc_result($rs, "WhsCode"),
                "whs_name" => odbc_result($rs, "WhsName"),
                "line_num" => $index,
            ];
            $index++;
        }

        return $this->success([
            "simple" => $arr
        ]);
    }
}
