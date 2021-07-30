<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Traits\ConnectHana;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterUserController extends Controller
{
    use ConnectHana;

    /**
     * @param Request $request
     * @return mixed
     */
    public function authUser(Request $request)
    {
        return $request->user();
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $options = json_decode($request->options);
        $year_local = date('Y');
        $pages = isset($options->page) ? (int)$options->page : 1;
        $filter = isset($request->filter) ? (string)$request->filter : $year_local;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "name";
        $order = isset($options->sortDesc[0]) ? "DESC" : "ASC";
        $search = isset($request->search) ? (string)$request->search : "";
        $select_data = isset($request->searchItem) ? (string)$request->searchItem : "name";

        $offset = ($pages - 1) * $row_data;

        $result = array();
        $query = User::select('*', 'id AS Action')
            ->orderBy($sorts, $order);

        $result["total"] = $query->count();
        $all_data = $query->offset($offset)
            ->when($select_data, function ($query) use ($select_data, $search) {
                $data_query = $query;
                switch ($select_data) {
                    case 'Username':
                        $data_query->where('username', 'LIKE', '%' . $search . '%');
                        break;
                    case 'Name':
                        $data_query->where('name', 'LIKE', '%' . $search . '%');
                        break;
                    case 'Department':
                        $data_query->where('department', 'LIKE', '%' . $search . '%');
                        break;
                }
                return $data_query;
            })
            ->limit($row_data)
            ->get();

        $result = array_merge($result, [
            "rows" => $all_data,
            "filter" => ['Username', 'Name', 'Department'],
        ]);
        return response()->json($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($this->validation($request)) {
            return response()->json([
                "errors" => true,
                "validHeader" => true,
                "message" => $this->validation($request)
            ]);
        }

        $form = $request->form;
        $this->uniqueValidation($form);

        try {
            $doc_entry = User::orderBy("user_id", "DESC")->first();
            $doc_entry = ($doc_entry) ? $doc_entry->user_id : 0;

            $data = new User();
            $data->U_SubId = $form['U_SubId'];
            $data->U_UserCode = $form['U_UserCode'];
            $data->U_UserName = $form['U_UserName'];
            $data->U_NIK = $form['U_NIK'];
            $data->U_Role = (is_array($form['role'])) ? $form['role']['U_DocEntry'] : $form['U_Role'];
            $data->U_Division = (is_array($form['division'])) ? $form['division']['U_Name'] : $form['U_Division'];
            $data->U_Department = (is_array($form['department'])) ? $form['department']['U_Name']
                : $form['U_Department'];
            $data->U_IsAdminSubWH = $form['U_IsAdminSubWH'];
            $data->U_IsActive = $form['U_IsActive'];
            $data->U_Email = $form['U_Email'];
            $data->U_Password = bcrypt($form['U_Password']);
            $data->U_CreateDate = Carbon::now();
            $data->user_id = ($doc_entry) ? ($doc_entry + 1) : 1;
            $data->save();

            return response()->json([
                "errors" => false,
                "message" => ($doc_entry != 'null') ? "Data updated!" : "Data inserted!"
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "errors" => true,
                "message" => $exception->getMessage(),
                "Trace" => $exception->getTrace()
            ]);
        }
    }

    /**
     * @param $request
     * @return false|string
     */
    protected function validation($request)
    {
        $user_setting = (isset($request->userSetting)) ? $request->userSetting : false;

        if ($user_setting) {
            $messages = [
                'form.U_UserName' => 'Username Field is required!',
                'form.U_NIK' => 'NIK Field is required!',
            ];

            $validator = Validator::make($request->all(), [
                'form.U_UserName' => 'required',
                'form.U_NIK' => 'required',
            ], $messages);
        } else {
            $messages = [
                'form.U_UserCode' => 'User Code Field is required!',
                'form.U_UserName' => 'Username Field is required!',
                'form.U_NIK' => 'NIK Field is required!',
                'form.division' => 'Division Field is required!',
                'form.department' => 'Department Field is required!',
                'form.U_IsAdminSubWH' => 'Is Admin Field Sub WH is required!',
                'form.U_IsActive' => 'Is Active Field is required!',
                //'form.U_Password' => 'Password Field is required!',
                // 'form.U_Email' => 'Email Field is required!',
            ];

            $validator = Validator::make($request->all(), [
                'form.U_UserCode' => 'required',
                'form.U_UserName' => 'required',
                'form.U_NIK' => 'required',
                'form.division' => 'required',
                'form.department' => 'required',
                'form.U_IsAdminSubWH' => 'required',
                'form.U_IsActive' => 'required',
                //'form.U_Password' => 'required',
                // 'form.U_Email' => 'email',
            ], $messages);
        }


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

    /**
     * @param $form
     * @return array
     */
    protected function uniqueValidation($form): array
    {
        $user_nik = User::where("U_NIK", "=", $form['U_NIK'])
            ->where("U_UserCode", "<>", $form['U_UserCode'])
            ->count();
        if ($user_nik > 0) {
            return [
                "errors" => true,
                "message" => 'User NIK must unique!'
            ];
        }

        $usr_code = User::where("U_UserCode", "=", $form['U_UserCode'])
            ->where("U_NIK", "<>", $form['U_NIK'])
            ->count();

        if ($usr_code > 0) {
            return [
                "errors" => true,
                "message" => 'User Code must unique!'
            ];
        }
        return [
            "errors" => false,
            "message" => ''
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): \Illuminate\Http\JsonResponse
    {
        if (intval($id)) {
            $user = User::where("user_id", "=", $id)->first();
            return response()->json([
                "sub_id" => [
                    "U_UserName" => $user['U_UserName'],
                    "user_id" => $user['user_id'],
                ]
            ]);
        } else {
            return response()->json([
                "sub_id" => [
                    "U_UserName" => null,
                    "user_id" => null,
                ]
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $user_setting = (isset($request->userSetting)) ? $request->userSetting : false;
        $change_password = (isset($request->changePassword)) ? $request->changePassword : false;
        $form = $request->form;

        if (!$change_password) {
            if ($this->validation($request)) {
                return response()->json([
                    "errors" => true,
                    "validHeader" => true,
                    "message" => $this->validation($request)
                ]);
            }

            if (!$user_setting) {
                $this->uniqueValidation($form);
            }
        } else {
            if ($request->form['U_Password'] != $request->form['password_confirmation']) {
                return response()->json([
                    "errors" => true,
                    "validHeader" => true,
                    "message" => "Password and Password Confirmation Not Match!"
                ]);
            }
        }

        try {
            $data = User::where("user_id", "=", $id)->first();
            $data->U_UserCode = (array_key_exists('U_UserCode', $form)) ? $form['U_UserCode'] : $data->U_UserCode;
            $data->U_UserName = (array_key_exists('U_UserName', $form)) ? $form['U_UserName'] : $data->U_UserName;
            $data->U_NIK = array_key_exists('U_NIK', $form) ? $form['U_NIK'] : $data->U_NIK;
            if (array_key_exists('division', $form)) {
                $data->U_Division = (is_array($form['division'])) ? $form['division']['U_Name'] : $form['U_Division'];
            }
            if (array_key_exists('role', $form)) {
                $data->U_Role = (is_array($form['role'])) ? $form['role']['U_DocEntry'] : $form['U_Role'];
            }
            if (array_key_exists('department', $form)) {
                $data->U_Department = (is_array($form['department'])) ? $form['department']['U_Name']
                    : $form['U_Department'];
            }
            $data->U_IsAdminSubWH = array_key_exists('U_IsAdminSubWH', $form)
                ? $form['U_IsAdminSubWH'] : $data->U_IsAdminSubWH;
            $data->U_IsActive = array_key_exists('U_IsActive', $form) ? $form['U_IsActive'] : $data->U_IsActive;
            $data->U_Email = array_key_exists('U_Email', $form) ? $form['U_Email'] : $data->U_Email;
            $data->U_SubId = array_key_exists('U_SubId', $form) ? $form['U_SubId'] : $data->U_SubId;
            $data->U_Password = (array_key_exists('U_Password', $form))
                ? bcrypt($form['U_Password']) : $data->U_Password;
            $data->U_UpdateDate = Carbon::now();
            $data->save();

            return response()->json([
                "errors" => false,
                "message" => "Data updated!"
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                "errors" => true,
                "message" => $exception->getMessage(),
                "Trace" => $exception->getTrace()
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
