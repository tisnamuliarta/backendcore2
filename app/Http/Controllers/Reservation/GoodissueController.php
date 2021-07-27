<?php

namespace App\Http\Controllers\Reservation;

use App\Traits\ConnectHana;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoodissueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
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

    public function login($db_name): \Illuminate\Http\JsonResponse
    {
        $array = [];
        $params = [
            "UserName" => "RESV",
            "Password" => "imip#1234",
            "CompanyDB" => $db_name
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
            'ROUTEID' => $routeId
        ]);
        return response()->json([
            'B1SESSION' => $response_text->SessionId,
            'ROUTEID' => $routeId
        ]);
    }

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

    public function getInventoryType()
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/UDO_INV_TYPE";
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
        if (!empty($response_text->value)) {
            $array_ = $response_text->value;
            foreach ($array_ as $key => $value) {
                $data_ = array('Code' => $value->Code, 'Name' => $value->Name);
                array_push($array_data, $data_);
            }
        }
        curl_close($curl);
        return $array_data;
    }

    public function getDocumentById($U_DocNum)
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
        if (!empty($response_text->DocNum)) {
            $data_ = array('Creator' => $response_text->Creator, 'DocNum' => $response_text->DocNum, 'DocEntry' => $response_text->DocEntry, 'Canceled' => $response_text->Canceled, 'Status' => $response_text->Status, 'CreateDate' => $response_text->CreateDate, 'UpdateDate' => $response_text->UpdateDate, 'U_DocDate' => $response_text->U_DocDate, 'U_DueDate' => $response_text->U_DueDate, 'U_Remarks' => $response_text->U_Remarks, 'U_ReqBy' => $response_text->U_ReqBy, 'U_DocNum' => $response_text->U_DocNum);
            array_push($array_data, $data_);
        }
        curl_close($curl);
        return $array_data;
    }
    // public function companyUser(Request $request): \Illuminate\Http\JsonResponse
    // {


    // }


    public function getAllDocument($offset, $row_data, $params, $db_name)
    {
        $arr = [];
        $connect = $this->connectHana();
        $sql = '
                select "U_ReqBy", oi."U_Remarks", "U_DocNum", "DocNum" from "' . $db_name . '"."@DGN_EI_OIGR" as oi
                left join "' . $db_name . '"."@DGN_EI_IGR1" ei on oi."DocEntry" = ei."DocEntry"
                left join "' . $db_name . '"."@ADDON_CONFIG" ac on ei."U_WhsCode"= ac."U_Value"
                where oi."Canceled"=\'N\' AND oi."Status"=\'O\' and ac."U_Description"=\'RESV_SUBWH_GI\'';
        if (!empty($params['docNumFilterValue'])) {
            $sql .= ' AND "U_DocNum" LIKE( \'%' . $params['docNumFilterValue'] . '%\' )';
        }
        $sql .= ' ORDER BY oi."CreateDate" desc
                ';
        $sql .= ' LIMIT ' . $row_data . '
                        OFFSET ' . $offset . '
                        ';
        $rs = odbc_exec($connect, $sql);
        if (!$rs) {
            exit("Error in SQL");
        }
        $l = 1;
        while (odbc_fetch_row($rs)) {
            $arr[] = [
                "No" => $l,
                "U_ReqBy" => odbc_result($rs, "U_ReqBy"),
                "U_Remarks" => odbc_result($rs, "U_Remarks"),
                "U_DocNum" => odbc_result($rs, "U_DocNum"),
                "DocNum" => odbc_result($rs, "DocNum"),
            ];
            $l++;
        }
        // $sessionId = session('B1SESSION');
        // $routeId = session('ROUTEID');
        // $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        // $docNumFilterValue='';
        // $url="https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR?\$top=" . $row_data . "&\$skip=" . $offset . "&\$filter=Status%20eq%20'O'%20and%20Canceled%20eq%20'N'&\$orderby=CreateDate%20desc";
        // if(!empty($params['docNumFilterValue'])){
        //     $docNumFilterValue=$params['docNumFilterValue'];
        //     $url="https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR?\$top=" . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(U_DocNum,'" .  $docNumFilterValue . "'))%20Status%20eq%20'O'%20and%20Canceled%20eq%20'N'&\$orderby=CreateDate%20desc";
        // }
        // $array_data=[];
        // $curl = curl_init();
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // curl_setopt_array($curl, array(
        //   CURLOPT_URL => $url,
        //   CURLOPT_RETURNTRANSFER => true,
        //   CURLOPT_TIMEOUT => 90,
        //   CURLOPT_CUSTOMREQUEST => "GET",
        //   CURLOPT_HTTPHEADER =>$headers
        // ));
        // $response = curl_exec($curl);
        // $response_text=json_decode($response);
        // $array_data=[];
        // $detail_header=[];
        // $header=[];
        // if(!empty($response_text->value)){
        //     $array_=$response_text->value;
        //     foreach ($array_ as $key => $value) {
        //         foreach ($value->DGN_EI_IGR1Collection as $key_detail => $value_detail) {

        //             $detail_header=array('U_ItemCode'=>$value_detail->U_ItemCode,'U_ItemDesc'=>$value_detail->U_ItemDesc,'U_ReqQty'=>$value_detail->U_ReqQty,'U_quantity'=>0,'reserved'=>'','description'=>'','U_Issued'=>$value_detail->U_Issued,'LineId'=>$value_detail->LineId);
        //             array_push($header, $detail_header);
        //         }
        //         $txt_U_DocNum=$value->U_DocNum.", ".$value->U_ReqBy.", ".$value->U_Remarks;
        //         $data_=array('check_docnum'=>false,'Creator'=>$value->Creator,'DocNum'=>$value->DocNum,'DocEntry'=>$value->DocEntry,'Canceled'=>$value->Canceled,'Status'=>$value->Status,'CreateDate'=>$value->CreateDate,'UpdateDate'=>$value->UpdateDate,'U_DocDate'=>$value->U_DocDate,'U_DueDate'=>$value->U_DueDate,'U_Remarks'=>$value->U_Remarks,'U_ReqBy'=>$value->U_ReqBy,'U_DocNum'=>$value->U_DocNum,'detail_header'=>$header,'txt_U_DocNum'=>$txt_U_DocNum);
        //         array_push($array_data, $data_);
        //     }
        // }
        // curl_close($curl);
        return $arr;
    }

    public function getAllDocumentDetails($doc_entry)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR(" . $doc_entry . ")";
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
        $selisih = 0;
        if (!empty($response_text->DocNum)) {
            $array_data = $response_text;
        }
        curl_close($curl);
        return $array_data;
    }

    public function documentDetails(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $result = [];
            $db_name = $request->companyItem;
            if (!empty($request->companyItem)) {
                if (session('CompanyDB') != $request->companyItem) {
                    $this->logout();
                    $this->login($db_name);
                    $doc_entry = $request->doc_entry;
                    $array_data = [];
                    $response_text = $this->getAllDocumentDetails($doc_entry);
                    foreach ($response_text->DGN_EI_IGR1Collection as $key_detail => $value_detail) {
                        $selisih = $value_detail->U_ReqQty - $value_detail->U_Issued;
                        if ($selisih >= 1) {
                            $item_data = $this->getItemsByID($value_detail->U_ItemCode, $value_detail->U_WhsCode);
                            $detail_header = array('LineId' => $value_detail->LineId, 'U_Issued' => $value_detail->U_Issued, 'U_WhsCode' => $value_detail->U_WhsCode, 'check_data' => false, 'DocEntry' => $response_text->DocEntry, 'U_ItemCode' => $value_detail->U_ItemCode, 'U_ItemDesc' => $value_detail->U_ItemDesc, 'U_ReqQty' => $value_detail->U_ReqQty, 'U_quantity' => 0, 'InStock' => $item_data['InStock'], 'General_stock' => $item_data['General_stock']);
                            array_push($array_data, $detail_header);
                        }
                    }
                    $result = array_merge($result, [
                        "rows" => $array_data
                    ]);
                }
            }

            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
            ]);
        }
    }

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
        $detail_header = [];
        $header = [];
        $level1 = [];
        $level2 = [];
        if (!empty($response_text->value)) {
            foreach ($response_text->value as $key_detail => $value_detail) {
                if ($value_detail->InWhichDimension == 1 && $value_detail->Active == 'tYES') {
                    $level1 = ['FactorCode' => $value_detail->FactorCode, 'FactorText' => $value_detail->FactorDescription];
                    array_push($array_data_lvl1, $level1);
                } elseif ($value_detail->InWhichDimension == 2 && $value_detail->Active == 'tYES') {
                    $level2 = ['FactorCode' => $value_detail->FactorCode, 'FactorText' => $value_detail->FactorDescription];
                    array_push($array_data_lvl2, $level2);
                }
            }
        }
        $array_data = ['array_data_lvl1' => $array_data_lvl1, 'array_data_lvl2' => $array_data_lvl2];
        curl_close($curl);
        return $array_data;
    }

    protected function validation($request)
    {
        $messages = [
            'ItemWarehouse' => 'Item Warehouse is required!',
            'U_DocDate' => 'Document Date is required!',
            'CostItem1' => 'Cost Item1 is required',
            'CostItem2' => 'Cost Item2 is required',
            'U_Remarks' => 'Remarks is required',
            'inventorytype' => 'Inventory Type is required',
            'U_DocNum' => 'Document Number is required',
            'U_ReqBy' => 'Request Name is required',
        ];

        $validator = Validator::make($request->all(), [
            'ItemWarehouse' => 'required',
            'U_DocDate' => 'required',
            'CostItem1' => 'required',
            'CostItem2' => 'required',
            'U_Remarks' => 'required',
            'inventorytype' => 'required',
            'U_DocNum' => 'required',
            'U_ReqBy' => 'required',
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

    public function chooseBasedoc(Request $request): \Illuminate\Http\JsonResponse
    {
        $selectedDocnum = $request->id_doc;
        $docs_data = $request->docs_data;
        $result = [];
        $choose = [];
        foreach ($docs_data as $key => $value) {
            if ($selectedDocnum == $value['DocNum']) {
                $choose[] = $value;
                $result = array_merge($result, [
                    "choose" => $choose,
                ]);
            }
        }
        return response()->json($result);
    }

    public function addGoodissues(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $result = [];
            $newDocEntry = "";
            $error = true;
            $message = "";
            $companyItem = $request->companyItem;
            if ($this->validation($request)) {
                return response()->json([
                    "errors" => true,
                    "validHeader" => true,
                    "message" => $this->validation($request)
                ]);
            }
            if (!empty($request->companyItem)) {
                if (session('CompanyDB') != $request->companyItem) {
                    $this->logout();
                    $this->login($companyItem);
                }
                $U_ERESV_USER = $request->user()->U_UserID;
                $detailData = $request->detailData;

                $inventorytype = $request->inventorytype;
                $ItemWarehouse = $request->ItemWarehouse;
                $U_DocNum = $request->U_DocNum;
                $U_Remarks = $request->U_Remarks;
                $U_DocDate = $request->U_DocDate;
                $CostItem1 = $request->CostItem1;
                $CostItem2 = $request->CostItem2;
                $DocEntry = $request->DocEntry;
                $params = [];
                $params_documentlines = [];
                $params_Document_ApprovalRequests = [];
                $gi = [];
                $n_cek = 0;
                $line = [];
                $DGN_EI_IGR1data = [];
                $DGN_EI_IGR1Collection = [];
                if (!empty($detailData) && !empty($inventorytype) && !empty($ItemWarehouse) && !empty($U_DocDate)) {
                    foreach ($detailData as $key => $value) {
                        if ($value['check_data'] == 1) {
                            $n_cek = $n_cek + 1;
                        }
                    }
                    if ($n_cek >= 1) {
                        foreach ($detailData as $key => $value) {
                            $sisa = $value['U_ReqQty'] - $value['U_Issued'];
                            if ($value['U_quantity'] > $value['U_ReqQty']) {
                                $message .= "Good Issue " . $value['U_ItemCode'] . " Failed. Can not more than Qty Request.";
                            } else {
                                // if($value['U_quantity']>$value['General_stock']){
                                //     $message.="Good Issue ".$value['U_ItemCode']." Failed. Can not more than Qty Of Warehouse.";
                                // }elseif($value['U_quantity']>$sisa){
                                //     $message.="Good Issue ".$value['U_ItemCode']." Failed. Can not more than Rest of Reserved ReqQty.";
                                // }else{
                                if ($value['check_data'] == 1) {
                                    $params_documentlines = [
                                        'ItemCode' => $value['U_ItemCode'],
                                        'Quantity' => $value['U_quantity'],
                                        'UnitPrice' => 10,
                                        'WarehouseCode' => $ItemWarehouse,
                                        'CostingCode' => $CostItem1,
                                        'CostingCode2' => $CostItem2,
                                        'U_DGN_IReqId' => $DocEntry,
                                        'U_DGN_IReqLineId' => $value['LineId']
                                    ];
                                    array_push($line, $params_documentlines);
                                    $hasil = $value['U_Issued'] + $value['U_quantity'];
                                    $DGN_EI_IGR1Data = ['LineId' => $value['LineId'], 'U_Issued' => $hasil];
                                    array_push($DGN_EI_IGR1Collection, $DGN_EI_IGR1Data);
                                }
                                // }
                            }
                        }
                        $params = [
                            'U_REQ_NO' => $U_DocNum,
                            'Comments' => $U_Remarks,
                            'DocDate' => $U_DocDate,
                            'U_INV_TYPE' => $inventorytype,
                            'DocumentLines' => $line,
                            'U_ERESV_USER' => $U_ERESV_USER,
                        ];
                        $gi = $this->pushGoodissues($params);
                        if (!empty($gi->original['response'])) {
                            if (isset($gi->original['response']->EDocStatus)) {
                                if ($gi->original['response']->EDocStatus == "edoc_Ok") {
                                    $DocEntry = $value['DocEntry'];
                                    //update U_Issued
                                    $parameter = ['DGN_EI_IGR1Collection' => $DGN_EI_IGR1Collection];
                                    $updateU_Issue = $this->updateU_Issue($parameter, $DocEntry);
                                    $message .= "Good Issue GIR " . $U_DocNum . " Success. DocNum registered as " . $gi->original['response']->DocNum . ". ";
                                    if ($updateU_Issue['result'] != 1) {
                                        $message .= "Update U_Issued Failed.";
                                    }
                                    $newDocEntry = $gi->original['response']->DocEntry;
                                    $error = false;
                                }
                            } else {
                                $message .= "Error : " . $gi->original['response']->error->message->value;
                            }
                        }
                    } else {
                        $message .= "Good Issue Failed Please Select your Request.";
                    }
                    if (!$error) {
                        $all_DGN_EI_IGR_det = $this->getAllDocumentDetails($DocEntry);
                        $n_close = 0;
                        foreach ($all_DGN_EI_IGR_det->DGN_EI_IGR1Collection as $key_detail => $value_detail) {
                            if ($value_detail->U_Issued != $value_detail->U_ReqQty) {
                                $n_close = $n_close + 1;
                            }
                        }
                        if ($n_close == 0) {
                            //update status GIR -> 'C'
                            // $param_close=['Status'=>'C'];
                            $close_GIR = $this->updateStatusGIR($DocEntry);
                            if ($close_GIR['result'] != 1) {
                                $message .= "Update Status to Close GIR " . $U_DocNum . " Failed.";
                            }
                        }
                    }
                }
            }
            return response()->json([
                "errors" => $error,
                "message" => $message,
                "newdocentry" => $newDocEntry,
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
            ]);
        }
    }

    public function updateStatusGIR($U_DocNum)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $parameter = [];
        $curl = curl_init();
        $url = "https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR(" . $U_DocNum . ")/Close";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
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

    public function pushGoodissues($params): \Illuminate\Http\JsonResponse
    {
        $array = [];
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/InventoryGenExits");//GI
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $response_text = json_decode($response);
        curl_close($curl);
        return response()->json([
            'response' => $response_text
        ]);
    }

    public function getItemsByID($id, $U_WhsCode)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url = "https://192.168.88.8:50000/b1s/v1/Items('" . $id . "')";
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
        if (!empty($response_text->ItemCode)) {
            foreach ($response_text->ItemWarehouseInfoCollection as $key => $value) {
                if ($value->WarehouseCode == $U_WhsCode) {
                    $array_data = ['InventoryUOM' => $response_text->InventoryUOM, 'InStock' => $value->InStock, 'General_stock' => $response_text->QuantityOnStock];
                }
            }
        }
        curl_close($curl);
        return $array_data;
    }

    public function index(Request $request)
    {
        try {
            $result = [];
            $docNumFilterValue = json_decode($request->docNumFilterValue);
            $params = [];
            $db_name = $request->companyItem;
            if (!empty($request->companyItem)) {
                if (session('CompanyDB') != $request->companyItem) {
                    $this->logout();
                    $this->login($db_name);
                }
                $params = ['docNumFilterValue' => $docNumFilterValue];
                $options = json_decode($request->options);
                $options_modal = json_decode($request->options_modal);
                $pages = isset($options->page) ? (int)$options->page : 1;
                $row_data = isset($options_modal->itemsPerPage) ? (int)$options_modal->itemsPerPage : 10;
                $offset = ($pages - 1) * $row_data;
                $all_doc = $this->getAllDocument($offset, $row_data, $params, $db_name);
                $coasting = $this->getCostcenter();
                $Cosecenter1 = [];
                $Cosecenter2 = [];
                $Cosecenter1 = $coasting['array_data_lvl1'];
                $Cosecenter2 = $coasting['array_data_lvl2'];
                $inventory_type = $this->getInventoryType();
                $result["total"] = 1000;
                $result = array_merge($result, [
                    "rows" => $all_doc,
                    "inventorytype" => $inventory_type,
                    "Cosecenter1" => $Cosecenter1,
                    "Cosecenter2" => $Cosecenter2,
                ]);
            }
            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json([
                "error" => true,
                "message" => $exception->getMessage(),
            ]);
        }
    }


    public function show($id)
    {
        //
    }
}
