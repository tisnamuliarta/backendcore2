<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ListPermission;
use App\Models\Role;
use App\Traits\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class MasterPermissionController extends Controller
{
    use RolePermission;

    /**
     * MasterUserController constructor.
     */
    public function __construct()
    {
        $this->middleware(['permission:Permission-index'])->only(['index', 'show']);
        $this->middleware(['permission:Permission-store'])->only('store');
        $this->middleware(['permission:Permission-edits'])->only('update');
        $this->middleware(['permission:Permission-erase'])->only('destroy');
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
        $pages = isset($options->page) ? (int)$options->page : 1;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "order_line";
        $order = isset($options->sortDesc[0]) ? (string)$options->sortDesc[0] : "asc";
        $offset = ($pages - 1) * $row_data;

        $result = array();
        $query = ListPermission::select('*');

        $result["total"] = $query->count();

        $parents = Permission::where('has_child', 'Y')
            //->whereIsNull('route_name')
            ->select('id', 'menu_name')
            ->get();

        $data_parent = [];
        foreach ($parents as $parent) {
            $data_parent[] = $parent->menu_name;
        }

        $all_data = $query->offset($offset)
            ->orderBy($sorts, $order)
            ->limit($row_data)
            ->get();

        $all_rows = Permission::groupBy(['menu_name'])->select('menu_name')->get();
        $arr_rows = [];
        foreach ($all_rows as $item) {
            $arr_rows[] = $item->menu_name;
        }

        $result = array_merge($result, [
            'rows' => $all_data,
            'simple' => $arr_rows,
            'parent' => $data_parent
        ]);
        return $this->success($result);
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
        DB::beginTransaction();
        try {
            $parent = Permission::where('menu_name', $form['parent_name'])->first();
            $data = [
                'name' => $form['menu_name'],
                'app_name' => $form['app_name'],
                'menu_name' => $form['menu_name'],
                'parent_id' => ($parent) ? $parent->id : 0,
                'icon' => $form['icon'],
                'route_name' => $form['route_name'],
                'has_child' => $form['has_child'],
                'has_route' => $form['has_route'],
                'order_line' => $form['order_line'],
                'is_crud' => $form['is_crud'],
                'role' => $form['role'],
            ];

            if ($form['is_crud'] == 'Y') {
                $this->generatePermission((object)$data, '-index', 'Y');
            } else {
                if (isset($form['index'])) {
                    $this->generatePermission((object)$data, '-index', 'Y');
                }

                if (isset($form['store'])) {
                    $this->generatePermission((object)$data, '-store', 'Y');
                }

                if (isset($form['edits'])) {
                    $this->generatePermission((object)$data, '-edits', 'Y');
                }

                if (isset($form['edits'])) {
                    $this->generatePermission((object)$data, '-erase', 'Y');
                }
            }

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
     *
     * @return false|string
     */
    protected function validation($request)
    {
        $messages = [
            'form.app_name' => 'Application Name is required!',
            'form.menu_name' => 'Menu Name is required!',
            'form.order_line' => 'Order line field is required!',
            'form.role' => 'Role field is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.app_name' => 'required',
            'form.menu_name' => 'required',
            'form.order_line' => 'required',
            'form.role' => 'required',
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
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $menu_name = $request->menu_name;
        $data = DB::select("EXEC sp_single_permission '$menu_name' ");

        return $this->success([
            'rows' => $data[0]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if ($this->validation($request)) {
            return $this->error($this->validation($request), 422, [
                "errors" => true
            ]);
        }

        $form = $request->form;
        try {
            $data = $this->data($form);

            Permission::where("id", "=", $id)->update($data);

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
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $details = Permission::where("id", "=", $id)->first();
        if ($details) {
            Permission::where("id", "=", $id)->delete();
            return $this->success([
                "errors" => false
            ], 'Row deleted!');
        }

        return $this->error('Row not found', 422, [
            "errors" => true
        ]);
    }
}
