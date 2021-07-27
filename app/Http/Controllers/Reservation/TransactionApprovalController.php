<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Traits\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TransactionApprovalController extends Controller
{
    use Approval;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $cherry_token = $request->cherry_token;
        $employee_code = $request->user()->user_code;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $documents = Http::withHeaders($headers)
            ->post(env('CHERRY_REQ'), [
                'CommandName' => 'GetList',
                'ModelCode' => 'ApprovalRequests',
                'UserName' => $request->user()->username,
                'Token' => $cherry_token,
                'ParameterData' => [
                    [
                        'ParamKey' => 'ApproverCode',
                        'ParamValue' => $employee_code,
                        'Operator' => 'eq'
                    ]
                ]
            ]);

        $collect = $documents->collect();
        $array_result = [];

        foreach ($collect['Data'] as $datum) {
            $documents = Http::withHeaders($headers)
                ->post(env('CHERRY_REQ'), [
                    'CommandName' => 'GetList',
                    'ModelCode' => 'GADocuments',
                    'UserName' => $request->user()->username,
                    'Token' => $cherry_token,
                    'ParameterData' => [
                        [
                            'ParamKey' => 'Code',
                            'ParamValue' => $datum['ModelEntityCode'],
                            'Operator' => 'eq'
                        ]
                    ]
                ]);

            $array_result[] = [
                'Keys' => Str::random(10),
                'TypeName' => $datum['TypeName'],
                'Details' => $documents->collect()['Data'][0]['DocumentContent'],
                'RequesterName' => $datum['RequesterName'],
                'StatusId' => $datum['StatusId'],
                'Date' => ($datum['Date']) ?
                    date('Y-m-d H:i:s', (int)substr($datum['Date'], 6, 10)) : '',
            ];
        }

        return response()->json([
            'rows' => $array_result,
            'total' => count($collect['Data'])
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function action(Request $request): \Illuminate\Http\JsonResponse
    {
        $form = $request->form;
        $action_text = $request->actionText;
        $action = $request->titleAction;
        // App/Http/Traits/Approval
        return $this->actionApproval($request, $form, $action_text, $action);
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

        $arr_result = [];
        foreach ($list_code->collect()['Data'] as $datum) {
            $arr_result[] = [
                'ApproverEmployeeName' => $datum['ApproverEmployeeName'],
                'StatusId' => $datum['StatusId'],
                'ResponseDate' => ($datum['ResponseDate']) ?
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
