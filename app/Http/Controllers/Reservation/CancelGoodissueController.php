<?php

namespace App\Http\Controllers\Reservation;

use App\Traits\ConnectHana;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserWhs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Providers\RouteServiceProvider;
use App\Models\UserCompany;
use App\Http\Controllers\Api\GoodissueController;
use App\Http\Controllers\Master\MasterUserController;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Jobs\RemoveAttachment;
use Illuminate\Support\Str;

class CancelGoodissueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */

    use ConnectHana;

    public function validateApiLogin()
    {
        if (session('B1SESSION') && session('ROUTEID')) {
            return true;
        } else {
            return false;
        }
    }

    public function authUser(Request $request)
    {
        return $request->user();
    }

    /**
     * Logout
     */
    public function logout()
    {
        $array = [];
        $params = [];
        $result = [];
        $errors = true;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/Logout");//API LOGIN
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);

        $response = curl_exec($curl);
    }

    /**
     * @param $db_name
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login($db_name): \Illuminate\Http\JsonResponse
    {
        $array = [];
        $params = [
            "UserName" => "RESV",
            "Password" => "imip#1234",
            "CompanyDB" => $db_name,
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/Login");//API LOGIN
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $routeId = "";
        $data_string = "";
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, $string) use (&$routeId) {
            $len = strlen($string);
            if (substr($string, 0, 10) == "Set-Cookie") {
                preg_match("/ROUTEID=(.+);/", $string, $match);
                if (count($match) == 2) {
                    $routeId = $match[1];
                }
            }
            return $len;
        });

        curl_exec($curl);
        curl_close($curl);
        setcookie("B1SESSION", $response_text->SessionId, time() + (86400 * 30), "/");
        setcookie("ROUTEID", $routeId, time() + (86400 * 30), "/");
        session([
            'B1SESSION' => $response_text->SessionId,
            'ROUTEID' => $routeId,
            'CompanyDB' => $db_name,
        ]);
        return response()->json([
            'B1SESSION' => $response_text->SessionId,
            'ROUTEID' => $routeId
        ]);
    }

    /**
     * @return array[]
     */
    public function getCostcenter()
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/DistributionRules";
        $array_data = [];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers
        ));
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $array_data = [];
        $array_data_lvl1 = [];
        $array_data_lvl2 = [];
        $level1 = [];
        $level2 = [];
        if (!empty($response_text->value)) {
            foreach ($response_text->value as $key_detail => $value_detail) {
                if ($value_detail->InWhichDimension == 1 && $value_detail->Active == 'tYES') {
                    $level1 = [
                        'FactorCode' => $value_detail->FactorCode,
                        'FactorText' => $value_detail->FactorDescription
                    ];
                    array_push($array_data_lvl1, $level1);
                } elseif ($value_detail->InWhichDimension == 2 && $value_detail->Active == 'tYES') {
                    $level2 = [
                        'FactorCode' => $value_detail->FactorCode,
                        'FactorText' => $value_detail->FactorDescription
                    ];
                    array_push($array_data_lvl2, $level2);
                }
            }
        }
        $array_data = ['array_data_lvl1' => $array_data_lvl1, 'array_data_lvl2' => $array_data_lvl2];
        // echo "<pre>";
        // print_r($array_data);
        // echo "</pre>";
        // die();
        curl_close($curl);
        return $array_data;
    }

    /**
     * @param $docEntry
     * @param $db_name
     *
     * @return array|mixed
     */
    public function getDocumentDetail($docEntry, $db_name)
    {
        // $db_name=$request->companyItem;

        $array = [];
        $params = [
            "UserName" => "RESV",
            "Password" => "imip#1234",
            "CompanyDB" => $db_name,
        ];
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $docNumFilterValue = '';
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits(" . $docEntry . ")";
        $array_data = [];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers
        ));
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $array_data = [];
        $detail_header = [];
        $header = [];
        if (!empty($response_text->DocEntry)) {
            $array_data = $response_text;
        }
        // echo "<pre>";
        // print_r($response_text);
        // echo "</pre>";
        // die();
        curl_close($curl);
        return $array_data;
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function postCanceling($params)
    {
        $errors = true;
        $docEntry = null;
        $message = "";
        $result = [];
        $array = [];
        $docNum = "";
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/InventoryGenEntries");//GI
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        if (isset($response_text->error->code)) {
            $message .= $response_text->error->message->value;
        } else {
            $errors = false;
            $docEntry = $response_text->DocEntry;
            $docNum = $response_text->DocNum;
            $message = "Ok";
        }

        $result = ["errors" => $errors, "docEntry" => $docEntry, "message" => $message, "docNum" => $docNum];
        return $result;
    }

    public function getTransValue($params)
    {
        $companyItem = $params['db_name'];
        $DocEntry = $params['DocEntry'];
        $LineId = $params['LineId'];
        $connect = $this->connectHana();
        $message = "Error in SQL TransValue";
        $result = 0;
        $data = [];
        $array_data = [];
        $sql = '
                    select "TransValue", "CreatedBy", "ItemCode", "DocLineNum"
                    from ' . $companyItem . '."OINM" as T0
                    where T0."TransType"=\'60\'
                    AND T0."CreatedBy" IN (' . $DocEntry . ') AND T0."DocLineNum" IN (' . $LineId . ')
                ';
        $rs = odbc_exec($connect, $sql);
        $arr = [];
        if ($rs) {
            $arr = odbc_fetch_array($rs);
            $message = "OK";
            $result = 1;
        }
        $array_data = ["message" => $message, "result" => $result, "data" => $arr];
        return $array_data;
    }

    /**
     * @param $params
     * @param $U_DocNum
     * @return array
     */
    public function updateU_Issue($params, $U_DocNum)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $parameter = [];
        $curl = curl_init();
        $url = "https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR(" . $U_DocNum . ")";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $result_all = [];
        $result = 0;
        $result_data_api = [];
        if (!empty($response_text->error->code)) {//if Error
            $message = 'Data Not Saved.';
        } else {
            $message = 'Updating Data Item Success';
            $result = 1;
            $result_data_api = $response_text;
        }
        $result_all = ['result' => $result, 'message' => $message, 'result_data_api' => $result_data_api];
        return $result_all;
    }

    /**
     * @param $params
     * @param $DocEntry
     *
     * @return array
     */
    public function updateStatusCanceling($params, $DocEntry)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $parameter = [];
        $curl = curl_init();
        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits(" . $DocEntry . ")";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $result_all = [];
        $result = 0;
        $result_data_api = [];
        if (!empty($response_text->error->code)) {//if Error
            $message = 'Data Not Saved.';
        } else {
            $message = 'Updating Data Item Success';
            $result = 1;
            $result_data_api = $response_text;
        }
        $result_all = ['result' => $result, 'message' => $message, 'result_data_api' => $result_data_api];
        return $result_all;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelGoodissues(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $params = [];
            $detail = [];
            $DocumentLines = [];
            $errors = true;
            $message = "";
            $result = [];
            $editedItem = $request->editedItem;
            $docsdatadetail = $request->docsdatadetail;
            $db_name = $editedItem['companyItem'];
            if (session('CompanyDB') != $db_name) {
                $this->logout();
                $this->login($db_name);
            }
            $DocEntry = $editedItem['DocEntry'];
            $DocNum = $editedItem['DocNum'];
            $DocDate = $editedItem['DocDate'];
            $Comments = $editedItem['Comments'];
            $U_INV_TYPE = $editedItem['U_INV_TYPE'];
            $Comments = $editedItem['Comments'];
            $U_ERESV_USER = $request->user()->U_UserID;
            $parameter = [];
            $parameter_GIR = [];
            $data_GIR = [];
            $array_GIR = [];
            $line_GIR = [];
            $make_issued = [];
            $make_status = [];
            $status_GIR = [];
            foreach ($docsdatadetail as $key => $value) {
                $parameter = ['DocEntry' => $DocEntry, 'LineId' => $value['LineNum'], 'db_name' => $db_name];
                $getTransValue = $this->getTransValue($parameter);
                if ($getTransValue['result'] == 1) {
                    $LineTotal = abs($getTransValue['data']['TransValue']);
                    $detail = [
                        'U_GI_LINE_ID' => $value['LineNum'],
                        'U_GI_ID' => $DocEntry,
                        'U_DGN_IReqId' => $value['U_DGN_IReqId'],
                        'U_DGN_IReqLineId' => $value['U_DGN_IReqLineId'],
                        'ItemCode' => $value['ItemCode'],
                        'ItemDescription' => $value['ItemDescription'],
                        'Quantity' => $value['Quantity'],
                        'WarehouseCode' => $value['WarehouseCode'],
                        'LineTotal' => $LineTotal,
                        'CostingCode' => $value['CostingCode'],
                        'CostingCode2' => $value['CostingCode2']
                    ];
                    $data_GIR = $this->getGIRById($value['U_DGN_IReqId']);
                    if (!empty($data_GIR->DocEntry)) {
                        foreach ($data_GIR->DGN_EI_IGR1Collection as $key_col => $value_col) {
                            if ($value_col->LineId == $value['U_DGN_IReqLineId']) {
                                $hasil_issue = $value_col->U_Issued - $value['Quantity'];
                                $line_GIR = [
                                    'Status' => $data_GIR->Status,
                                    'DocEntry' => $data_GIR->DocEntry,
                                    'LineId' => $value_col->LineId,
                                    'U_Issued' => $hasil_issue
                                ];
                                array_push($array_GIR, $line_GIR);
                            }
                        }
                    }
                }
                array_push($DocumentLines, $detail);
            }
            $params = [
                'U_ERESV_USER' => $U_ERESV_USER,
                'U_INV_TYPE' => $U_INV_TYPE,
                'DocDate' => $DocDate,
                'Comments' => $Comments,
                'U_ORIGIN_NO' => $DocNum,
                'DocumentLines' => $DocumentLines
            ];
            $postCanceling = $this->postCanceling($params);
            $docEntry_ = null;
            if ($postCanceling['errors'] == false) {
                $docNum_ = $postCanceling['docNum'];
                $param_status = [];
                $param_status = ['U_CANCELED_NO' => $docNum_];
                $updateStatusCanceling = $this->updateStatusCanceling($param_status, $DocEntry);
                if ($updateStatusCanceling['result'] != 1) {
                    $message .= "Update Status Cancel GI" . $DocNum . " Failed";
                }
                $errors = false;
                $docEntry_ = $postCanceling['docEntry'];
                $message .= "Canceling Good Issue " . $DocNum . " is Success
                    with DocEntry " . $docEntry_ . " in Good Receipt. ";
                foreach ($array_GIR as $key => $value) {
                    $DGN_EI_IGR1Collection_GIR = ['LineId' => $value['LineId'], 'U_Issued' => $value['U_Issued']];
                    array_push($make_issued, $DGN_EI_IGR1Collection_GIR);
                    $parameter_GIR = ['DGN_EI_IGR1Collection' => $make_issued];
                    $data_updateGIR = $this->updateU_Issue($parameter_GIR, $value['DocEntry']);
                    if ($data_updateGIR['result'] != 1) {
                        $message .= "Update Status(Close) of GIR " . $value['DocEntry'] . " Failed.";
                    }
                    if ($value['Status'] == "C") {
                        $status_GIR = ['Status' => 'O'];
                        $status = '';
                        array_push($make_status, $status);
                        $data_update_statusGIR = $this->updateU_Issue($status_GIR, $value['DocEntry']);
                        if ($data_updateGIR['result'] != 1) {
                            $message .= "Update Status of GIR " . $value['DocEntry'] . " Failed.";
                        }
                    }
                }
            } else {
                $message .= "Canceling Good Issue " . $DocNum . " Failed. " . $postCanceling['message'];
            }

            $result = ["errors" => $errors, "docEntry" => $docEntry_, "message" => $message];
            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param $U_DocNum
     * @return mixed
     */
    public function getGIRById($U_DocNum)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR(" . $U_DocNum . ")";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers
        ));
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $array_data = [];
        curl_close($curl);
        return $response_text;
    }

    // public function getDetail(Request $request): \Illuminate\Http\JsonResponse
    // {
    //     $errors=true;
    //     if (session('CompanyDB') != $request->companyItem){
    //         $this->logout();
    //         $this->login($request);
    //     }
    //     $result=[];
    //     $detail_data=[];
    //     $array_data=[];
    //     $editedItem=$request->editedItem;
    //     $DocEntry=$editedItem['DocEntry'];
    //     if(!empty($DocEntry)){
    //         $docDetail=$this->getDocumentDetail($DocEntry,$request);
    //         $errors=false;
    //         foreach ($docDetail->DocumentLines as $key => $value) {
    //             $detail_data=[
    //'U_DGN_IReqId'=>$value->U_DGN_IReqId,
    //'U_DGN_IReqLineId'=>$value->U_DGN_IReqLineId,
    //'LineNum'=>$value->LineNum,
    //'ItemCode'=>$value->ItemCode,
    //'ItemDescription'=>$value->ItemDescription,
    //'Quantity'=>$value->Quantity,'Price'=>$value->Price,
    //'WarehouseCode'=>$value->WarehouseCode,
    //'CostingCode'=>$value->CostingCode,'CostingCode2'=>$value->CostingCode2];
    //             array_push($array_data, $detail_data);
    //         }
    //     }
    //     $result = array_merge($result, [
    //         "detail" => $array_data
    //     ]);
    //     return response()->json($result);
    // }

    /**
     * @param Request $request
     *
     * @return array|array[]|string[]
     */
    public function getDataindex(Request $request)
    {
        $result = [];
        $companyItem = $request->companyItem;
        $options = json_decode($request->options);
        $docNumFilterValue = json_decode($request->docNumFilterValue);
        $statusItemFilterValue = $request->statusItemFilterValue;
        $todocdateFilterValue = $request->todocdateFilterValue;
        $fromdocdateFilterValue = $request->fromdocdateFilterValue;
        $pages = isset($options->page) ? (int)$options->page : 1;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 10;
        $offset = ($pages - 1) * $row_data;
        $all_doc = [];
        $db_name = $request->companyItem;
        if (session('CompanyDB') != $request->companyItem) {
            $this->logout();
            $this->login($db_name);
        }

        if ($statusItemFilterValue == 2) {
            $statusItemFilterValue == null;
        }

        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top=" . $row_data . "&\$skip="
            . $offset . "&\$orderby=DocDate%20desc";
        if (!empty($todocdateFilterValue) && !empty($fromdocdateFilterValue)) {
            if (!empty($docNumFilterValue) || !empty($statusItemFilterValue)) {
                if (!empty($docNumFilterValue) && !empty($statusItemFilterValue)) {
                    if ($statusItemFilterValue == 2) {
                        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                            . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                            . $docNumFilterValue . "')%20and%20U_CANCELED_NO%20eq%20null%20and%20DocDate%20ge%20'"
                            . $fromdocdateFilterValue . "'%20and%20DocDate%20le%20'"
                            . $todocdateFilterValue . "')&\$orderby=DocDate%20desc";
                    } else {
                        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                            . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                            . $docNumFilterValue . "')%20and%20U_CANCELED_NO%20ne%20null%20and%20DocDate%20ge%20'"
                            . $fromdocdateFilterValue . "'%20and%20DocDate%20le%20'"
                            . $todocdateFilterValue . "')&\$orderby=DocDate%20desc";
                    }
                } else {
                    if (!empty($docNumFilterValue)) {
                        if ($statusItemFilterValue == 2) {
                            $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                                . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                                . $docNumFilterValue . "')%20and%20U_CANCELED_NO%20eq%20null%20and%20DocDate%20ge%20'"
                                . $fromdocdateFilterValue . "'%20and%20DocDate%20le%20'"
                                . $todocdateFilterValue . "')&\$orderby=DocDate%20desc";
                        } else {
                            $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                                . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                                . $docNumFilterValue . "')%20and%20U_CANCELED_NO%20ne%20null%20and%20DocDate%20ge%20'"
                                . $fromdocdateFilterValue . "'%20and%20DocDate%20le%20'"
                                . $todocdateFilterValue . "')&\$orderby=DocDate%20desc";
                        }
                    } else {
                        if ($statusItemFilterValue == 2) {
                            $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                                . $row_data . "&\$skip="
                                . $offset . "&\$filter=U_CANCELED_NO%20eq%20null%20and%20DocDate%20ge%20'"
                                . $fromdocdateFilterValue . "'%20and%20DocDate%20le%20'"
                                . $todocdateFilterValue . "'&\$orderby=DocDate%20desc";
                        } else {
                            $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                                . $row_data . "&\$skip="
                                . $offset . "&\$filter=U_CANCELED_NO%20ne%20null%20and%20DocDate%20ge%20'"
                                . $fromdocdateFilterValue . "'%20and%20DocDate%20le%20'"
                                . $todocdateFilterValue . "'&\$orderby=DocDate%20desc";
                        }
                    }
                }
            } else {
                $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                    . $row_data . "&\$skip=" . $offset . "&\$filter=DocDate%20ge%20'"
                    . $todocdateFilterValue . "'%20and%20DocDate%20le%20'"
                    . $fromdocdateFilterValue . "'&\$orderby=DocDate%20desc";
            }
        } else {
            if (!empty($docNumFilterValue) || !empty($statusItemFilterValue)) {
                if (!empty($docNumFilterValue) && !empty($statusItemFilterValue)) {
                    $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                        . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                        . $docNumFilterValue . "')%20and%20contains(U_CANCELED_NO,'"
                        . $statusItemFilterValue . "'))&\$orderby=DocDate%20desc";
                    if ($statusItemFilterValue == 2) {
                        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                            . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                            . $docNumFilterValue . "')%20and%20U_CANCELED_NO%20eq%20null)&\$orderby=DocDate%20desc";
                    } else {
                        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                            . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                            . $docNumFilterValue . "')%20and%20U_CANCELED_NO%20ne%20null)&\$orderby=DocDate%20desc";
                    }
                } else {
                    if (!empty($docNumFilterValue)) {
                        $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                            . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(DocNum,'"
                            . $docNumFilterValue . "'))&\$orderby=DocDate%20desc";
                    } else {
                        if ($statusItemFilterValue == 2) {
                            $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                                . $row_data . "&\$skip="
                                . $offset . "&\$filter=U_CANCELED_NO%20eq%20null&\$orderby=DocDate%20desc";
                        } else {
                            $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                                . $row_data . "&\$skip="
                                . $offset . "&\$filter=U_CANCELED_NO%20ne%20null&\$orderby=DocDate%20desc";
                        }
                    }
                }
            } else {
                $url = "https://192.168.88.8:50000/b1s/v1/InventoryGenExits?\$top="
                    . $row_data . "&\$skip=" . $offset . "&\$orderby=DocDate%20desc";
            }
        }


        $array_data = [];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers
        ));
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $array_data = [];
        $detail_header = [];
        $header = [];
        if (!empty($response_text->value)) {
            $array_data = $response_text->value;
        }
        $data_ = [];
        $array_ = [];
        $inventory_type = [];
        $U_INV_TYPE_name = "";

        if (!empty($array_data)) {
            foreach ($array_data as $key => $value) {
                $status = "Yes";
                $inventory_type = $this->getInventoryTypeByID($value->U_INV_TYPE);
                if (isset($inventory_type->Name)) {
                    $U_INV_TYPE_name = $inventory_type->Name;
                }
                if ($value->U_CANCELED_NO == null) {
                    $status = "No";
                }
                $data_ = array(
                    'db_name' => $request->companyItem,
                    'status' => $status,
                    'DocEntry' => $value->DocEntry,
                    'DocNum' => $value->DocNum,
                    'DocDate' => $value->DocDate,
                    'Comments' => $value->Comments,
                    'DocumentStatus' => $value->DocumentStatus,
                    'CancelStatus' => $value->CancelStatus,
                    'U_INV_TYPE' => $value->U_INV_TYPE,
                    'U_INV_TYPE_name' => $U_INV_TYPE_name
                );
                array_push($all_doc, $data_);
            }
        }

        $result["total"] = 1000;

        $result = array_merge($result, [
            "rows" => $all_doc,
            "url" => $url,
        ]);
        return $result;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $result = [];
            $companyItem = [];
            $master = new MasterUserController;
            $relation = $master->userRelationship($request);
            if (isset($relation->original['items'])) {
                $companyItem = $relation->original['items'];
            }
            $options = json_decode($request->options);
            $pages = isset($options->page) ? (int)$options->page : 1;
            $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 10;
            $offset = ($pages - 1) * $row_data;
            $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "DocNum";
            $order = isset($options->sortDesc[0]) ? "DESC" : "ASC";
            $search = isset($request->search) ? (string)$request->search : "";
            $select_type = isset($request->searchItem) ? (string)$request->searchItem : null;
            $todocdateFilterValue = $request->todocdateFilterValue;
            $fromdocdateFilterValue = $request->fromdocdateFilterValue;
            $filter = [
                "All",
                "Company", "DocNum", "DocDate",
                "Base Ref", "Inventory Type", "Warehouse", "CreatedBy", "Comments", "Canceled"
            ];
            $connect = $this->connectHana();
            $i = 0;
            foreach ($companyItem as $key => $value) {
                if ($select_type == 'Company') {
                    if (stripos($value, $search) !== false) {
                        $sql_count = 'select count("' . $value . '"."OIGE"."DocEntry") as countdata
                            from "' . $value . '"."OIGE"
                                left join "' . $value . '"."@INV_TYPE" on "' . $value . '"."OIGE"."U_INV_TYPE"="' . $value . '"."@INV_TYPE"."Code"
                                left join "IMIP_ERESV"."OUSR_H" on "' . $value . '"."OIGE"."U_ERESV_USER"="IMIP_ERESV"."OUSR_H"."U_UserID"
                                left join (select "DocEntry", "WhsCode" from ' . $value . '.IGE1 GROUP BY "DocEntry", "WhsCode") as whs
                                    on ' . $value . '.OIGE."DocEntry"=whs."DocEntry"
                            where "' . $value . '"."OIGE"."DocEntry" IS NOT NULL
                                ';
                    }
                } else {
                    if ($i < 1) {
                        $sql_count = 'select count("' . $value . '"."OIGE"."DocEntry") as countdata
                            from "' . $value . '"."OIGE"
                                left join "' . $value . '"."@INV_TYPE" on "' . $value . '"."OIGE"."U_INV_TYPE"="' . $value . '"."@INV_TYPE"."Code"
                                left join "IMIP_ERESV"."OUSR_H" on "' . $value . '"."OIGE"."U_ERESV_USER"="IMIP_ERESV"."OUSR_H"."U_UserID"
                                left join (select "DocEntry", "WhsCode" from ' . $value . '.IGE1 GROUP BY "DocEntry", "WhsCode") as whs
                                    on ' . $value . '.OIGE."DocEntry"=whs."DocEntry"
                            where "' . $value . '"."OIGE"."DocEntry" IS NOT NULL
                                ';
                    } else {
                        $sql_count .= ' UNION ALL select count("' . $value . '"."OIGE"."DocEntry") as countdata
                            from "' . $value . '"."OIGE"
                                left join "' . $value . '"."@INV_TYPE" on "' . $value . '"."OIGE"."U_INV_TYPE"="' . $value . '"."@INV_TYPE"."Code"
                                left join "IMIP_ERESV"."OUSR_H" on "' . $value . '"."OIGE"."U_ERESV_USER"="IMIP_ERESV"."OUSR_H"."U_UserID"
                                left join (select "DocEntry", "WhsCode" from ' . $value . '.IGE1 GROUP BY "DocEntry", "WhsCode") as whs
                                    on ' . $value . '.OIGE."DocEntry"=whs."DocEntry"
                            where "' . $value . '"."OIGE"."DocEntry" IS NOT NULL
                                ';
                    }
                    if ($select_type == 'DocNum') {
                        $sql_count .= ' AND "' . $select_type . '" LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Canceled') {
                        if (stripos("No", $search) !== false) {
                            $sql_count .= ' AND "U_CANCELED_NO" IS NULL';
                        } elseif (stripos("Yes", $search) !== false) {
                            $sql_count .= ' AND "U_CANCELED_NO" IS NOT NULL';
                        }
                    } elseif ($select_type == 'DocDate') {
                        $sql_count .= ' AND "' . $select_type . '" BETWEEN \'' .
                            $fromdocdateFilterValue . '\' AND \'' . $todocdateFilterValue . '\'';
                    } elseif ($select_type == 'Base Ref') {
                        $sql_count .= ' AND "U_REQ_NO" LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Inventory Type') {
                        $sql_count .= ' AND LOWER("' . $value . '"."@INV_TYPE"."Name") LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'CreatedBy') {
                        $sql_count .= ' AND LOWER("IMIP_ERESV"."OUSR_H"."U_UserCode") LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Comments') {
                        $sql_count .= ' AND "' . $select_type . '" LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Warehouse') {
                        $sql_count .= ' AND LOWER(whs."WhsCode") LIKE( \'%' . $search . '%\' )';
                    } else {
                        $fromdocdateFilterValue_default = date(
                            'Y-m-d',
                            mktime(0, 0, 0, date("m"), date("d") - 14, date("Y"))
                        );
                        $todocdateFilterValue_default = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d"), date("Y")));
                        $sql_count .= ' AND "DocDate" BETWEEN \'' . $fromdocdateFilterValue_default
                            . '\' AND \'' . $todocdateFilterValue_default . '\'';
                    }
                    $i++;
                }
            }
            // $total_data=0;
            if (isset($sql_count)) {
                $rs_count = odbc_exec($connect, $sql_count);
                $total_ = odbc_fetch_array($rs_count);
                $total_data = (int)$total_['COUNTDATA'];
            } else {
                $total_data = 0;
            }
            $i = 0;
            foreach ($companyItem as $key => $value) {
                if ($select_type == 'Company') {
                    if (stripos($value, $search) !== false) {
                        $sql = 'select whs."WhsCode",
                        "U_CANCELED_NO",
                        "' . $value . '"."OIGE"."DocEntry",
                        "IMIP_ERESV"."OUSR_H"."U_UserCode",
                        "U_REQ_NO",
                        "Requester",
                        "Comments",
                        "DocNum",
                        "DocDate",
                        "' . $value . '"."@INV_TYPE"."Name" ,
                        \'' . $value . '\' as "Company",
                        Case WHEN "U_CANCELED_NO" IS NULL
                              THEN \'No\'
                              ELSE \'Yes\'
                              End As "Canceled"
                            from "' . $value . '"."OIGE"
                                left join "' . $value . '"."@INV_TYPE" on "' . $value . '"."OIGE"."U_INV_TYPE"="' . $value . '"."@INV_TYPE"."Code"
                                left join "IMIP_ERESV"."OUSR_H" on "' . $value . '"."OIGE"."U_ERESV_USER"="IMIP_ERESV"."OUSR_H"."U_UserID"
                                left join (select "DocEntry", "WhsCode" from ' . $value . '.IGE1 GROUP BY "DocEntry", "WhsCode") as whs
                                    on ' . $value . '.OIGE."DocEntry"=whs."DocEntry"
                            where "' . $value . '"."OIGE"."DocEntry" IS NOT NULL
                                ';
                    }
                } else {
                    if ($i < 1) {
                        $sql = 'select whs."WhsCode",
                            "U_CANCELED_NO",
                            "' . $value . '"."OIGE"."DocEntry",
                            "IMIP_ERESV"."OUSR_H"."U_UserCode",
                            "U_REQ_NO","Requester",
                            "Comments",
                            "DocNum",
                            "DocDate",
                            "' . $value . '"."@INV_TYPE"."Name" ,
                            \'' . $value . '\' as "Company",
                            Case WHEN "U_CANCELED_NO" IS NULL
                              THEN \'No\'
                              ELSE \'Yes\'
                              End As "Canceled"
                            from "' . $value . '"."OIGE"
                                left join "' . $value . '"."@INV_TYPE" on "' . $value . '"."OIGE"."U_INV_TYPE"="' . $value . '"."@INV_TYPE"."Code"
                                left join "IMIP_ERESV"."OUSR_H" on "' . $value . '"."OIGE"."U_ERESV_USER"="IMIP_ERESV"."OUSR_H"."U_UserID"
                                left join (select "DocEntry", "WhsCode" from ' . $value . '.IGE1 GROUP BY "DocEntry", "WhsCode") as whs
                                    on ' . $value . '.OIGE."DocEntry"=whs."DocEntry"
                            where "' . $value . '"."OIGE"."DocEntry" IS NOT NULL
                                ';
                    } else {
                        $sql .= ' UNION ALL
                        select whs."WhsCode",
                        "U_CANCELED_NO",
                        "' . $value . '"."OIGE"."DocEntry",
                        "IMIP_ERESV"."OUSR_H"."U_UserCode",
                        "U_REQ_NO",
                        "Requester",
                        "Comments",
                        "DocNum",
                        "DocDate",
                        "' . $value . '"."@INV_TYPE"."Name" ,
                        \'' . $value . '\' as "Company",
                        Case WHEN "U_CANCELED_NO" IS NULL
                              THEN \'No\'
                              ELSE \'Yes\'
                              End As "Canceled"
                            from "' . $value . '"."OIGE"
                                left join "' . $value . '"."@INV_TYPE" on "' . $value . '"."OIGE"."U_INV_TYPE"="' . $value . '"."@INV_TYPE"."Code"
                                left join "IMIP_ERESV"."OUSR_H" on "' . $value . '"."OIGE"."U_ERESV_USER"="IMIP_ERESV"."OUSR_H"."U_UserID"
                                left join (select "DocEntry", "WhsCode" from ' . $value . '.IGE1 GROUP BY "DocEntry", "WhsCode") as whs
                                    on ' . $value . '.OIGE."DocEntry"=whs."DocEntry"
                            where "' . $value . '"."OIGE"."DocEntry" IS NOT NULL
                                ';
                    }
                    if ($select_type == 'DocNum') {
                        $sql .= ' AND "' . $select_type . '" LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Canceled') {
                        if (stripos("No", $search) !== false) {
                            $sql .= ' AND "U_CANCELED_NO" IS NULL';
                        } elseif (stripos("Yes", $search) !== false) {
                            $sql .= ' AND "U_CANCELED_NO" IS NOT NULL';
                        }
                    } elseif ($select_type == 'DocDate') {
                        $sql .= ' AND "' . $select_type . '" BETWEEN \'' . $fromdocdateFilterValue . '\' AND \'' . $todocdateFilterValue . '\'';
                    } elseif ($select_type == 'Base Ref') {
                        $sql .= ' AND "U_REQ_NO" LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Inventory Type') {
                        $sql .= ' AND LOWER("' . $value . '"."@INV_TYPE"."Name") LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'CreatedBy') {
                        $sql .= ' AND LOWER("IMIP_ERESV"."OUSR_H"."U_UserCode") LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Comments') {
                        $sql .= ' AND "' . $select_type . '" LIKE( \'%' . $search . '%\' )';
                    } elseif ($select_type == 'Warehouse') {
                        $sql .= ' AND LOWER(whs."WhsCode") LIKE( \'%' . $search . '%\' )';
                    } else {
                        $fromdocdateFilterValue_default = date(
                            'Y-m-d',
                            mktime(0, 0, 0, date("m"), date("d") - 14, date("Y"))
                        );
                        $todocdateFilterValue_default = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d"), date("Y")));
                        $sql .= ' AND "DocDate" BETWEEN \'' . $fromdocdateFilterValue_default . '\' AND \''
                            . $todocdateFilterValue_default . '\'';
                    }
                    $i++;
                }
            }
            $arr = [];
            if (isset($sql)) {
                $sql .= ' ORDER BY "DocDate" desc
                ';
                if ($row_data == -1) {
                    $row_data = $total_data;
                } else {
                    $sql .= ' LIMIT ' . $row_data . '
                        OFFSET ' . $offset . '
                        ';
                }
                $rs = odbc_exec($connect, $sql);
                if (!$rs) {
                    exit("Error in SQL");
                }
                $l = 1;
                while (odbc_fetch_row($rs)) {
                    $arr[] = [
                        "No" => $l,
                        "DocEntry" => odbc_result($rs, "DocEntry"),
                        "DocNum" => odbc_result($rs, "DocNum"),
                        "DocDate" => substr(odbc_result($rs, "DocDate"), 0, 10),
                        "companyItem" => odbc_result($rs, "Company"),
                        "Comments" => odbc_result($rs, "Comments"),
                        "U_INV_TYPE_name" => odbc_result($rs, "Name"),
                        "Requester" => odbc_result($rs, "Requester"),
                        "U_REQ_NO" => odbc_result($rs, "U_REQ_NO"),
                        "status" => odbc_result($rs, "Canceled"),
                        "createdBy" => odbc_result($rs, "U_UserCode"),
                        "WhsCode" => odbc_result($rs, "WhsCode"),
                    ];
                    $l++;
                }
            }
            $result["total"] = $total_data;
            $result = array_merge($result, [
                "rows" => $arr,
                "filter" => $filter,
                "select_type" => $select_type,
                "search" => $search,
                "row_data" => $row_data,
            ]);
            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param $id
     *
     * @return array|mixed
     */
    public function getInventoryTypeByID($id)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/UDO_INV_TYPE('" . $id . "')";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers
        ));
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $array_data = [];
        if (!empty($response_text->Code)) {
            $array_data = $response_text;
        }
        curl_close($curl);
        return $array_data;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLine(Request $request)
    {
        // echo "companyItem".$request->companyItem;
        try {
            $result = [];
            $array_data = [];
            $detail_data = [];
            $data_header = [];
            $header = [];
            $U_INV_TYPE_name = "";
            $db_name = $request->companyItem;
            if (!empty($db_name)) {
                if (session('CompanyDB') != $db_name) {
                    $this->logout();
                    $this->login($db_name);
                }
                $getDocumentDetail = $this->getDocumentDetail($request->DocEntry, $db_name);
                if (isset($getDocumentDetail->DocEntry)) {
                    $inventory_type = $this->getInventoryTypeByID($getDocumentDetail->U_INV_TYPE);
                    $status = "Yes";
                    if ($getDocumentDetail->U_CANCELED_NO == null) {
                        $status = "No";
                    }
                    if (isset($inventory_type->Name)) {
                        $U_INV_TYPE_name = $inventory_type->Name;
                    }
                    $getCostcenter = $this->getCostcenter();
                    $data_header = [
                        'DocEntry' => $getDocumentDetail->DocEntry,
                        'DocDate' => $getDocumentDetail->DocDate,
                        'DocNum' => $getDocumentDetail->DocNum,
                        'Comments' => $getDocumentDetail->Comments,
                        'U_INV_TYPE_name' => $U_INV_TYPE_name,
                        'U_INV_TYPE' => $getDocumentDetail->U_INV_TYPE,
                        'status' => $status,
                        'U_ERESV_USER' => $getDocumentDetail->U_ERESV_USER
                    ];
                    $factor_name1 = "";
                    $factor_name2 = "";
                    foreach ($getDocumentDetail->DocumentLines as $key => $value) {
                        if (!empty($getCostcenter['array_data_lvl1'])) {
                            foreach ($getCostcenter['array_data_lvl1'] as $key_factor1 => $value_factor1) {
                                if ($value_factor1['FactorCode'] == $value->CostingCode) {
                                    $factor_name1 = $value_factor1['FactorText'];
                                }
                            }
                        }
                        if (!empty($getCostcenter['array_data_lvl2'])) {
                            foreach ($getCostcenter['array_data_lvl2'] as $key_factor2 => $value_factor2) {
                                if ($value_factor2['FactorCode'] == $value->CostingCode2) {
                                    $factor_name2 = $value_factor2['FactorText'];
                                }
                            }
                        }
                        $detail_data = [
                            'U_DGN_IReqId' => $value->U_DGN_IReqId,
                            'U_DGN_IReqLineId' => $value->U_DGN_IReqLineId,
                            'LineNum' => $value->LineNum,
                            'ItemCode' => $value->ItemCode,
                            'ItemDescription' => $value->ItemDescription,
                            'Quantity' => $value->Quantity,
                            'Price' => $value->Price,
                            'WarehouseCode' => $value->WarehouseCode,
                            'CostingCode' => $value->CostingCode,
                            'CostingCode2' => $value->CostingCode2,
                            'factor_name1' => $factor_name1,
                            'factor_name2' => $factor_name2
                        ];
                        array_push($array_data, $detail_data);
                    }
                }
            }
            $result = array_merge($result, [
                "detail" => $array_data,
                "header" => $data_header,
            ]);
            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
                //"trace" => $exception->getTrace(),
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $id
     * @param string $db_name
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDetail(Request $request, int $id, string $db_name): \Illuminate\Http\JsonResponse
    {
        try {
            $result = [];
            $array_data = [];
            $detail_data = [];
            $data_header = [];
            $header = [];
            $U_INV_TYPE_name = "";
            if (!empty($id) && !empty($db_name)) {
                if (session('CompanyDB') != $db_name) {
                    $this->logout();
                    $this->login($db_name);
                }
                $getDocumentDetail = $this->getDocumentDetail($id, $db_name);
                if (isset($getDocumentDetail->DocEntry)) {
                    $inventory_type = $this->getInventoryTypeByID($getDocumentDetail->U_INV_TYPE);
                    $status = "Yes";
                    if ($getDocumentDetail->U_CANCELED_NO == null) {
                        $status = "No";
                    }
                    if (isset($inventory_type->Name)) {
                        $U_INV_TYPE_name = $inventory_type->Name;
                    }
                    $getCostcenter = $this->getCostcenter();
                    $data_header = [
                        'DocEntry' => $getDocumentDetail->DocEntry,
                        'DocDate' => $getDocumentDetail->DocDate,
                        'DocNum' => $getDocumentDetail->DocNum,
                        'Comments' => $getDocumentDetail->Comments,
                        'U_INV_TYPE_name' => $U_INV_TYPE_name,
                        'U_INV_TYPE' => $getDocumentDetail->U_INV_TYPE,
                        'status' => $status,
                        'U_ERESV_USER' => $getDocumentDetail->U_ERESV_USER
                    ];
                    foreach ($getDocumentDetail->DocumentLines as $key => $value) {
                        $factor_name1 = "";
                        $factor_name2 = "";
                        foreach ($getCostcenter['array_data_lvl1'] as $key_factor1 => $value_factor1) {
                            if ($value_factor1['FactorCode'] == $value->CostingCode) {
                                $factor_name1 = $value_factor1['FactorText'];
                            }
                        }
                        foreach ($getCostcenter['array_data_lvl2'] as $key_factor2 => $value_factor2) {
                            if ($value_factor2['FactorCode'] == $value->CostingCode2) {
                                $factor_name2 = $value_factor2['FactorText'];
                            }
                        }
                        $detail_data = [
                            'U_DGN_IReqId' => $value->U_DGN_IReqId,
                            'U_DGN_IReqLineId' => $value->U_DGN_IReqLineId,
                            'LineNum' => $value->LineNum,
                            'ItemCode' => $value->ItemCode,
                            'ItemDescription' => $value->ItemDescription,
                            'Quantity' => $value->Quantity,
                            'Price' => $value->Price,
                            'WarehouseCode' => $value->WarehouseCode,
                            'CostingCode' => $value->CostingCode,
                            'CostingCode2' => $value->CostingCode2,
                            'factor_name1' => $factor_name1,
                            'factor_name2' => $factor_name2
                        ];
                        array_push($array_data, $detail_data);
                    }
                }
            }
            $result = array_merge($result, [
                "detail" => $array_data,
                "header" => $data_header,
            ]);
            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
                //"trace" => $exception->getTrace(),
            ]);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        try {
            $result = [];
            $array_data = [];
            $detail_data = [];
            $data_header = [];
            $header = [];
            $U_INV_TYPE_name = "";
            $db_name = $request->companyItem;
            $id = $request->DocEntry;
            if (!empty($db_name)) {
                if (session('CompanyDB') != $db_name) {
                    $this->logout();
                    $this->login($db_name);
                }
                $getDocumentDetail = $this->getDocumentDetail($id, $db_name);
                if (isset($getDocumentDetail->DocEntry)) {
                    $inventory_type = $this->getInventoryTypeByID($getDocumentDetail->U_INV_TYPE);
                    $status = "Yes";
                    if ($getDocumentDetail->U_CANCELED_NO == null) {
                        $status = "No";
                    }
                    if (isset($inventory_type->Name)) {
                        $U_INV_TYPE_name = $inventory_type->Name;
                    }
                    $getCostcenter = $this->getCostcenter();
                    $U_ERESV_USER_ = "";
                    if (!empty($getDocumentDetail->U_ERESV_USER)) {
                        $user = User::where("U_UserID", "=", $getDocumentDetail->U_ERESV_USER)->first();
                        $U_ERESV_USER_ = $user->U_UserCode;
                    }
                    $requester = "";
                    if (!empty($getDocumentDetail->U_REQ_NO)) {
                        $connect = $this->connectHana();
                        $U_DocNum = $getDocumentDetail->U_REQ_NO;
                        $sql = 'select * from ' . $db_name . '."@DGN_EI_OIGR" where "U_DocNum"=' . $U_DocNum . '';
                        $rs = odbc_exec($connect, $sql);
                        $data_detail = odbc_fetch_array($rs);
                        $requester = $data_detail['U_ReqBy'];
                    }
                    $data_header = [
                        'companyItem' => $db_name,
                        'DocEntry' => $getDocumentDetail->DocEntry,
                        'DocDate' => $getDocumentDetail->DocDate,
                        'DocNum' => $getDocumentDetail->DocNum,
                        'Comments' => $getDocumentDetail->Comments,
                        'U_INV_TYPE_name' => $U_INV_TYPE_name,
                        'U_INV_TYPE' => $getDocumentDetail->U_INV_TYPE,
                        'status' => $status,
                        'U_ERESV_USER' => $U_ERESV_USER_,
                        'requester' => $requester
                    ];
                    foreach ($getDocumentDetail->DocumentLines as $key => $value) {
                        $factor_name1 = "";
                        $factor_name2 = "";
                        if (!empty($getCostcenter['array_data_lvl1'])) {
                            foreach ($getCostcenter['array_data_lvl1'] as $key_factor1 => $value_factor1) {
                                if ($value_factor1['FactorCode'] == $value->CostingCode) {
                                    $factor_name1 = $value_factor1['FactorText'];
                                }
                            }
                        }
                        if (!empty($getCostcenter['array_data_lvl2'])) {
                            foreach ($getCostcenter['array_data_lvl2'] as $key_factor2 => $value_factor2) {
                                if ($value_factor2['FactorCode'] == $value->CostingCode2) {
                                    $factor_name2 = $value_factor2['FactorText'];
                                }
                            }
                        }
                        $visual_order = $value->VisualOrder + 1;
                        $detail_data = [
                            'U_DGN_IReqId' => $value->U_DGN_IReqId,
                            'U_DGN_IReqLineId' => $value->U_DGN_IReqLineId,
                            'LineNum' => $value->LineNum,
                            'VisualOrder' => $visual_order,
                            'ItemCode' => $value->ItemCode,
                            'ItemDescription' => $value->ItemDescription,
                            'Quantity' => $value->Quantity,
                            'Price' => $value->Price,
                            'WarehouseCode' => $value->WarehouseCode,
                            'CostingCode' => $value->CostingCode,
                            'CostingCode2' => $value->CostingCode2,
                            'factor_name1' => $factor_name1,
                            'factor_name2' => $factor_name2
                        ];
                        array_push($array_data, $detail_data);
                    }
                }
            }
            $result = array_merge($result, [
                "detail" => $array_data,
                "header" => $data_header,
            ]);
            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
                //"trace" => $exception->getTrace(),
            ]);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function printDocument(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $editedItem = $request->editedItem;
            $db_name = $editedItem['companyItem'];
            $docsdatadetail = $request->docsdatadetail;
            $DocEntry = $editedItem['DocEntry'];
            $DocNum = $editedItem['DocNum'];
            $DocDate = $editedItem['DocDate'];
            $Comments = $editedItem['Comments'];
            $U_INV_TYPE = $editedItem['U_INV_TYPE'];
            $Comments = $editedItem['Comments'];
            $CreatedBy = $editedItem['U_ERESV_USER'];
            $Comments = $editedItem['Comments'];
            $U_ERESV_USER = $request->user()->U_UserID;
            $data_letter = [];
            $connect = $this->connectHana();
            $query = '
                    SELECT T2."U_DocNum" AS "GIR_NO",T2."U_ERESV_DOCNUM" AS "RESV_NO"
                    FROM ' . $db_name . '."OIGE" T0
                    LEFT JOIN ' . $db_name . '."IGE1" T1 ON T0."DocEntry" = T1."DocEntry"
                    LEFT JOIN ' . $db_name . '."@DGN_EI_OIGR" T2 ON T1."U_DGN_IReqId" = T2."DocEntry"
                    WHERE T0."DocNum" = \'' . $DocNum . '\'';
            $rs = odbc_exec($connect, $query);
            $arr_query = odbc_fetch_array($rs);
            $GIR_NO = $arr_query['GIR_NO'];
            $RESV_NO = $arr_query['RESV_NO'];
            $no = 0;
            foreach ($docsdatadetail as $key => $value) {
                $sql = 'select "InvntryUom" from ' . $db_name . '."OITM" where "ItemCode"=\'' . $value['ItemCode'] . '\'';
                $rs = odbc_exec($connect, $sql);
                $data_detail = odbc_fetch_array($rs);
                $InvntryUom = $data_detail['InvntryUom'];
                $data_letter [] = [
                    "no" => $value['VisualOrder'],
                    "ItemCode" => $value['ItemCode'],
                    "Description" => $value['ItemDescription'],
                    "Uom" => $InvntryUom,
                    "Qty" => $value['Quantity'],
                    "Whs" => $value['WarehouseCode'],
                    "factor_name1" => $value['factor_name1'],
                    "RESV_NO" => $RESV_NO,
                    "GIR_NO" => $GIR_NO,
                ];
                $no++;
            }
            $letter_template = new TemplateProcessor(
                public_path(
                    'template/GI.docx'
                )
            );
            // echo "<pre>";print_r($data_letter);echo "</pre>";die();
            $letter_template->setValue('DocNum', $DocNum);
            $letter_template->setValue('CreatedBy', $CreatedBy);
            $letter_template->setValue('DocDate', $DocDate);
            $letter_template->setValue('Comments', $Comments);
            $letter_template->setValue('DATETIME', 'Print Date: ' . date('Y-m-d H:i:s'));
            $letter_template->cloneRowAndSetValues('no', $data_letter);
            $file_path_name = public_path(
                '/Attachment/GI/'
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

            $word_file->Quit(false);

            unset($word_file);
            $all_files = [
                $file_name,
                $pdf_file
            ];

            RemoveAttachment::dispatch($all_files)->delay(now()->addMinutes(3));

            return response()->json([
                'url' => url('/Attachment/GI/' . $request->user()->U_UserID . strtotime(date('Y-m-d')) . ".pdf")
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
            ]);
        }
    }
}
