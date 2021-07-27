<?php

namespace App\Http\Controllers;

use App\Traits\Approval;
use App\Models\Resv\ReservationDetails;
use App\Models\Resv\ReservationHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CherryApprovalController extends Controller
{

    use Approval;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(Request $request)
    {
        try {
            $document_id = $request->DocumentReferenceID;
            $status = $request->StatusId;

            if ($status == 'Approved') {
                DB::connection('laravelOdbc')
                    ->table('RESV_H')
                    ->where('DocNum', '=', $document_id)
                    ->update([
                        'ApprovalStatus' => 'Y',
                        'DocStatus' => 'O'
                    ]);

                $data_header = ReservationHeader::select(
                    "RESV_H.*",
                    "RESV_H.Company as CompanyName",
                )
                    ->where("RESV_H.DocNum", "=", $document_id)
                    ->first();

                $data_details = ReservationDetails::where("U_DocEntry", "=", $data_header->U_DocEntry)
                    ->get();

                $this->createGoodsIssueRequest($data_header, $data_details);

            } elseif ($status == 'Rejected') {
                DB::connection('laravelOdbc')
                    ->table('RESV_H')
                    ->where('DocNum', '=', $document_id)
                    ->update([
                        'ApprovalStatus' => 'N'
                    ]);
            }

            return response()->json([
                'error' => false,
                'message' => 'Document updated!'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ]);
        }
    }
}
