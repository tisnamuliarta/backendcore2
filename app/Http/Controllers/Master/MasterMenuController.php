<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\UserMenu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterMenuController extends Controller
{
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
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 1000;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : 'created_at';
        $order = isset($options->sortDesc[0]) ? 'DESC' : 'ASC';

        $search = isset($request->q) ? (string)$request->q : '';
        $select_data = isset($request->selectData) ? (string)$request->selectData : 'id';
        $offset = ($pages - 1) * $row_data;
        $username = $request->user()->U_UserCode;

        $result = array();
        $query = Menu::select('menus.*', 'b.menu as ParentName')
            ->leftJoin('menus as B', 'b.parent_id', 'menus.id')
            ->with([
                'parent' => function ($query) {
                    $query->select('id', 'menu');
                }
            ])
            ->orderBy($sorts, $order);

        $result['total'] = $query->count();
        $all_data = $query->offset($offset)
            ->limit($row_data)
            ->get();

        $parent = Menu::select('menus.*')
            ->where('parent_id', '=', '0')
            ->get();

        $result = array_merge($result, [
            'rows' => $all_data,
            'filter' => ['All'],
            'parent' => $parent,
            'menu' => $this->listMenu(),
            'userMenu' => $this->userMenu($request->user()->username),
        ]);
        return response()->json($result);
    }

    /**
     * @return array
     */
    protected function listMenu(): array
    {
        $parents = Menu::where('parent_id', '=', '0')
            ->get();
        $menu_arr = [];
        foreach ($parents as $parent) {
            $children = $this->getListChildMenu($parent['id']);
            $menu_arr[] = [
                'icon' => $parent['icon'],
                'id' => $parent['id'],
                'icon-alt' => $parent['icon_alt'],
                'text' => $parent['menu'],
                'name' => $parent['menu'],
                'model' => false,
                'children' => $children
            ];
        }

        return $menu_arr;
    }

    /**
     * @param $parent_id
     * @return array
     */
    protected function getListChildMenu($parent_id): array
    {
        $menu_arr = [];
        $children = Menu::select('*')
            ->where('parent_id', '=', $parent_id)
            ->get();

        foreach ($children as $child) {
            $menu_arr[] = [
                'icon' => $child['icon'],
                'id' => $child['id'],
                'icon-alt' => $child['icon_alt'],
                'text' => $child['menu'],
                'name' => $child['menu'],
                'parent_id' => $child['parent_id'],
                'route_name' => $child['route_name'],
                'model' => false
            ];
        }
        return $menu_arr;
    }

    /**
     * @param $user_id
     * @return array
     */
    protected function userMenu($user_id): array
    {
        $parents = UserMenu::leftJoin('menus', 'menus.id', 'user_menus.menu_id')
            ->where('menus.parent_id', '=', '0')
            ->where('user_menus.user_id', '=', $user_id)
            ->select('menus.*', 'user_menus.id AS MenuEntry')
            ->get();

        $menu_arr = [];
        foreach ($parents as $parent) {
            $children = $this->getChildMenu($parent['id'], $user_id);
            $menu_arr[] = [
                'icon' => $parent['icon'],
                'id' => $parent['id'],
                'icon-alt' => $parent['icon_alt'],
                'text' => $parent['menu'],
                'name' => $parent['menu'],
                'model' => false,
                'children' => $children
            ];
        }

        return $menu_arr;
    }

    /**
     * @param $parent_id
     * @param $user_id
     *
     * @return array
     */
    protected function getChildMenu($parent_id, $user_id): array
    {
        $menu_arr = [];
        $children = UserMenu::leftJoin('menus', 'menus.id', 'user_menus.menu_id')
            ->where('menus.parent_id', '=', $parent_id)
            ->where('user_menus.user_id', '=', $user_id)
            ->select('menus.*', 'user_menus.id AS MenuEntry')
            ->get();

        foreach ($children as $child) {
            $menu_arr[] = [
                'icon' => $child['icon'],
                'id' => $child['id'],
                'icon-alt' => $child['icon_alt'],
                'text' => $child['menu'],
                'name' => $child['menu'],
                'parent_id' => $child['parent_id'],
                'route_name' => $child['route_name'],
                'model' => false
            ];
        }
        return $menu_arr;
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
                'errors' => true,
                'validHeader' => true,
                'message' => $this->validation($request)
            ]);
        }

        $form = $request->form;
        try {
            $doc_entry = Menu::orderBy('id', 'DESC')->first();
            $parent = Menu::where('id', '=', $form['parent'])->first();

            $data = new Menu();
            $data->menu = $form['menu'];
            $data->order_line = $form['order_line'];
            $data->parent_id = (!empty($form['parent'])) ? $form['parent'] : 0;
            $data->route_name = $form['route_name'];
            $data->icon = $form['icon'];
            $data->icon_alt = $form['icon_alt'];
            $data->created_by = Auth::user()->username;
            $data->created_at = Carbon::now();
            $data->id = ($doc_entry) ? ($doc_entry->id + 1) : 1;
            $data->save();

            return response()->json([
                'errors' => false,
                'message' => ($doc_entry != 'null') ? 'Data updated!' : 'Data inserted!'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'errors' => true,
                'message' => $exception->getMessage(),
                'Trace' => $exception->getTrace()
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
            'form.menu' => 'Name is required!',
            'form.order_line' => 'order_line is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.menu' => 'required',
            'form.order_line' => 'required',
        ], $messages);

        $string_data = '';
        if ($validator->fails()) {
            foreach (collect($validator->messages()) as $error) {
                foreach ($error as $items) {
                    $string_data .= $items . ' \n  ';
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
        $data = Menu::where('id', '=', $id)->get();
        return response()->json([
            'rows' => $data
        ]);
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
            return response()->json([
                'errors' => true,
                'validHeader' => true,
                'message' => $this->validation($request)
            ]);
        }

        $form = $request->form;
        try {
            $data = Menu::where('id', '=', $id)->first();
            $parent = Menu::where('id', '=', $form['parent'])->first();

            $data->menu = $form['menu'];
            $data->order_line = $form['order_line'];
            $data->parent_id = (!empty($form['parent'])) ? $form['parent'] : 0;
            $data->route_name = $form['route_name'];
            $data->icon = $form['icon'];
            $data->icon_alt = $form['icon_alt'];
            $data->updated_by = Auth::user()->username;
            $data->UpdatedAt = Carbon::now();
            $data->save();

            return response()->json([
                'errors' => false,
                'message' => 'Data updated!'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'errors' => true,
                'message' => $exception->getMessage(),
                'Trace' => $exception->getTrace()
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
        $details = Menu::where('id', '=', $id)->first();
        if ($details) {
            Menu::where('id', '=', $id)->delete();
            return response()->json([
                'message' => 'Row deleted'
            ]);
        }
        return response()->json([
            'message' => 'Row not found'
        ]);
    }
}
