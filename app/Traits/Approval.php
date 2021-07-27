<?php

namespace App\Traits;

use App\Jobs\ProcessApproval;
use App\Models\Resv\ApprovalApprover;
use App\Models\Resv\ApprovalRequester;
use App\Models\Resv\ReservationDetails;
use App\Models\Resv\ReservationHeader;
use App\Models\TransactionApproval;
use App\Models\TransactionApprovalDetails;
use App\Models\User;
use App\Models\Resv\UserLeave;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Approval
{
    use ConnectHana;

    /**
     * @param $request
     * @param $form
     * @param null $action_text
     * @param null $action
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function actionApproval($request, $form, $action_text = null, $action = null): \Illuminate\Http\JsonResponse
    {
        $data_header = ReservationHeader::select(
            "RESV_H.*",
            "OUSR_COMP.U_DbCode as CompanyName",
            "OUSR_H.U_NIK",
            DB::raw('OUSR_H."U_UserName" AS "RequestName"'),
            DB::raw('OUSR_H."U_UserCode" AS "U_UserCode"'),
            DB::raw('RESV_H."U_NIK" AS "UserNIK"'),
            DB::raw('CONCAT(OUSR_H."U_Division", CONCAT( \'/\', OUSR_H."U_Department")) AS "Departments"')
        )
            ->leftJoin("OUSR_COMP", "OUSR_COMP.U_DocEntry", "RESV_H.Company")
            ->leftJoin("OUSR_H", "RESV_H.Requester", "OUSR_H.U_UserID")
            ->where("RESV_H.U_DocEntry", "=", $form['U_DocEntry'])
            ->first();

        if (empty($data_header)) {
            return response()->json([
                'errors' => true,
                'message' => "Please save document first!"
            ]);
        }
        if (empty($data_header['UserNIK'])) {
            return response()->json([
                'errors' => true,
                'message' => "User Request NIK Cannot Empty!"
            ]);
        }

        $data_details = ReservationDetails::where("U_DocEntry", "=", $data_header->U_DocEntry)
            ->get();

        if (count($data_details) == 0) {
            return response()->json([
                'errors' => true,
                'message' => "Document cannot without details!"
            ]);
        }

        $find_stages = $this->findStages($form, $action, $request, $data_header);
        //dd($find_stages);
        if (!empty($find_stages)) {
            $params = [
                'find_stages' => $find_stages,
                'form' => $form,
                'data_header' => $data_header,
                'data_details' => $data_details,
                'action_text' => $action_text,
                'action' => $action,
                'request' => $request
            ];
            // dd($this->createGoodsIssueRequest($data_header, $data_details));
            return $this->loopStages($params);
        }
        return response()->json([
            'errors' => true,
            'message' => "cannot find stages!"
        ]);
    }

    /**
     * @param $form
     * @param $action
     * @param $request
     * @param $data_header
     * @return mixed
     */
    protected function findStages($form, $action, $request, $data_header)
    {
        $find_stages = null;
        if ($action) {
            $schema = env('DB_SCHEMA');
            $doc_date = $this->formatDate($data_header->DocDate);
            $template_approval = TransactionApproval::where("U_DocKey", "=", $form["U_DocEntry"])->first();
            $check_sub = User::where("OUSR_H.U_SubId", "=", $request->user()->U_UserID)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->whereRaw('\'' . $doc_date . '\' BETWEEN ' . $schema . '."OUSR_LEAVE"."U_DateFrom" AND ' . $schema . '."OUSR_LEAVE"."U_DateTo"')
                ->where("U_WTM2.U_WtmCode", "=", $template_approval->U_WtmCode)
                ->leftJoin("OUSR_OWST", "OUSR_OWST.U_UserID", "OUSR_H.U_UserID")
                ->leftJoin("U_WTM2", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                ->leftJoin("OUSR_LEAVE", "OUSR_LEAVE.U_UserID", "OUSR_H.U_UserID")
                ->count();
            // dd($check_sub);
            $query = ApprovalApprover::leftJoin("U_OWST", "U_OWST.U_WstCode", "U_WTM2.U_WstCode")
                ->leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_OWST.U_WstCode")
                //->where("OUSR_OWST.U_UserID", "=", $request->user()->U_UserID)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->where("U_WTM2.U_WtmCode", "=", $template_approval->U_WtmCode)
                ->orderBy("OUSR_OWST.U_DocEntry", "DESC")
                ->select(
                    "OUSR_OWST.*",
                    "U_OWST.U_MaxReqr",
                    "U_OWST.U_MaxRejReqr",
                    "U_OWST.U_WstName"
                );
            if ($check_sub > 0) {
                $find_stages = $query->get();
            } else {
                $find_stages = $query->where("OUSR_OWST.U_UserID", "=", $request->user()->U_UserID)->get();
            }
            //dd($find_stages);
        } else {
            $find_stages = ApprovalRequester::leftJoin("U_OWST", "U_OWST.U_WstCode", "U_WTM1.U_WstCode")
                ->leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_OWST.U_WstCode")
                ->where("OUSR_OWST.U_UserID", "=", $data_header->CreatedBy)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->orderBy("OUSR_OWST.U_DocEntry", "DESC")
                ->select(
                    "OUSR_OWST.*",
                    "U_OWST.U_MaxReqr",
                    "U_OWST.U_MaxRejReqr",
                    "U_OWST.U_WstName"
                )->get();
//            $find_stages = UserStages::select(
//                "OUSR_OWST.*",
//                "U_OWST.U_MaxReqr",
//                "U_OWST.U_MaxRejReqr",
//                "U_OWST.U_WstName"
//            )
//                ->leftJoin("U_OWST", "U_OWST.U_WstCode", "OUSR_OWST.U_WstCode")
//                ->where("OUSR_OWST.U_UserID", "=", $data_header->CreatedBy)
//                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
//                ->orderBy("OUSR_OWST.U_DocEntry", "DESC")
//                ->get();
        }
        return $find_stages;
    }

    /**
     * @param $date
     * @return false|string
     */
    protected function formatDate($date)
    {
        return date('Y-m-d', strtotime($date));
    }

    /**
     * @param array $params
     * @return \Illuminate\Http\JsonResponse
     */
    protected function loopStages(array $params): \Illuminate\Http\JsonResponse
    {
        $request = $params['request'];
        $action = $params['action'];
        $action_text = $params['action_text'];
        $data_details = $params['data_details'];
        $data_header = $params['data_header'];
        $form = $params['form'];
        $find_stages = $params['find_stages'];

        $is_send = false;
        foreach ($find_stages as $find_stage) {
            // get requester for approval
            $requester = $this->getRequester($find_stage, $form, $action, $request);
            if ($requester) {
                // Get User Who Approves The Documents
                $approves = $this->getApproves($form, $requester, $find_stage, $action);
                //dd($approves);
                $receiver = [];
                $approved_name = [];
                $new_approves = [];
                $schema = env('DB_SCHEMA');
                // $approves['final_approves'];
                // $approves['approves']
                $doc_date = $this->formatDate($data_header->DocDate);
                if ($approves['final']) {
                    foreach ($approves['final_approval'] as $approve) {
                        $data_leave = UserLeave::where("OUSR_LEAVE.U_UserID", "=", $approve->U_UserID)
                            ->whereRaw('\'' . $doc_date . '\' BETWEEN ' . $schema . '."OUSR_LEAVE"."U_DateFrom" AND ' . $schema . '."OUSR_LEAVE"."U_DateTo"')
                            ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_LEAVE.U_UserID")
                            ->select("OUSR_H.*", "OUSR_LEAVE.U_DateFrom", "OUSR_LEAVE.U_DateTo")
                            ->orderBy("U_CreatedAt", "DESC")
                            ->first();
                        if ($data_leave) {
                            $sub_user = User::where("U_UserID", "=", $data_leave->U_SubId)->first();
                            $approves['final_approves'][] = $this->getUserLeave(
                                $data_header,
                                $data_leave,
                                $approve,
                                $sub_user
                            );
                        } else {
                            $approves['final_approves'][] = (object)[
                                "U_Email" => $approve->U_Email,
                                "U_UserName" => $approve->U_UserName,
                                "U_UserID" => $approve->U_UserID,
                                "U_SortId" => $approve->U_SortId,
                                "U_WtmCode" => $approve->U_WtmCode,
                            ];
                        }
                    }
                }

                foreach ($approves['approval'] as $approve) {
                    $data_leave = UserLeave::where("OUSR_LEAVE.U_UserID", "=", $approve->U_UserID)
                        ->whereRaw('\'' . $doc_date . '\' BETWEEN ' . $schema . '."OUSR_LEAVE"."U_DateFrom" AND ' . $schema . '."OUSR_LEAVE"."U_DateTo"')
                        ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_LEAVE.U_UserID")
                        ->select("OUSR_H.*", "OUSR_LEAVE.U_DateFrom", "OUSR_LEAVE.U_DateTo")
                        ->orderBy("U_CreatedAt", "DESC")
                        ->first();
                    if ($data_leave) {
                        $sub_user = User::where("U_UserID", "=", $data_leave->U_SubId)->first();
                        $approves['approves'][] = $this->getUserLeave($data_header, $data_leave, $approve, $sub_user);
                    } else {
                        $approves['approves'][] = (object)[
                            "U_Email" => $approve->U_Email,
                            "U_UserName" => $approve->U_UserName,
                            "U_UserID" => $approve->U_UserID,
                            "U_SortId" => $approve->U_SortId,
                            "U_WtmCode" => $approve->U_WtmCode,
                        ];
                    }
                }

                foreach ($approves['approves'] as $approve) {
                    $receiver[] = $approve->U_Email;
                    $approved_name[] = $approve->U_UserName;
                    // dd($approve->U_Email);
                }
                // Send email approval
                $param_email = [
                    'form' => $form,
                    'data_header' => $data_header,
                    'data_details' => $data_details,
                    'approved_name' => $approved_name,
                    'requester' => $requester,
                    'receiver' => $receiver,
                    'action' => $action,
                    'final' => $approves['final'],
                ];
                $this->sendEmailApproval($param_email);
                //dd($approves['approves']);

                if ($approves['final']) {
                    if ($action == 'Approve') {
                        // insert to goods issue
                        $this->createGoodsIssueRequest($data_header, $data_details);
                        //ProcessGoodsIssueRequest::dispatch($data_header, $data_details);
                    }
                }
                // dd($approves);

                // Update Document Status
                $this->updateReservationHeader($form, $action, $approves);

                $header = ReservationHeader::where("U_DocEntry", "=", $form['U_DocEntry'])->first();
                // Insert Transaction Approval
                $transaction_approval = $this->insertTransactionApproval(
                    $approves,
                    $requester,
                    $find_stage,
                    $header,
                    $action_text,
                    $request
                );
                // send notification to related user
                $this->sendNotification($approves['approves'], $data_header, $requester);
                $approval_key = $transaction_approval->U_WddCode;
                // Update Document Status
                $header = ReservationHeader::where("U_DocEntry", "=", $form['U_DocEntry'])->first();
                $header->ApprovalKey = $approval_key;
                $header->save();

                $is_send = true;
            }
            // else {
            //     return response()->json([
            //         'errors' => true,
            //         'message' => "You're not register as requester!"
            //     ]);
            // }
        }
        if ($is_send) {
            return response()->json([
                'errors' => false,
                'message' => "Document has been sent!",
                'U_DocEntry' => $form['U_DocEntry']
            ]);
        } else {
            return response()->json([
                'errors' => true,
                'message' => "Failed to send document!",
                'U_DocEntry' => $form['U_DocEntry']
            ]);
        }
    }

    /**
     * @param $find_stage
     * @param $form
     * @param $action
     * @param $request
     * @return mixed
     */
    protected function getRequester($find_stage, $form, $action, $request)
    {
        $requester = ApprovalRequester::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM1.U_WstCode")
            ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
            ->select("OUSR_H.U_Email", "U_WTM1.U_WtmCode", "OUSR_H.U_UserID")
            ->where("U_WTM1.U_WstCode", "=", $find_stage['U_WstCode'])
            ->where("OUSR_H.U_UserID", "=", $form['CreatedBy'])
            ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
            ->first();

        if ($action) {
            $requester = TransactionApproval::where("U_DocKey", "=", $form['U_DocEntry'])
                ->leftJoin("U_WDD1", "U_WDD1.U_WddCode", "U_OWDD.U_WddCode")
                ->leftJoin("OUSR_H", "U_WDD1.U_UserID", "OUSR_H.U_UserID")
                ->select("U_WDD1.*", "U_OWDD.U_WtmCode", "OUSR_H.U_Email")
                ->orderBy("U_WDD1.U_SortID", "DESC")
                ->first();

            $requester2 = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                ->select("OUSR_H.U_Email", "U_WTM2.U_WtmCode", "OUSR_H.U_UserID")
                ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->where("U_WTM2.U_SortId", "=", $requester->U_SortID)
                ->where("OUSR_H.U_UserID", "=", $requester->U_UserID)
                //->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                ->orderBy("U_WTM2.U_SortId", "DESC")
                // ->distinct()
                ->first();
        }
        return $requester;
    }

    /**
     * @param $form
     * @param $requester
     * @param $find_stage
     * @return array
     */
    protected function getApproves($form, $requester, $find_stage, $action): array
    {
        // check if this is first approval
        $check_latest_approve = TransactionApproval::where("U_DocKey", "=", $form['U_DocEntry'])->count();
        if ($check_latest_approve == 0) {
            $first_approval = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                ->select("U_WTM2.U_SortId")
                ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->where("U_WTM2.U_SortId", "=", 1)
                ->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                ->orderBy("U_WTM2.U_SortId", "ASC")
                ->distinct()
                ->first();
            if (!$first_approval) {
                $first_approval = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                    ->select("U_WTM2.U_SortId")
                    ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    ->where("U_WTM2.U_SortId", "=", 1)
                    ->orderBy("U_WTM2.U_SortId", "ASC")
                    ->distinct()
                    ->first();
            }
            // return first approval
            $approves = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                ->select("OUSR_H.U_Email", "U_WTM2.U_SortId", "OUSR_H.U_UserName", "OUSR_H.U_UserID", "U_WTM2.U_WtmCode")
                ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->where("U_WTM2.U_SortId", "=", $first_approval->U_SortId)
                ->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                ->orderBy("U_WTM2.U_SortId", "ASC")
                ->distinct()
                ->get();
            if (count($approves) == 0) {
                $approves = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                    ->select("OUSR_H.U_Email", "U_WTM2.U_SortId", "OUSR_H.U_UserName", "OUSR_H.U_UserID", "U_WTM2.U_WtmCode")
                    ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    ->where("U_WTM2.U_SortId", "=", $first_approval->U_SortId)
                    ->orderBy("U_WTM2.U_SortId", "ASC")
                    ->distinct()
                    ->get();
            }

            return [
                'approval' => $approves,
                'final_approval' => [],
                'response' => 1,
                'final' => false
            ];
        } else {
            $check_approval_header = TransactionApproval::where("U_DocKey", "=", $form['U_DocEntry'])->first();
            if ($check_approval_header->U_DocStatus == 'N') {
                // check if approval status is reject
                // then retry as first time approval
                $first_query = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                    ->select("U_WTM2.U_SortId")
                    ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    ->where("U_WTM2.U_SortId", "=", 1)
                    // ->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                    ->orderBy("U_WTM2.U_SortId", "ASC")
                    ->distinct();

                if ($first_query->count() == 1) {
                    $first_approval = $first_query->first();
                } else {
                    $first_approval = $first_query
                        ->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                        ->first();
                }
                // return first approval
                $query_approve = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                    ->select("OUSR_H.U_Email", "U_WTM2.U_SortId", "OUSR_H.U_UserName", "OUSR_H.U_UserID", "U_WTM2.U_WtmCode")
                    ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    ->where("U_WTM2.U_SortId", "=", $first_approval->U_SortId)
                    ->orderBy("U_WTM2.U_SortId", "ASC")
                    ->distinct();

                if ($query_approve->count() == 1) {
                    $approves = $query_approve->get();
                } else {
                    $approves = $query_approve
                        ->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                        ->get();
                }

                return [
                    'approval' => $approves,
                    'final_approval' => [],
                    'response' => 2,
                    'final' => false
                ];
            }
            // short the latest approval
            $check_latest = TransactionApproval::where("U_DocKey", "=", $form['U_DocEntry'])
                ->leftJoin("U_WDD1", "U_WDD1.U_WddCode", "U_OWDD.U_WddCode")
                ->where("U_WDD1.U_Status", "W")
                ->select("U_WDD1.U_SortID")
                ->orderBy("U_WDD1.U_SortID", "DESC")
                ->first();

            $next_app = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                ->select("U_WTM2.U_SortId")
                ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                ->where("U_WTM2.U_SortId", "=", ($check_latest->U_SortID + 1))
                ->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                ->orderBy("U_WTM2.U_SortId", "ASC")
                ->distinct()
                ->first();

            if ($next_app) {
                $approves = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                    ->select("OUSR_H.U_Email", "U_WTM2.U_SortId", "OUSR_H.U_UserName", "OUSR_H.U_UserID", "U_WTM2.U_WtmCode")
                    ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                    ->where("U_WTM2.U_SortId", "=", $next_app->U_SortId)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    //->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                    ->orderBy("U_WTM2.U_SortId", "ASC")
                    ->distinct()
                    ->get();
                return [
                    'approval' => $approves,
                    'final_approval' => [],
                    'response' => 3,
                    'final' => false
                ];
            } else {
                $approves = ApprovalApprover::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM2.U_WstCode")
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                    ->select(
                        "OUSR_H.U_Email",
                        "U_WTM2.U_SortId",
                        "OUSR_H.U_UserName",
                        "OUSR_H.U_UserID",
                        "U_WTM2.U_WtmCode"
                    )
                    ->where("U_WTM2.U_WtmCode", "=", $requester->U_WtmCode)
                    ->where("U_WTM2.U_SortId", "=", $check_latest->U_SortID)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    //->whereNotIn("U_WTM2.U_WstCode", [$find_stage['U_WstCode']])
                    ->orderBy("U_WTM2.U_SortId", "ASC")
                    ->distinct();

                $approver = $approves->get();
                $approves = $approves->first();

                $requester = ApprovalRequester::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM1.U_WstCode")
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                    ->select("OUSR_H.U_Email", "U_WTM1.U_WtmCode", "OUSR_H.U_UserID", "OUSR_H.U_UserName",)
                    ->where("U_WTM1.U_WtmCode", "=", $approves->U_WtmCode)
                    ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                    ->distinct()
                    ->get();

                return [
                    'approval' => $requester,
                    'final_approval' => $approver,
                    'response' => 4,
                    'final' => true
                ];
            }
        }
    }

    /**
     * @param $data_header
     * @param $data_leave
     * @param $approve
     * @param $sub_user
     * @return object
     */
    protected function getUserLeave($data_header, $data_leave, $approve, $sub_user)
    {
        if ($this->formatDate($data_header->DocDate) >= $this->formatDate($data_leave->U_DateFrom)
            && $this->formatDate($data_header->DocDate) <= $this->formatDate($data_leave->U_DateTo)) {
            return (object)[
                "U_Email" => $sub_user->U_Email,
                "U_UserName" => $sub_user->U_UserName,
                "U_UserID" => $sub_user->U_UserID,
                "U_SortId" => $approve->U_SortId,
                "U_WtmCode" => $approve->U_WtmCode,
            ];
        }
    }

    /**
     * @param $param_email
     */
    protected function sendEmailApproval($param_email)
    {
        $form = $param_email['form'];
        $data_header = $param_email['data_header'];
        $data_details = $param_email['data_details'];
        $approved_name = $param_email['approved_name'];
        $requester = $param_email['requester'];
        $receiver = $param_email['receiver'];
        $action = $param_email['action'];
        $final = $param_email['final'];
        //content Email
        $content = [
            'subject' => '[E-Reservation] Need for Approval for Document Number ' . $form['DocNum'],
            'content' => [
                'header' => $data_header,
                'details' => $data_details,
                'url_approve' => '#',
                'url_reject' => '#',
                'approve_name' => implode(' & ', $approved_name),
            ]
        ];

        // cc user email
        $cc_email = $requester->U_Email;

        $content['content']['approve_message'] = '';
        if ($action) {
            if ($final) {
                if ($action == 'Approve') {
                    $content['subject'] = '[E-Reservation] Document Number ' . $form['DocNum'] . ' Has Been Approved!';
                    $content['content']['approve_message'] = 'Your Reservation Request No. ' . $form['DocNum'] . ' Has Been Approved!';
                } else {
                    $content['subject'] = '[E-Reservation] Document Number ' . $form['DocNum'] . ' Has Been Rejected!';
                    $content['content']['approve_message'] = '';
                }
                //$receiver = $requester->U_Email;
                $docs = ReservationHeader::where("RESV_H.U_DocEntry", "=", $form["U_DocEntry"])
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "RESV_H.CreatedBy")
                    ->select("OUSR_H.U_Email", "OUSR_H.U_UserName")
                    ->first();
                $receiver = [];
                $approved_name = [];
                $receiver[] = $docs->U_Email;
                $approved_name[] = $docs->U_UserName;
                $content['content']['approve_name'] = implode(' & ', $approved_name);

                $list_cc = TransactionApproval::where("U_OWDD.U_DocKey", "=", $form["U_DocEntry"])
                    ->leftJoin("U_WDD1", "U_WDD1.U_WddCode", "=", "U_OWDD.U_WddCode")
                    ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "U_WDD1.U_UserID")
                    ->select("OUSR_H.U_Email")
                    ->distinct()
                    ->get();

                $cc_email = [];
                foreach ($list_cc as $item) {
                    $cc_email[] = $item->U_Email;
                }
            } else {
                if ($action != 'Approve') {
                    $requestUser = ApprovalRequester::leftJoin("OUSR_OWST", "OUSR_OWST.U_WstCode", "U_WTM1.U_WstCode")
                        ->leftJoin("OUSR_H", "OUSR_H.U_UserID", "OUSR_OWST.U_UserID")
                        ->select("OUSR_H.U_Email", "U_WTM1.U_WtmCode", "OUSR_H.U_UserID", "OUSR_H.U_UserName",)
                        ->where("U_WTM1.U_WtmCode", "=", $requester->U_WtmCode)
                        ->where("OUSR_OWST.U_DbCode", "=", $form['CompanyName'])
                        ->distinct()
                        ->get();
                    $receiver = [];
                    $approved_name = [];
                    foreach ($requestUser as $key => $value) {
                        $receiver[] = $value->U_Email;
                        $approved_name[] = $value->U_UserName;
                    }
                    $content['content']['approve_name'] = implode(' & ', $approved_name);
                    $content['content']['approve_message'] = '';
                    $cc_email = [];
                    $content['subject'] = '[E-Reservation] Document Number ' . $form['DocNum'] . ' Has Been Rejected!';
                }
            }
        }
        // dd($content);
        // dd($approved_name);
        // Send Email
        ProcessApproval::dispatch($content, $receiver, $cc_email);
        // $email = new ProcessApprovalMail($content);
        // Mail::to($receiver)
        //     ->cc($cc_email)
        //     ->send(new ProcessApprovalMail($content));
    }

    /**
     * @param $header
     * @param $details
     * @return array
     */
    protected function createGoodsIssueRequest($header, $details): array
    {
        // Login To Service Layer
        $this->loginServiceLayer($header['CompanyName']);
        // Get Latest DocNum
        $docNum = $this->getLatestGoodsIssueRequest($header['CompanyName']);
        // Get Current User Login In Service Layer
        $current_user = $this->getCurrentLoginUser($header['CompanyName'], "RESV");
        $doc_date = $header['DocDate'];
        if (substr($docNum, 0, 2) != substr($header['DocDate'], 2, 2)) {
            $doc_date = '20' . substr($docNum, 0, 2) . date('-m-d', strtotime($header['DocDate']));
        }
        $req_date = $header['RequiredDate'];
        if (substr($docNum, 0, 2) != substr($header['RequiredDate'], 2, 2)) {
            $req_date = '20' . substr($docNum, 0, 2) . date('-m-d', strtotime($header['RequiredDate']));
        }
        if ($header['RequestType'] == 'Normal') {
            $gir_type = 'ERESV-NM';
        } else {
            $gir_type = 'ERESV-RS';
        }
        // Assign params
        $params_header = [
            //"RequestStatus" => "W", auto
            //"Creator" => "LOG14", auto
            "Remark" => $header['Memo'],
            "UserSign" => $current_user['USERID'],
            "U_DocDate" => $doc_date,
            "U_DueDate" => $req_date,
            "U_UserCode" => $current_user['USER_CODE'],
            "U_Remarks" => $header['Memo'],
            "U_ReqBy" => "RESV",
            "U_DocNum" => ((int)$docNum + 1),
            "U_CatShft" => $gir_type,
            "U_CatPot" => 0.0,
            "U_Attach" => null,
            "U_ResvCreatedBy" => $header['U_UserCode'],
            "U_ERESV_DOCNUM" => $header['DocNum'],
        ];
        $whs_to = ($header['RequestType'] == 'For Restock SubWH') ? $header['WhTo'] : null;

        $param_details = [];
        foreach ($details as $detail) {
            $param_details[] = [
                "U_ItemCode" => $detail->ItemCode,
                "U_ItemDesc" => $detail->ItemName,
                "U_WhsCode" => $detail->WhsCode,
                "U_Bin" => null,
                "U_Remarks" => null,
                "U_ReqQty" => $detail->ReqQty,
                "U_Issued" => 0,
                "U_ResType" => $detail->RequestType,
                "U_ItemCat" => $detail->ItemCategory,
                "U_OthResv" => $detail->OtherResvNo,
                "U_WhsTo" => $whs_to,
            ];
        }

        $array_all = array_merge($params_header, [
            "DGN_EI_IGR1Collection" => $param_details
        ]);
        // dd($array_all);
        return $this->insertGoodsIssueRequest($array_all, $header);
    }

    /**
     * @param $db_name
     * @return array|\Illuminate\Http\JsonResponse
     */
    protected function loginServiceLayer($db_name)
    {
        $params = [
            "UserName" => env('SERVICE_LAYER_USER'),
            "Password" => env('SERVICE_LAYER_PASSWORD'),
            "CompanyDB" => $db_name,
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://192.168.88.8:50000/b1s/v1/Login");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $response = curl_exec($curl);

        $response_text = json_decode($response);

        if (property_exists($response_text, "error")) {
            return response()->json($response_text);
        } else {
            $routeId = "";
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
            $array = [
                'B1SESSION' => $response_text->SessionId,
                'ROUTEID' => $routeId,
                "UserID" => session('UserID'),
                "password" => session('password'),
                "db_name" => $db_name,
            ];

            session($array);
        }

        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        return $headers;
    }

    /**
     * @return null
     */
    protected function getLatestGoodsIssueRequest($db_name)
    {
        $connect = $this->connectHana();
        $sql = 'select "U_DocNum" from ' . $db_name . '."@DGN_EI_OIGR" order by "U_DocNum" DESC LIMIT 1';
        $rs = odbc_exec($connect, $sql);

        if (!$rs) {
            exit("Error in SQL");
        }
        $arr = [];
        while (odbc_fetch_row($rs)) {
            $arr = [
                "U_DocNum" => odbc_result($rs, "U_DocNum"),
            ];
        }
        return $arr['U_DocNum'];
    }

    /**
     * @param $db_name
     * @param $user_code
     * @return array
     */
    protected function getCurrentLoginUser($db_name, $user_code): array
    {
        $connect = $this->connectHana();
        $sql = "select \"USERID\", \"USER_CODE\"
                from  $db_name.OUSR WHERE \"USER_CODE\"='" . $user_code . "' LIMIT 1";
        $rs = odbc_exec($connect, $sql);

        if (!$rs) {
            exit("Error in SQL");
        }
        $arr = [];
        while (odbc_fetch_row($rs)) {
            $arr = [
                "USERID" => odbc_result($rs, "USERID"),
                "USER_CODE" => odbc_result($rs, "USER_CODE"),
            ];
        }
        return $arr;
    }

    /**
     * @param $params
     * @param $header
     *
     * @return array
     */
    protected function insertGoodsIssueRequest($params, $header): array
    {
        $sessionId = session('B1SESSION');
        $routeId = session('ROUTEID');
        $headers[] = "Cookie: B1SESSION=" . $sessionId . "; ROUTEID=" . $routeId . ";";
        $curl = curl_init();
        $url = "https://192.168.88.8:50000/b1s/v1/DGN_EI_IGR";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        // dd($response);
        $response_text = json_decode($response);
        $result = false;
        $result_data_api = [];
        if (!empty($response_text->error->code)) {//if Error
            $message = 'Data Not Saved!';
        } else {
            $message = 'Data Saved Successfully!';
            $result = true;
            $result_data_api = $response_text;
            DB::connection('laravelOdbc')
                ->table('RESV_H')
                ->where('U_DocEntry', '=', $header->U_DocEntry)
                ->update([
                    'SAP_GIRNo' => $response_text->DocNum
                ]);
        }
        return [
            'result' => $result,
            'message' => $message,
            'result_data_api' => $result_data_api
        ];
    }

    /**
     * @param $form
     * @param $action
     * @param $approves
     */
    protected function updateReservationHeader($form, $action, $approves)
    {
        $header = ReservationHeader::where("U_DocEntry", "=", $form['U_DocEntry'])->first();
        if ($action) {
            if ($approves['final']) {
                if ($action == 'Approve') {
                    $header->DocStatus = 'O';
                    $header->ApprovalStatus = 'Y';
                    $header->save();
                } else {
                    $header->DocStatus = 'D';
                    $header->ApprovalStatus = 'N';
                    $header->save();
                }
            } else {
                if ($action == 'Approve') {
                    $header->DocStatus = 'D';
                    $header->ApprovalStatus = 'W';
                    $header->save();
                } else {
                    $header->DocStatus = 'D';
                    $header->ApprovalStatus = 'N';
                    $header->save();
                }
            }
        } else {
            $header->DocStatus = 'D';
            $header->ApprovalStatus = 'W';
            $header->save();
        }
    }

    /**
     * @param $approves
     * @param $requester
     * @param $find_stage
     * @param $header
     * @param $action_text
     * @return TransactionApproval
     */
    protected function insertTransactionApproval($approves, $requester, $find_stage, $header, $action_text, $request)
    {
        $transaction_approval = TransactionApproval::where("U_DocKey", "=", $header->U_DocEntry)->first();
        if ($transaction_approval) {
            // update  approval table
            $transaction_approval->U_WtmCode = $requester['U_WtmCode'];
            $transaction_approval->U_CurrStage = $find_stage['U_WstCode'];
            $transaction_approval->U_DocStatus = $header->ApprovalStatus;
            $transaction_approval->U_DocKey = $header->U_DocEntry;
            $transaction_approval->U_DocType = 'ERESV';
            $transaction_approval->U_MaxReqr = $find_stage['U_MaxReqr'];
            $transaction_approval->U_MaxRejReqr = $find_stage['U_MaxRejReqr'];
            $transaction_approval->U_RequesterID = $requester['U_UserID'];
            $transaction_approval->U_Remarks = $action_text;
            $transaction_approval->save();
            if ($approves['final']) {
                foreach ($approves['final_approves'] as $approve) {
                    $transaction_details = TransactionApprovalDetails::where("U_UserID", "=", $approve->U_UserID)
                        ->where("U_WddCode", "=", $transaction_approval->U_WddCode)
                        ->where("U_Status", "=", "W")
                        ->first();

                    if ($transaction_details) {
                        $transaction_details->U_StepCode = $find_stage['U_WstCode'];
                        $transaction_details->U_Status = $header->ApprovalStatus;
                        $transaction_details->U_Remarks = $action_text;
                        $transaction_details->U_UpdateDate = Carbon::now();
                        $transaction_details->save();
                    }
                }
            } else {
                if ($header->ApprovalStatus == 'N') {
                    foreach ($approves['approves'] as $approve) {
                        $transaction_details = TransactionApprovalDetails::where("U_UserID", "=", $requester['U_UserID'])
                            ->where("U_WddCode", "=", $transaction_approval->U_WddCode)
                            ->orderBy("U_DocEntry", "DESC")
                            ->first();

                        if ($transaction_details) {
                            $transaction_details->U_StepCode = $find_stage['U_WstCode'];
                            $transaction_details->U_Status = $header->ApprovalStatus;
                            $transaction_details->U_Remarks = $action_text;
                            $transaction_details->U_UpdateDate = Carbon::now();
                            $transaction_details->save();
                        }
                    }
                } else {
                    foreach ($approves['approves'] as $approve) {
                        $transaction_details = TransactionApprovalDetails::where("U_Status", "=", 'W')
                            ->where("U_WddCode", "=", $transaction_approval->U_WddCode)
                            //->where("U_UserID", "=", $requester['U_UserID'])
                            ->where("U_UserID", "=", $request->user()->U_UserID)
                            ->orderBy("U_DocEntry", "DESC")
                            ->first();
                        //dd($transaction_details);

                        if ($transaction_details) {
                            $transaction_details->U_StepCode = $find_stage['U_WstCode'];
                            $transaction_details->U_Status = 'Y';
                            $transaction_details->U_Remarks = $action_text;
                            $transaction_details->U_UpdateDate = Carbon::now();
                            $transaction_details->save();
                        }
                    }

                    foreach ($approves['approves'] as $approve) {
                        $transaction_details = new TransactionApprovalDetails();
                        $transaction_details->U_WddCode = $transaction_approval->U_WddCode;
                        $transaction_details->U_SortID = $approve->U_SortId;
                        $transaction_details->U_StepCode = $find_stage['U_WstCode'];
                        $transaction_details->U_UserID = $approve->U_UserID;
                        $transaction_details->U_Status = $header->ApprovalStatus;
                        $transaction_details->U_Remarks = null;
                        $transaction_details->U_UserSign = $requester['U_UserID'];
                        $transaction_details->U_CreateDate = Carbon::now();
                        $transaction_details->save();
                    }
                }
            }
        } else {
            // Insert into approval table
            $transaction_approval = new TransactionApproval();
            $transaction_approval->U_WtmCode = $requester['U_WtmCode'];
            $transaction_approval->U_CurrStage = $find_stage['U_WstCode'];
            $transaction_approval->U_DocStatus = $header->ApprovalStatus;
            $transaction_approval->U_DocKey = $header->U_DocEntry;
            $transaction_approval->U_DocType = 'ERESV';
            $transaction_approval->U_MaxReqr = $find_stage['U_MaxReqr'];
            $transaction_approval->U_MaxRejReqr = $find_stage['U_MaxRejReqr'];
            $transaction_approval->U_RequesterID = $header->Requester;
            $transaction_approval->U_Remarks = $header->Memo;
            $transaction_approval->U_CreatedBy = Auth::user()->U_UserID;
            $transaction_approval->save();

            foreach ($approves['approves'] as $approve) {
                $transaction_details = new TransactionApprovalDetails();
                $transaction_details->U_WddCode = $transaction_approval->U_WddCode;
                $transaction_details->U_SortID = $approve->U_SortId;
                $transaction_details->U_StepCode = $find_stage['U_WstCode'];
                $transaction_details->U_UserID = $approve->U_UserID;
                $transaction_details->U_Status = $header->ApprovalStatus;
                $transaction_details->U_Remarks = $header->Memo;
                $transaction_details->U_UserSign = $header->Requester;
                $transaction_details->U_CreateDate = Carbon::now();
                $transaction_details->save();
            }
        }

        return $transaction_approval;
    }

    /**
     * @param $approves
     * @param $data_header
     * @param $requester
     */
    protected function sendNotification($approves, $data_header, $requester)
    {
        foreach ($approves as $approve) {
            $data_notification = [
                'U_DocNum' => $data_header->U_DocEntry,
                'U_IsRead' => 'N',
                'U_Sender' => $requester->U_UserID,
                'U_Receiver' => $approve->U_UserID,
                'U_Desc' => 'Document Number ' . $data_header->DocNum . ' Need for approval!',
                'U_CreatedAt' => Carbon::now(),
                'U_DocType' => 'ERESV',
            ];
            $this->createNotification($data_notification);
        }
    }

    /**
     * @param array $data
     */
    protected function createNotification(array $data)
    {
        UserNotification::insert($data);
    }
}
