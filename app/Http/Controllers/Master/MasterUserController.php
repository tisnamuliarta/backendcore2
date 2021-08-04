<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
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
        $this->middleware(['permission:Users-index'])->only(['index', 'show']);
        $this->middleware(['permission:Users-store'])->only('store');
        $this->middleware(['permission:Users-edits'])->only('update');
        $this->middleware(['permission:Users-erase'])->only('destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->user()->hasPermissionTo('Users-index')) {
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
            $query = User::select('users.*', 'users.id AS Action', DB::raw("CONCAT(roles.name, ',') as role_name"))
                ->leftJoin('model_has_roles', 'model_has_roles.model_id', 'users.id')
                ->leftJoin('roles', 'roles.id', 'model_has_roles.role_id')
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

            $divisions = ViewEmployee::select('Department')->distinct()->get();
            $arr_division = [];
            foreach ($divisions as $division) {
                $arr_division[] = [
                    'name' => $division->Department
                ];
            }

            $result = array_merge($result, [
                "rows" => $all_data,
                "filter" => ['Username', 'Name', 'Department'],
                'division'=> $arr_division
            ]);
            return response()->json($result);
        } else {
            return $this->error('Not authorized to access this resources!', 422, [
                "errors" => true
            ]);
        }
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
                'username' => $form['username']['Nik'],
                'is_admin_subwh' => $form['is_admin_subwh'],
            ];

            $user = User::create($data);

            $this->storeUserRole($request, $user);

            $this->storeUserApps($request, $user);

            $this->storeUseDivision($request, $user);

            $this->storeUseWhs($request, $user);

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
        $user_id = $request->user()->id;
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
                'username' => $form['username']
            ];

            User::where("user_id", "=", $id)->update($data);

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
