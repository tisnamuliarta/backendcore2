<?php

namespace App\Http\Controllers;

use App\Models\Paper;
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
        DB::beginTransaction();
        try {
            $document_id = $request->DocumentReferenceID;
            $status = $request->StatusId;

            if ($document_id) {
                $paper = Paper::where('paper_no', '=', $document_id)->first();
                if ($status == 'Approved') {
                    if ($paper) {
                        DB::connection('sqlsrv')
                            ->table('papers')
                            ->where('paper_no', '=', $document_id)
                            ->update([
                                'status' => 'active'
                            ]);
                    } else {
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
                    }
                } elseif ($status == 'Rejected') {
                    if ($paper) {
                        DB::connection('sqlsrv')
                            ->table('papers')
                            ->where('paper_no', '=', $document_id)
                            ->update([
                                'status' => 'rejected'
                            ]);
                    } else {
                        DB::connection('laravelOdbc')
                            ->table('RESV_H')
                            ->where('DocNum', '=', $document_id)
                            ->update([
                                'ApprovalStatus' => 'N'
                            ]);
                    }
                }
                DB::commit();
                return response()->json([
                    'error' => false,
                    'message' => 'Document updated!'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'No Document Found!'
                ], 422);
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ]);
        }
    }
}
