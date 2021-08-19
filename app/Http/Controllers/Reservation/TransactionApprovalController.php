<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\Resv\ReservationHeader;
use App\Traits\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TransactionApprovalController extends Controller
{
    use Approval;

    public function __construct()
    {
        $this->middleware(['direct_permission:Reservation Approval-index'])->only('index');
        $this->middleware(['direct_permission:Reservation Approval-store'])->only('store');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $cherry_token = $request->user()->cherry_token;
        $employee_code = $request->user()->employee_code;
        $status_approval = $request->status_approval;

        $documents = Http::post(env('CHERRY_REQ'), [
                'CommandName' => 'GetList',
                'ModelCode' => 'ApprovalRequests',
                'UserName' => $request->user()->username,
                'Token' => $cherry_token,
                'OrderBy' => 'InsertStamp',
                'OrderDirection ' => 'desc',
                'ParameterData' => [
                    [
                        'ParamKey' => 'ApproverCode',
                        'ParamValue' => $employee_code,
                        'Operator' => 'eq'
                    ],
                    [
                        'ParamKey' => 'StatusId',
                        'ParamValue' => $status_approval,
                        'Operator' => 'eq'
                    ]
                ]
            ]);

        $collect = $documents->collect();
        $array_result = [];

        //return response()->json($collect);

        if (!isset($collect['Data'])) {
            return $this->success([]);
        }

        foreach ($collect['Data'] as $datum) {
            $documents = Http::post(env('CHERRY_REQ'), [
                    'CommandName' => 'GetList',
                    'ModelCode' => 'GADocuments',
                    'UserName' => $request->user()->username,
                    'Token' => $cherry_token,
                    'ParameterData' => [
                        [
                            'ParamKey' => 'Code',
                            'ParamValue' => $datum['ModelEntityCode'],
                            'Operator' => 'eq'
                        ],
                    ]
                ]);

            //return response()->json($documents->collect()['Data']);
            if ($documents->collect()['Data']) {
                $doc_entry = ReservationHeader::where(
                    'DocNum',
                    '=',
                    $documents->collect()['Data'][0]['DocumentReferenceID']
                )
                    ->first();

                $array_result[] = [
                    'Keys' => Str::random(20),
                    'TypeName' => $datum['TypeName'],
                    'ApproveUrl' => $datum['ApproveUrl'],
                    'ApproveToken' => $datum['ApproveToken'],
                    'RejectUrl' => $datum['RejectUrl'],
                    'RejectToken' => $datum['RejectToken'],
                    'Code' => $datum['Code'],
                    'DocDate' => $datum['InsertStamp'],
                    'Details' => $documents->collect()['Data'][0]['DocumentContent'],
                    'DocumentReferenceID' => $documents->collect()['Data'][0]['DocumentReferenceID'],
                    'DocNum' => $documents->collect()['Data'][0]['DocumentReferenceID'],
                    'RequesterName' => $datum['RequesterName'],
                    'StatusId' => $datum['StatusId'],
                    'U_DocEntry' => $doc_entry->U_DocEntry,
                    'Date' => ($datum['Date']) ?
                        date('Y-m-d H:i:s', (int)substr($datum['Date'], 6, 10)) : '',
                ];
            }
        }

        return $this->success([
            'rows' => $array_result,
            'total' => count($collect['Data']),
            'ApprovalStatus' => ['Pending', 'Approved', 'Rejected'],
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function action(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $code = [];
            $selected = collect($request->selected);
            foreach ($selected as $item) {
                $code[] = $item['Code'];
            }

            $action = $request->action;
            $documents = Http::post(env('CHERRY_REQ'), [
                'CommandName' => 'GetList',
                'ModelCode' => 'ApprovalRequests',
                'UserName' => $request->user()->username,
                'Token' => $request->user()->cherry_token,
                'ParameterData' => [
                    [
                        'ParamKey' => 'Code',
                        'ParamValue' => implode(',', $code),
                        'Operator' => 'in'
                    ]
                ]
            ]);

            $concat_array = [];
            foreach ($documents->collect()['Data'] as $index => $item) {
                $item = (object)$item;
                $item->StatusId = $action;
                $concat_array[] = $item;
            }

            //return response()->json($concat_array);

            $approval = Http::post(env('CHERRY_REQ'), [
                'CommandName' => 'SubmitList',
                'ModelCode' => 'ApprovalRequests',
                'UserName' => $request->user()->username,
                'Token' => $request->user()->cherry_token,
                'ModelData' => json_encode($concat_array),
                'ParameterData' => []
            ]);
            return $this->success($approval->collect(), $approval->collect()['Message']);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), '422', [
                'trace' => $exception->getTrace()
            ]);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function approvalStages(Request $request)
    {
        $form = json_decode($request->form);
        $cherry_token = $request->cherry_token;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $documents = Http::withHeaders($headers)
            ->post(env('CHERRY_REQ'), [
                'CommandName' => 'GetList',
                'ModelCode' => 'GADocuments',
                'UserName' => $request->user()->username,
                'Token' => $cherry_token,
                'ParameterData' => [
                    [
                        'ParamKey' => 'DocumentReferenceID',
                        'ParamValue' => $form->DocNum,
                        'Operator' => 'eq'
                    ]
                ]
            ]);

        $collect = $documents->collect();

        $list_code = Http::post(env('CHERRY_REQ'), [
            'CommandName' => 'GetList',
            'ModelCode' => 'ApprovalRequests',
            'UserName' => $request->user()->username,
            'Token' => $cherry_token,
            'ParameterData' => [
                [
                    'ParamKey' => 'ModelEntityCode',
                    'ParamValue' => $collect['Data'][0]['Code'],
                    'Operator' => 'eq'
                ]
            ]
        ]);

        //return response()->json($collect);

        $arr_result = [];
        foreach ($list_code->collect()['Data'] as $datum) {
            $arr_result[] = [
                'Keys' => $datum['Code'],
                'ApproverEmployeeName' => $datum['ApproverEmployeeName'],
                'StatusId' => $datum['StatusId'],
                'ResponseDate' => $datum['ResponseDate'],
                'ResponseDates' => ($datum['ResponseDate']) ?
                    date('Y-m-d H:i:s', (int)substr($datum['ResponseDate'], 6, 10)) : '',
                'Notes' => $datum['Notes'],
                'ApprovalSchemaName' => $datum['ApprovalSchemaName'],
            ];
        }

        return response()->json([
            'rows' => $arr_result,
            'total' => count($list_code->collect()['Data'])
        ]);
    }
}
