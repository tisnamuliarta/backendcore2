<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\UserDivision;
use App\Models\ViewEmployee;
use App\Traits\ConnectHana;
use App\Models\User;
use App\Traits\MasterData;
use App\Traits\RolePermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterUserController extends Controller
{
    use ConnectHana;
    use MasterData, RolePermission;

    /**
     * MasterUserController constructor.
     */
    public function __construct()
    {
        $this->middleware(['direct_permission:Users-index'])->only(['index', 'show']);
        $this->middleware(['direct_permission:Users-store'])->only('store');
        $this->middleware(['direct_permission:Users-edits'])->only('update');
        $this->middleware(['direct_permission:Users-erase'])->only('destroy');
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
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "users.name";
        $order = isset($options->sortDesc[0]) ? "DESC" : "ASC";
        $search = isset($request->search) ? (string)$request->search : "";
        $select_data = isset($request->searchItem) ? (string)$request->searchItem : "name";

        $offset = ($pages - 1) * $row_data;

        $result = array();
        $query = User::select('users.*')
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

        $array_user = [];
        foreach ($all_data as $item) {
            $user_roles = User::leftJoin('model_has_roles', 'model_has_roles.model_id', 'users.id')
                ->leftJoin('roles', 'roles.id', 'model_has_roles.role_id')
                ->where('users.id', $item->id)
                ->select('model_has_roles.role_id', 'roles.name')
                ->get();
            $arr_role_name = [];
            $arr_user_role = [];
            foreach ($user_roles as $user_role) {
                $arr_user_role[] = (int)$user_role->role_id;
                $arr_role_name[] = $user_role->name;
            }

            $app_access = User::leftJoin('user_apps', 'user_apps.user_id', 'users.id')
                ->leftJoin('applications', 'user_apps.app_id', 'applications.id')
                ->where('users.id', $item->id)
                ->select('user_apps.app_id')
                ->get();

            $arr_user_app = [];
            foreach ($app_access as $user_app) {
                $arr_user_app[] = (int)$user_app->app_id;
            }

            $divisions = User::leftJoin('user_divisions', 'user_divisions.user_id', 'users.id')
                ->where('users.id', $item->id)
                ->select('user_divisions.division_name')
                ->get();

            $arr_user_division = [];
            foreach ($divisions as $division) {
                $arr_user_division[] = $division->division_name;
            }

            $whs = User::leftJoin('user_whs', 'user_whs.user_id', 'users.id')
                ->where('users.id', $item->id)
                ->select('user_whs.whs_code')
                ->get();
            $arr_user_whs = [];
            foreach ($whs as $itemwhs) {
                $arr_user_whs[] = $itemwhs->whs_code;
            }

            $item_groups = User::leftJoin('user_itm_grps', 'user_itm_grps.user_id', 'users.id')
                ->where('users.id', $item->id)
                ->select('user_itm_grps.item_group')
                ->get();
            $arr_item_group = [];
            foreach ($item_groups as $item_group) {
                $arr_item_group[] = $item_group->item_group;
            }

            $array_user[] = [
                'Action' => $item->Action,
                'active' => $item->active,
                'company' => $item->company,
                'company_code' => $item->company_code,
                'department' => $item->department,
                'email' => $item->email,
                'id' => $item->id,
                'is_admin_subwh' => $item->is_admin_subwh,
                'location' => $item->location,
                'name' => $item->name,
                'position' => $item->position,
                'role_name' => implode(', ', $arr_role_name),
                'username' => $item->username,
                'role' => $arr_user_role,
                'apps' => $arr_user_app,
                'division' => $arr_user_division,
                'item_group' => $arr_item_group,
                'whs' => $arr_user_whs
            ];
        }

        //return response()->json($array_user);
        $divisions = ViewEmployee::select('Department')
            ->where('Company', '=', 'PT IMIP')
            ->orderBy('Department')
            ->distinct()
            ->get();

        $arr_division = [];
        foreach ($divisions as $division) {
            $arr_division[] = [
                'name' => $division->Department
            ];
        }

        $result = array_merge($result, [
            "rows" => $array_user,
            "filter" => ['Username', 'Name', 'Department'],
            'division' => $arr_division,
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
            return $this->error($this->validation($request), 422, [
                "errors" => true
            ]);
        }

        $form = $request->form;
//        $roles = $request->form['role'];
//        foreach ($roles as $role) {
//            return response()->json($role['id']);
//        }
        //return response()->json($form);

        DB::beginTransaction();
        try {
            $data = [
                'username' => $form['username'],
                'is_admin_subwh' => $form['is_admin_subwh'],
                'email' => strtotime(date('Y-m-d H:i:s')) .'@imip.co.id',
                'active' => $form['active'],
            ];

            $user = User::create($data);

            $this->storeUserDetails($request, $user);

            DB::commit();

            return $this->success([
                "errors" => false
            ], 'Data inserted!');
        } catch (\Exception $exception) {
            DB::rollBack();

            return $this->error($exception->getMessage(), 422, [
                "errors" => true,
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
        $messages = [
            'form.username' => 'Username Field is required!',
            'form.apps' => 'Apps Access Field is required!',
            'form.role' => 'Role Field is required!',
            'form.active' => 'Status is required!',
        ];
        $user_id = $request->form['id'];
        $validator = Validator::make($request->all(), [
            'form.username' => 'required|unique:users,username,' . $user_id,
            'form.apps' => 'required',
            'form.role' => 'required',
            'form.active' => 'required',
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
     * @param $request
     * @param $user
     */
    protected function storeUserDetails($request, $user)
    {
        $this->storeUserRole($request, $user);

        $this->storeUserApps($request, $user);

        $this->storeUseDivision($request, $user);

        $this->storeUseWhs($request, $user);

        $this->storeUserItemGroups($request, $user);
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
        if ($this->validation($request)) {
            return $this->error($this->validation($request), 422, [
                "errors" => true
            ]);
        }

        $form = $request->form;

        try {
            $data = [
                'username' => $form['username'],
                'is_admin_subwh' => $form['is_admin_subwh'],
                'active' => $form['active'],
            ];

            User::where("id", "=", $id)->update($data);

            $user = User::find($id);

            $this->storeUserDetails($request, $user);

            return $this->success([
                "errors" => false
            ], 'Data updated!');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422, [
                "errors" => true,
                "Trace" => $exception->getTrace()
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $details = User::where("id", "=", $id)->first();
        if ($details) {
            User::where("id", "=", $id)->delete();
            return $this->success([
                "errors" => false
            ], 'Row deleted!');
        }

        return $this->error('Row not found', 422, [
            "errors" => true
        ]);
    }
}
