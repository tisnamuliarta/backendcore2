<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\MasterPaper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterPaperController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $options = json_decode($request->options);
        $year_local = date('Y');
        $pages = isset($options->page) ? (int)$options->page : 1;
        $filter = isset($request->filter) ? (string)$request->filter : $year_local;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 1000;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : 'id';
        $order = isset($options->sortDesc[0]) ? 'DESC' : 'ASC';

        $search = isset($request->q) ? (string)$request->q : '';
        $type = $request->type ?? null;
        $offset = ($pages - 1) * $row_data;

        $result = [];
        $query = MasterPaper::orderBy($sorts, $order)->select('*', 'is_active as status');

        $result['total'] = $query->count();
        $all_data = $query->offset($offset)
            ->limit($row_data)
            ->get();

        return $this->success([
            'rows' => $all_data,
            $result,
            'filter' => ['All'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($this->validation($request)) {
            return $this->error($this->validation($request), '422');
        }

        try {
            $form = $request->form;

            $data = new MasterPaper();
            $data->name = $form['name'];
            $data->is_active = $form['is_active'];
            $data->alias = $form['alias'];
            $data->background = $form['background'];
            $data->created_at = Carbon::now();
            $data->save();

            return $this->success('Data inserted!');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): \Illuminate\Http\JsonResponse
    {
        $data = MasterPaper::where('id', '=', $id)->first();
        return response()->json([
            'rows' => $data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if ($this->validation($request)) {
            return $this->error($this->validation($request), '422');
        }

        try {
            $form = $request->form;

            $data = MasterPaper::where('id', '=', $id)->first();
            $data->name = $form['name'];
            $data->is_active = $form['is_active'];
            $data->alias = $form['alias'];
            $data->background = $form['background'];
            $data->updated_at = Carbon::now();
            $data->save();

            return response()->json([
                'errors' => false,
                'message' => 'Data updated!',
            ]);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), '422');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $details = MasterPaper::where('id', '=', $id)->first();
        if ($details) {
            MasterPaper::where('id', '=', $id)->delete();
            return response()->json([
                'message' => 'Row deleted',
            ]);
        }

        return $this->error('Row not found', '422');
    }

    /**
     * @param $request
     *
     * @return false|string
     */
    protected function validation($request)
    {
        $validator = Validator::make($request->all(), [
            'form.name' => 'required',
            'form.is_active' => 'required',
        ]);

        $string_data = '';
        if ($validator->fails()) {
            foreach (collect($validator->messages()) as $error) {
                foreach ($error as $items) {
                    $string_data .= $items . " \n  ";
                }
            }
            return $string_data;
        }
        return false;
    }
}
