<?php

namespace App\Traits;

use App\Models\Paper;
use App\Models\Resv\ReservationHeader;
use Illuminate\Support\Facades\Http;

trait CherryApproval
{
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
            if ($datum['Name'] == 'E-RESERVATION NPB' && $form->ItemType == 'Ready Stock') {
                $reservation_code = $datum['Code'];
            } elseif ($datum['Name'] == 'E-RESERVATION SPB' && $form->ItemType != 'Ready Stock') {
                $reservation_code = $datum['Code'];
            }

//            if ($datum['Name'] == 'E-RESERVATION URGENT' && $form->RequestType == 'Urgent') {
//                $reservation_code = $datum['Code'];
//            } elseif ($datum['Name'] == 'E-RESERVATION NORMAL') {
//                $reservation_code = $datum['Code'];
//
        }

        //return response()->json($list_code->collect()['Data']);
        //return response()->json($reservation_code);

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

        $document_content = view('email.approval_resv', [
            'details' => $details,
            'header' => $header
        ])->render();

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
                'CallBackAccessToken' => 'http://sbo2.imip.co.id:3000/backendcore2/api/callback',
                'DocumentContent' => $document_content,
                'Notes' => $form->Memo
            ]
        ]);

        if ($response['MessageType'] == 'error') {
            return $this->error($response->collect()['Message'], '422');
        }

        //return response()->json($response->collect());

        ReservationHeader::where('U_DocEntry', '=', $form->U_DocEntry)
            ->update([
                'ApprovalStatus' => 'W'
            ]);

        return $this->success([
            "U_DocEntry" => $form->U_DocEntry
        ], ($form->U_DocEntry != 'null' ? "Data updated!" : "Data inserted!"));
    }

    /**
     * @param $form
     * @param $request
     *
     * @return array
     */
    public function submitPaperApproval($form, $request)
    {
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
            if ($datum['Name'] == 'E-FORM ENTRY EXIT' && ($form->paper_alias == 'sim' || $form->paper_alias == 'sik')) {
                $reservation_code = $datum['Code'];
            } elseif ($datum['Name'] == 'E-FORM RAPID' &&
                ($form->paper_alias == 'srk' || $form->paper_alias == 'srm')) {
                $reservation_code = $datum['Code'];
            }
        }

        $username = $request->user()->username;
        $company_code = $request->user()->company_code;

        $user_name = ($form->for_self == 'Yes') ? $form->user_name : $form->created_name;

        $response = Http::get(env('CHERRY_CHECK_EMPLOYEE'), [
            'username' => $username,
            'token' => $cherry_token,
            'companyCode' => $company_code,
            'q' => $user_name
        ]);

        $employee_code = '';
        foreach ($response->collect() as $item) {
            if ($item['Nik'] == $form->id_card) {
                $employee_code = $item['EmployeeCode'];
            }
        }

        $document_content = view('email.approval_eform', [
            'form' => $form
        ])->render();

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
                'DocumentReferenceID' => $form->paper_no,
                'CallBackAccessToken' => 'http://sbo2.imip.co.id:3000/backendcore/api/callback',
                'DocumentContent' => $document_content,
                'Notes' => $form->reason
            ]
        ]);

        if ($response['MessageType'] == 'error') {
            return [
                'error' => true,
                'message' => $response->collect()['Message']
            ];
        }

        //return response()->json($response->collect());

        Paper::where('id', '=', $form->id)
            ->update([
                'status' => 'pending'
            ]);
        return [
            'error' => false,
            'message' => $response->collect()['Message']
        ];
    }
}
