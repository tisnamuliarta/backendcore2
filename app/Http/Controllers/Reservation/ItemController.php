<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Master\MasterUserDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateApiLogin()
    {
        if (session('B1SESSION') && session('ROUTEID')) {
            return true;
        } else {
            return false;
        }
    }

    public function logout()
    {
        $array=[];
        $params = [];
        $result=[];
        $errors=true;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/Logout");//API LOGIN
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);

        $response = curl_exec($curl);
    }

    public function login($db_name):\Illuminate\Http\JsonResponse
    {
        $array=[];
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
        $data_string="";
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

    public function getDataindex(Request $request)
    {
        $result=[];
        $master=new MasterUserDataController();
        $data_=$master->getWarehouse($request)->original['result'];
        $options = json_decode($request->options);
        $itemcodeFilterValue = $request->itemcodeFilterValue;
        $itemnameFilterValue = $request->itemnameFilterValue;
        $itemgroupFilterValue = $request->itemgroupFilterValue;
        $params=['itemcodeFilterValue'=>$itemcodeFilterValue,'itemnameFilterValue'=>$itemnameFilterValue,'itemgroupFilterValue'=>$itemgroupFilterValue];
        $pages = isset($options->page) ? (int)$options->page : 1;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 10;
        $offset = ($pages - 1) * $row_data;
        $username = $request->user()->U_UserCode;
        $totalItem=$this->getTotalitem();
        $all_data=$array_data_all_items=$this->getAllitems($offset, $row_data, $params, $data_);
        $array_data_all_group=$this->getAllitemsgroup();
        $result["total"] = $totalItem;
        $result = array_merge($result, [
            "all_items" => $all_data,
            "filter" => ['All'],
            "items_data" => $array_data_all_group,
            "items_data_modal"=>$array_data_all_group,
            "items_warehouse"=>$data_,
            "skip"=>$offset,
            "top"=>$row_data
        ]);
        return $result;
    }

    public function index(Request $request):\Illuminate\Http\JsonResponse
    {
        try {
            $result=[];
            $db_name=$request->companyItem;
            if (!empty($request->companyItem)) {
                if (session('CompanyDB') != $request->companyItem) {
                    $this->logout();
                    $this->login($db_name);
                }
                $all_doc=$this->getDataindex($request);
                $result = array_merge($result, [
                "all_items" => $all_doc['all_items'],
                "filter" => $all_doc['filter'],
                "items_data" => $all_doc['items_data'],
                "items_data_modal"=>$all_doc['items_data_modal'],
                "items_warehouse"=>$all_doc['items_warehouse'],
                "skip"=>$all_doc['skip'],
                "top"=>$all_doc['top'],
                "total"=>$all_doc['total'],
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

    public function getTotalitem()
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url='https://192.168.88.8:50000/b1s/v1/Items?$apply=aggregate(ItemCode%20with%20count%20as%20CountItem)';
        $array_data=[];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 90,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER =>$headers
        ));
        $response = curl_exec($curl);
        $response_text=json_decode($response);
        $array_data=[];
        $total=0;
        if (!empty($response_text->value[0]->CountItem)) {
            $total=$response_text->value[0]->CountItem;
        }
        curl_close($curl);
        return $total;
    }

    public function getAllitemsgroup()
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $url="https://192.168.88.8:50000/b1s/v1/ItemGroups";
        $array_data=[];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 90,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER =>$headers
        ));
        $response = curl_exec($curl);
        $response_text=json_decode($response);
        $array_data=[];
        if (!empty($response)) {
            $array_=$response_text->value;
            foreach ($array_ as $key => $value) {
                $data_=array('GroupName'=>$value->GroupName,'GroupID'=>$value->Number);
                array_push($array_data, $data_);
            }
        }
        curl_close($curl);
        return $array_data;
    }

    public function getItemsGroupByID($id)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";

        $url="https://192.168.88.8:50000/b1s/v1/ItemGroups(".$id.")";
        $array_data=[];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 90,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER =>$headers
        ));
        $response = curl_exec($curl);
        $response_text=json_decode($response);
        curl_close($curl);
        $array_data=[];
        if (!empty($response)) {
            $data_=array('GroupName'=>$response_text->GroupName);
            array_push($array_data, $data_);
        }
        return $array_data;
    }


    public function getAllitems($offset, $row_data, $params, $data_warehouse)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = array("B1S-CaseInsensitive: true","Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";");
        $headers=array("B1S-CaseInsensitive: true","Cookie: B1SESSION=". $sessionId .'; ROUTEID='. $routeId .';');
        $itemcodeFilterValue='';
        $itemnameFilterValue='';
        $itemgroupFilterValue='';
        if (!empty($params['itemcodeFilterValue']) || !empty($params['itemnameFilterValue']) || !empty($params['itemgroupFilterValue'])) {
            if (!empty($params['itemcodeFilterValue'])) {
                $itemcodeFilterValue=$params['itemcodeFilterValue'];
            }
            if (!empty($params['itemnameFilterValue'])) {
                $itemnameFilterValue=$params['itemnameFilterValue'];
            }
            if (!empty($params['itemgroupFilterValue'])) {
                $itemgroupFilterValue=$params['itemgroupFilterValue'];
            }
            if (!empty($itemgroupFilterValue)) {
                $url="https://192.168.88.8:50000/b1s/v1/Items?\$select=*&\$top=" . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(ItemName,'". $itemnameFilterValue ."')%20and%20contains(ItemCode,'". $itemcodeFilterValue ."'))%20and%20ItemsGroupCode%20eq%20". ($itemgroupFilterValue) ."";
            } else {
                $url="https://192.168.88.8:50000/b1s/v1/Items?\$select=*&\$top=" . $row_data . "&\$skip=" . $offset . "&\$filter=(contains(ItemName,'". $itemnameFilterValue ."')%20and%20contains(ItemCode,'". $itemcodeFilterValue ."'))";
            }
        } else {
            $url='https://192.168.88.8:50000/b1s/v1/Items?$select=*&$top=' . $row_data . '&$skip=' . $offset . '';
        }


        $array_data=[];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 90,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER =>$headers
        ));
        $response = curl_exec($curl);
        $response_text=json_decode($response);
        if (!empty($response)) {
            $ItemWarehouseInfoCollection=[];
            if (!empty($response_text->value)) {
                $array_=$response_text->value;
                foreach ($array_ as $key => $value) {
                    $id=$value->ItemsGroupCode;
                    $data_group_by_id=$this->getItemsGroupByID($id);
                    $id_type="NRS";
                    $name_type='Not Ready Stock';
                    if ($value->U_ItemType=='RS') {
                        $id_type='RS';
                        $name_type='Ready Stock';
                    }
                    $wr_name="";
                    $ItemWarehouseInfoCollection=$value->ItemWarehouseInfoCollection;
                    $DefaultWarehouse=$value->DefaultWarehouse;
                    if (!empty($ItemWarehouseInfoCollection)) {
                        foreach ($ItemWarehouseInfoCollection as $key => $values) {
                            $ItemWarehouseInfoCollection[$key]->DefaultWarehouse="";
                            // $ItemWarehouseInfoCollection[$key]->WhseName="";
                            if ($values->Locked=='tYES') {
                                $ItemWarehouseInfoCollection[$key]->Locked=true;
                            } else {
                                $ItemWarehouseInfoCollection[$key]->Locked=false;
                            }
                            if ($DefaultWarehouse==$values->WarehouseCode) {
                                $ItemWarehouseInfoCollection[$key]->DefaultWarehouse=true;
                            } else {
                                $ItemWarehouseInfoCollection[$key]->DefaultWarehouse=false;
                            }
                            // $wr_name=$this->getWarehousedefault($values->WarehouseCode);
                            // $ItemWarehouseInfoCollection[$key]->WhseName=$wr_name;
                        }
                    } else {
                        $ItemWarehouseInfoCollection=[];
                        foreach ($data_warehouse as $keydata_ => $valuedata_) {
                            $keydata_ = new \stdClass();
                            $keydata_->Locked = false;
                            $keydata_->DefaultWarehouse=false;
                            $keydata_->WarehouseCode=$valuedata_;
                            $ItemWarehouseInfoCollection[]=$keydata_;
                        }
                    }
                    // $def_wh=$this->getWarehousedefault($value->DefaultWarehouse);
                    $data_=array('def_wh'=>$value->DefaultWarehouse,'ItemWarehouseInfoCollection'=>$ItemWarehouseInfoCollection,'ItemPurchaseuom'=>$value->PurchaseItemsPerUnit,'Salesuom'=>$value->SalesItemsPerUnit,'action'=>'action','QuantityOnStock'=>$value->QuantityOnStock,'ItemInventoryuom'=>$value->InventoryUOM,'U_ItemType'=>$id_type,'TypeName'=>$name_type,'ItemGroup'=>$value->ItemsGroupCode,'GroupName'=>$data_group_by_id[0]['GroupName'],'ItemCode'=>$value->ItemCode,'ItemName'=>$value->ItemName,'DefaultWarehouse'=>$value->DefaultWarehouse,'ItemSalesuom_name'=>$value->SalesUnit,'ItemPurchaseuom_name'=>$value->PurchaseUnit);
                    array_push($array_data, $data_);
                }
            }
        }
        curl_close($curl);

        return $array_data;
    }

    public function saveItem($params)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/Items");//SAVE ITEMS
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $result_all=[];
        $result=0;
        $result_data_api=[];
        if (!empty($response_text->ItemCode)) {
            $result=1;
            $message='Saving Data Item ' . $response_text->ItemName . '(' . $response_text->ItemCode . ') Successfull';
            $result_data_api=$response_text;
        } else {
            $message='Data Not Saved. ' . $response_text->error->message->value . '.';
            if (empty($message)) {
                $message='Data Not Saved';
            }
        }
        $result_all=['result'=>$result,'message'=>$message,'result_data_api'=>$result_data_api];
        return $result_all;
    }

    public function generateItemcode($params)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/SeriesService_GetDocumentSeries");//Load Service Series
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET", );
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $result_all=[];
        $result=0;
        if (!empty($response_text->value)) {
            $result=1;
            $message=$response_text;
        } else {
            $message='Empty Data';
        }
        $result_all=['result'=>$result,'message'=>$message];
        return $result_all;
    }

    public function insert(Request $request)
    {
        try {
            $result=[];
            if ($this->validation($request)) {
                return response()->json([
                    "errors" => true,
                    "validHeader" => true,
                    "message" => $this->validation($request)
                ]);
            }

            $db_name=$request->companyItem;
            $errors=true;
            if (!empty($request->companyItem)) {
                if (session('CompanyDB') != $request->companyItem) {
                    $this->logout();
                    $this->login($db_name);
                }

                $params_itemcode=['DocumentTypeParams'=>array('Document'=>'4')];
                $result_generate=$this->generateItemcode($params_itemcode);

                $validate_item_group=0;
                if (is_array($request->ItemGroup)) {
                    $validate_item_group=0;
                } else {
                    if ($request->ItemGroup !='') {
                        $validate_item_group=1;
                    } else {
                        $validate_item_group=0;
                    }
                }
                $result_export=0;
                $itemcodeapi='';
                $default_WH="";
                $message='Item Code Generate Error';
                $series='';
                $add_data=[];
                if ($validate_item_group==1) {
                    if ($result_generate['result']==1) {
                        foreach ($result_generate['message']->value as $key_generate => $value_generate) {
                            if ($value_generate->Remarks==$request->ItemGroup) {
                                $series=$value_generate->Series;
                            }
                        }
                        $default_WH=$request->DefaultWarehouse;
                        if ($series!='') {
                            $params=['SalesUnit'=>$request->ItemSalesuom_name,'PurchaseUnit'=>$request->ItemPurchaseuom_name,'DefaultWarehouse'=>$default_WH,'Series'=>$series,'ItemName'=>$request->ItemName,'U_ItemType'=>$request->U_ItemType,'ItemsGroupCode'=>$request->ItemGroup,'InventoryUOM'=>$request->ItemInventoryuom,'SalesItemsPerUnit'=>$request->Salesuom,'PurchaseItemsPerUnit'=>$request->ItemPurchaseuom];
                            $saveItem=$this->saveItem($params);
                            $result_export=$saveItem['result'];
                            $message=$saveItem['message'];
                            if ($result_export==1) {
                                $errors=false;
                                $result_data_api=$saveItem['result_data_api'];
                                $dataGroup=$this->getItemsGroupByID($request->ItemGroup);
                                $TypeName='Not Ready Stock';
                                if ($request->U_ItemType=='RS') {
                                    $TypeName='Ready Stock';
                                }
                                $itemcode=$saveItem['result_data_api']->ItemCode;
                                $GroupName=$dataGroup[0]['GroupName'];
                                $TypeName=$TypeName;
                                $add_data=['itemcode'=>$itemcode,'GroupName'=>$GroupName,'TypeName'=>$TypeName,'def_wh'=>$default_WH];
                                $master=new MasterUserController;
                                $data_warehouse=$master->getWarehouse($request)->original['result'];
                                $ItemWarehouseInfoCollection=[];
                                foreach ($data_warehouse as $keydata_ => $valuedata_) {
                                    $keydata_ = new \stdClass();
                                    $keydata_->Locked = false;
                                    $keydata_->DefaultWarehouse=false;
                                    $keydata_->WarehouseCode=$valuedata_;
                                    $ItemWarehouseInfoCollection[]=$keydata_;
                                }
                            }
                        } else {
                            $result_export=0;
                            $message='Series Not Found';
                        }
                    }
                } else {
                    $result_export=0;
                    $message='Item Group is Mandatory';
                }
                $result = array_merge($result, [
                    "errors"=>$errors,
                    "result" => $result_export,
                    "message" => $message,
                    'add_data'=>$add_data,
                    "ItemWarehouseInfoCollection"=>$ItemWarehouseInfoCollection,
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

    public function update(Request $request)
    {
        try {
            if ($this->validation($request)) {
                return response()->json([
                    "errors" => true,
                    "validHeader" => true,
                    "message" => $this->validation($request)
                ]);
            }
            $validate_item_group=0;
            $result=[];
            $db_name=$request->companyItem;
            $errors=true;
            if (!empty($request->companyItem)) {
                if (session('CompanyDB') != $request->companyItem) {
                    $this->logout();
                    $this->login($db_name);
                }
                $add_data=[];
                if (is_array($request->ItemGroup)) {
                    $validate_item_group=0;
                } else {
                    if ($request->ItemGroup !='') {
                        $validate_item_group=1;
                    } else {
                        $validate_item_group=0;
                    }
                }
                if ($validate_item_group==1) {
                    $default_WH=$request->DefaultWarehouse;
                    $params=['SalesUnit'=>$request->ItemSalesuom_name,'PurchaseUnit'=>$request->ItemPurchaseuom_name,'DefaultWarehouse'=>$default_WH,'ItemCode'=>$request->ItemCode,'ItemName'=>$request->ItemName,'U_ItemType'=>$request->U_ItemType,'ItemsGroupCode'=>$request->ItemGroup,'InventoryUOM'=>$request->ItemInventoryuom,'SalesItemsPerUnit'=>$request->Salesuom,'PurchaseItemsPerUnit'=>$request->ItemPurchaseuom];
                    $updateItem=$this->updateItem($params);
                    $result_export=$updateItem['result'];
                    $message=$updateItem['message'];
                    if ($result_export==1) {
                        $result_data_api=$updateItem['result_data_api'];
                        $dataGroup=$this->getItemsGroupByID($request->ItemGroup);
                        $TypeName='Not Ready Stock';
                        if ($request->U_ItemType=='RS') {
                            $TypeName='Ready Stock';
                        }
                        $wr_name=$default_WH;
                        $itemcode=$request->ItemCode;
                        $GroupName=$dataGroup[0]['GroupName'];
                        $TypeName=$TypeName;
                        $add_data=['itemcode'=>$itemcode,'GroupName'=>$GroupName,'TypeName'=>$TypeName,'def_wh'=>$wr_name];
                        $errors=false;
                        $result = array_merge($result, [
                                "errors"=>$errors,
                                "result" => $updateItem['result'],
                                "message" => $updateItem['message'],
                                "add_data"=>$add_data
                            ]);
                    }
                } else {
                    $result_export=0;
                    $message='Item Group is Mandatory';
                    $result = array_merge($result, [
                        "errors"=>$errors,
                        "result" => $result_export,
                        "message" => $message,
                        "add_data"=>$add_data
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

    protected function validation($request)
    {
        $rules=[];
        $messages = [
            'ItemName.required' => 'Item Name is required!',
            'ItemGroup.required' => 'Item Group is required!',
            'ItemPurchaseuom' => 'Purchase UOM is required!',
            'Salesuom' => 'Sales UOM is required!',
            'U_ItemType' => 'Item Type is required',
            'DefaultWarehouse' => 'DefaultWarehouse is required',
        ];
        $validator = Validator::make($request->all(), [
            'ItemName' => 'required',
            'ItemGroup' => 'required',
            'ItemPurchaseuom' => 'required',
            'Salesuom' => 'required',
            'U_ItemType' => 'required',
            'DefaultWarehouse' => 'required',
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

    public function updateItem($params)
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000" . "/b1s/v1/Items('" . $params['ItemCode'] . "')");//UPDATE ITEMS
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $response_text = json_decode($response);
        $result_all=[];
        $result=0;
        $result_data_api=[];
        if (!empty($response_text->error->code)) {//if Error
            $message='Data Not Saved. ' . $response_text->error->message->value . '.';
        } else {
            $message='Updating Data Item ' . $params['ItemName'] . '(' . $params['ItemCode'] . ') Successfull';
            $result=1;
            $result_data_api=$response_text;
        }
        $result_all=['result'=>$result,'message'=>$message,'result_data_api'=>$result_data_api];
        return $result_all;
    }
}
