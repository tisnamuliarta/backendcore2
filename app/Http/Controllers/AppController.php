<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function frontData()
    {
        $apps = Application::all();
        return $this->success([
            'rows' => $apps,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $options = json_decode($request->options);
        $pages = isset($options->page) ? (int)$options->page : 1;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : "app_name";
        $order = isset($options->sortDesc[0]) ? (string)$options->sortDesc[0] : "desc";
        $offset = ($pages - 1) * $row_data;

        $result = array();
        $query = Application::selectRaw("*, 'actions' as ACTIONS");

        $result["total"] = $query->count();

        $all_data = $query->offset($offset)
            ->orderBy($sorts, $order)
            ->limit($row_data)
            ->get();

        $all_rows = Application::all();
        $arr_rows = [];
        $arr_rows[] = ['name' => 'All'];
        foreach ($all_rows as $item) {
            $arr_rows[] = [
                'name' => $item->app_name,
                'id' => $item->id,
            ];
        }

        $result = array_merge($result, [
            "rows" => $all_data,
            "simple" => $arr_rows
        ]);
        return $this->success($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->validation($request)) {
            return $this->error($this->validation($request), 401, [
                "errors" => true
            ]);
        }

        $form = $request->form;
        try {
            $data = [
                'app_name' => $form['app_name'],
                'app_description' => $form['app_description'],
                'app_url' => $form['app_url'],
                'active' => $form['active'],
            ];
            Application::create($data);

            return $this->success([
                "errors" => false
            ], 'Data inserted!');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 401, [
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
            'form.app_name' => 'Name is required!',
            'form.app_description' => 'Description is required!',
            'form.app_url' => 'URL is required!',
        ];

        $validator = Validator::make($request->all(), [
            'form.app_name' => 'required',
            'form.app_description' => 'required',
            'form.app_url' => 'required',
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
    public function show($id)
    {
        $data = Application::where("id", "=", $id)->get();

        return $this->success([
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
    public function update(Request $request, $id)
    {
        if ($this->validation($request)) {
            return $this->error($this->validation($request), 401, [
                "errors" => true
            ]);
        }

        $form = $request->form;
        try {
            $data = [
                'name' => $form['name'],
                'description' => $form['description'],
            ];

            Application::where("id", "=", $id)->update($data);

            return $this->success([
                "errors" => false
            ], 'Data updated!');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 401, [
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
        $details = Application::where("id", "=", $id)->first();
        if ($details) {
            Application::where("id", "=", $id)->delete();
            return $this->success([
                "errors" => false
            ], 'Row deleted!');
        }

        return $this->error('Row not found', 422, [
            "errors" => true
        ]);
    }
}
