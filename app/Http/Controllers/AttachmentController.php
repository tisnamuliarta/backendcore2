<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $attachment = Attachment::where('source_id', '=', $request->source_id);
        return $this->success([
            'rows' => $attachment->get(),
            'total' => $attachment->count()
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file.*' => 'required|mimes:pdf,docx,docx,png,jpg,jpeg|max:8048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), '422');
        }
        try {
            $data_file = $request->file('file');

            $extension = $data_file->getClientOriginalExtension();

            $destination_path = public_path('/attachment/docs');

            if (!file_exists($destination_path)) {
                if (!mkdir($destination_path, 0777, true) && !is_dir($destination_path)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Directory "%s" was not created',
                            $destination_path
                        )
                    );
                }
            }

            $origin_name = $data_file->getClientOriginalName();
            $name_no_ext = strtoupper(Str::slug(pathinfo($origin_name, PATHINFO_FILENAME))) . time();
            $file_name = $name_no_ext . '.' . $extension;
            $data_file->move($destination_path, $file_name);

            $data = [
                'file_name' => $file_name,
                'file_path' => url('/attachment/docs/' . $file_name),
                'source_id' => $request->source_id,
                'created_by' => $request->user()->id,
                'type' => $request->type
            ];

            $attach = Attachment::create($data);
            return $this->success([], 'Document Uploaded!');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), '422');
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        try {
            $attachment = Attachment::where('id', '=', $request->id)
                ->first();

            if ($attachment) {
                $file = '/attachment/docs/' . $attachment->file_name;
                unlink(public_path() . $file);
                Attachment::where('id', '=', $attachment->id)
                    ->delete();

                return $this->success('', 'File deleted!');
            } else {
                return $this->error('File not found', 422);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
