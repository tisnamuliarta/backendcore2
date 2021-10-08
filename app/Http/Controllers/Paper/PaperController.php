<?php

namespace App\Http\Controllers\Paper;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\MasterPaper;
use App\Models\Paper;
use App\Models\PaperDetails;
use App\Models\ViewEmployee;
use App\Traits\CherryApproval;
use App\Traits\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

class PaperController extends Controller
{
    use CherryApproval, RolePermission;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $options = json_decode($request->options);
        $item = json_decode($request->item);
        $pages = isset($options->page) ? (int)$options->page : 1;
        $clinic = isset($request->clinic) ? $request->clinic : 'N';
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : 'papers.paper_date';
        $order = isset($options->sortDesc[0]) ? (string)$options->sortDesc[0] : 'desc';

        $user_id = isset($request->user_id) ? (string)$request->user_id : '';
        $search_item = isset($request->searchItem) ? (string)$request->searchItem : '';
        $search = isset($request->search) ? (string)$request->search : '';
        $type = isset($request->type) ? (string)$request->type : '';
        $offset = ($pages - 1) * $row_data;
        $status = isset($request->status) ? (string)$request->status : 'active';

        if ($status == 'All') {
            $status = '';
        }

        $result = [];
        $query = Paper::select(
            'papers.*',
            'B.name as paper_name',
            'B.alias',
        )
            ->leftJoin('master_papers as B', 'b.id', 'papers.master_paper_id')
            ->where('papers.deleted', '=', 'N')
            ->orderBY($sorts, $order);

        if ($clinic == 'Y') {
            $query = $query->whereIn('B.alias', ['srm', 'srk'])
                ->where('papers.status', '=', 'active');
        } else {
            $query = $query->where('B.alias', 'LIKE', '%' . $type . '%')
                ->where('papers.status', 'LIKE', '%' . $status . '%');
        }

        if ($type == 'stkpd') {
            if (!$request->user()->hasAnyRole(['Superuser', 'HRD Jakarta'])) {
                $query = $query->where("papers.user_id", '=', $user_id);
            }
        } else {
            if (!$request->user()->hasAnyRole(['HRD Morowali', 'Superuser', 'Admin Klinik'])) {
                $query = $query->where("papers.user_id", '=', $user_id);
            }
        }

        if ($search_item == 'Employee') {
            $query = $query->where('papers.user_name', 'LIKE', '%' . $search . '%');
        } elseif ($search_item == 'Paper No') {
            $query = $query->where('papers.paper_no', 'LIKE', '%' . $search . '%');
        } elseif ($search_item == 'Created By') {
            $query = $query->where('papers.created_name', 'LIKE', '%' . $search . '%');
        }

        $default_form = Paper::where('id', '=', 1)->first();

        $result['total'] = $query->count();

        $all_data = $query->offset($offset)
            ->limit($row_data)
            ->get();

        $document_status = Paper::select('status')->distinct()->get();
        $complete_status = Paper::select('is_complete')->distinct()->get();
        $filter_status = ['All'];
        $filter_complete = ['All'];

        foreach ($document_status as $value) {
            $filter_status[] = $value->status;
        }

        foreach ($complete_status as $value) {
            if ($value->is_complete == 'Y') {
                $filter_complete[] = 'finish';
            }

            if ($value->is_complete == 'N') {
                $filter_complete[] = 'pending';
            }
        }

        $company = ViewEmployee::distinct()
            ->pluck('Company');

        $resv_for = [
            'Own Purpose',
            'Subordinate',
            'Superior'
        ];

        $cost_cover = [
            'IMIP',
            'Guest Company',
            'Contractor'
        ];

        $travel_purpose = [
            'Duty Travel',
            'Family Visit/Yearly Leave',
            'Special Permit',
            'Others Purpose'
        ];

        $country = Storage::disk('local')->get('Data/country.json');
        $collection = collect(json_decode($country, true));
        $plucked = $collection->pluck('name');

        $master_paper = $this->getMasterPaper($request->type);
        $paper_no = (isset($request->type)) ? $this->generateDocNum(date('Y-m-d'), $request->type, $master_paper->id) : '';

        $result = array_merge($result, [
            'rows' => $all_data,
            'filter' => ['Paper No', 'Employee', 'Created By'],
            'document_status' => $filter_status,
            'filter_complete' => $filter_complete,
            'form' => $default_form,
            'str_url' => Str::random(40),
            'resv_for' => $resv_for,
            'travel_purpose' => $travel_purpose,
            'cost_cover' => $cost_cover,
            'country' => $plucked->all(),
            'detail' => [],
            'company' => $company,
            'paper_no' => $paper_no
        ]);

        return response()->json($result);
    }

    /**
     * @param $request
     *
     * @return mixed
     */
    protected function checkPaperBaseIdCardAlias($request)
    {
        $master_paper = $this->getMasterPaper($request->alias);
        return Paper::where('id_card', '=', $request->form['id_card'])
            ->where('master_paper_id', '=', $master_paper->id)
            ->where('status', '<>', 'canceled')
            ->first();
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
        DB::beginTransaction();
        try {
            if ($request->alias == 'stkpd') {
                $check_paper = $this->checkPaperBaseIdCardAlias($request);
                if ($check_paper) {
                    return $this->error('Form Surat Ini Sudah Pernah Dibuatkan
                        Untuk Karyawan Yang Bersangkutan, Silahkan Cek Kembali!', 422);
                }
            }

            if (empty($request->form['paper_date'])) {
                return $this->error('Tanggal Surat Tidak Boleh Kosong!', 422);
            }

            $alias = $request->alias;

            if ($alias == 'sim' || $alias == 'sik' || $alias == 'srm' || $alias == 'srk') {
                if (str_contains($request->form['work_location'], 'MOROWALI')) {
                    if ($request->form['for_self'] == 'Karyawan') {
                        if (empty($request->form['leave_from_to']) || empty($request->form['reference_number'])) {
                            return $this->error('Tanggal Cuti dan Nomor Cuti tidak boleh kosong!', 422);
                        }
                    }
                }
            }

            if ($alias == 'srm' || $alias == 'srk') {
                if (empty($request->form['swab_date'])) {
                    return $this->error('Tanggal Swab tidak boleh kosong!', 422);
                }
                if (!empty($request->form['swab_date'])) {
                    if (date('Y-m-d', strtotime($request->form['swab_date']))
                        <= date('Y-m-d', strtotime(Carbon::now()))) {
                        if (empty($request->form['reason_swab'])) {
                            return $this->error('Alasan tanggal Swab tidak boleh kosong!');
                        }
                    }
                }
            }

            $paper = new Paper();
            $paper = $this->saveData($paper, $request, 'post');

            $paper = Paper::leftJoin('master_papers', 'papers.master_paper_id', 'master_papers.id')
                ->select('papers.*', 'master_papers.name as paper_type', 'master_papers.alias as paper_alias')
                ->where('papers.id', '=', $paper->id)
                ->first();

            $this->checkAttachment($paper);

            if ($paper->paper_alias != 'stkpd') {
                if ($request->details) {
                    foreach ($request->details as $detail) {
                        $dataDetail = new PaperDetails();
                        $this->saveDetails($detail, $dataDetail, 'create', $paper->id);
                    }
                }
                if ($request->form['for_self'] == 'Karyawan') {
                    $response_approval = $this->submitPaperApproval($paper, $request);
                } else {
                    $response_approval = [
                        'error' => false,
                        'message' => 'Data saved!'
                    ];
                }

                if ($response_approval['error']) {
                    return $this->error($response_approval['message']);
                } else {
                    DB::commit();
                    return $this->success([], $response_approval['message']);
                }
            } else {
                return $this->success([], 'Data saved!');
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage(), '422', [
                'trace' => $exception->getTrace(),
            ]);
        }
    }

    /**
     * @param $paper
     */
    protected function checkAttachment($paper)
    {
        $attachment = Attachment::where('str_url', '=', $paper->str_url);
        $count = $attachment->count();
        if ($count > 0) {
            $attachment->update([
                'source_id' => $paper->id
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $paper = Paper::where('papers.id', '=', $id)
            ->select('papers.*', 'B.name as flight_origin', 'C.name as flight_destination')
            ->leftJoin('airports as B', 'papers.flight_origin', 'B.id')
            ->leftJoin('airports as C', 'papers.flight_destination', 'C.id')
            ->first();
        return response()->json([
            'form' => $paper,
            'detail' => PaperDetails::where('paper_id', $id)->get()
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
        DB::beginTransaction();
        try {
            $paper = Paper::where('id', '=', $id)->first();
            $this->saveData($paper, $request, 'update');

            if (array_key_exists('details', $request->form)) {
                foreach ($request->form['details'] as $detail) {
                    $dataDetail = PaperDetails::where('paper_id', '=', $id)->first();
                    $this->saveDetails($detail, $dataDetail, 'create', $paper->id);
                }
            }

            if ($request->form['status'] == 'canceled') {
                $cherry_token = $request->user()->cherry_token;

                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ];

                $documents = Http::withHeaders($headers)
                    ->post(env('CHERRY_REQ'), [
                        'CommandName' => 'GetList',
                        'ModelCode' => 'GADocuments',
                        'UserName' => $request->user()->username,
                        'Token' => $cherry_token,
                        'ParameterData' => [
                            [
                                'ParamKey' => 'DocumentReferenceID',
                                'ParamValue' => $request->form['paper_no'],
                                'Operator' => 'eq'
                            ]
                        ]
                    ]);

                $collect = $documents->collect();

                Http::post(env('CHERRY_REQ'), [
                    'CommandName' => 'Remove',
                    'ModelCode' => 'GADocuments',
                    'UserName' => $request->user()->username,
                    'Token' => $cherry_token,
                    'ModelData' => $collect['Data'][0]
                ]);
            }

            DB::commit();
            return $this->success('Data Saved');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage(), '422', [
                'trace' => $exception->getTrace(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }

    /**
     * @param $path
     */
    protected function generatePath($path)
    {
        if (!file_exists($path)) {
            if (!mkdir($path, 0777, true) && !is_dir($path)) {
                throw new \RuntimeException(
                    sprintf(
                        'Directory "%s" was not created',
                        $path
                    )
                );
            }
        }
    }

    /**
     * @param Request $request
     *
     * @return string
     *
     * @throws \PhpOffice\PhpWord\Exception\CopyFileException
     * @throws \PhpOffice\PhpWord\Exception\CreateTemporaryFileException
     */
    public function print(Request $request)
    {
        try {
            setlocale(LC_TIME, 'id_ID');
            Carbon::setLocale('id');

            $item = json_decode($request->item);
            $paper = Paper::where('id', '=', $item->id)->first();

            $direct_superior = ViewEmployee::where('Nik', '=', $paper->id_card)->first();

            $file_export_name = $paper->str_url;

            $qr_file = env('FRONT_URL') . '/verification?str=' . $file_export_name;

            $this->generatePath(public_path() . '/images/qrcode/');

            $qr_code = QrCode::size(300)
                ->format('png')
                ->errorCorrection('H')
                ->merge('/public/images/logo2.png', .3)
                ->generate($qr_file, public_path('images/qrcode/' . $file_export_name . '.png'));

            $this->generatePath(public_path() . '/paper/');

            $file_path_name = base_path(
                'public/paper/' . $file_export_name . '.docx'
            );
            if ($item->alias === 'sim') {
                $letter_template = new TemplateProcessor(public_path('template/paper/SIM.docx'));
            } elseif ($item->alias === 'sik') {
                $letter_template = new TemplateProcessor(public_path('template/paper/SIK.docx'));
            } elseif ($item->alias === 'srm') {
                $letter_template = new TemplateProcessor(public_path('template/paper/SRM.docx'));
            } elseif ($item->alias === 'srk') {
                $letter_template = new TemplateProcessor(public_path('template/paper/SRK.docx'));
            } elseif ($item->alias === 'stkpd') {
                $letter_template = new TemplateProcessor(public_path('template/paper/STK.docx'));
            } elseif ($item->alias === 'smt') {
                $letter_template = new TemplateProcessor(public_path('template/paper/SMT.docx'));
            } elseif ($item->alias === 'fsr') {
                $letter_template = new TemplateProcessor(public_path('template/paper/FSR.docx'));
            } elseif ($item->alias === 'gsv') {
                $letter_template = new TemplateProcessor(public_path('template/paper/GSV.docx'));
            }

            $letter_template->setImageValue(
                'QRCODE',
                public_path('images/qrcode/' . $file_export_name . '.png')
            );
            $letter_template->setImageValue(
                'QRCODE1',
                array(
                    'path' => public_path('images/qrcode/' . $file_export_name . '.png'),
                    'width' => 80, 'height' => 80, 'ratio' => false
                )
            );

            $department = str_replace('&', '&amp;', $paper->department);
            $occupation = str_replace('&', '&amp;', $paper->occupation);

            $letter_template->setValue('REQUESTER', $paper->user_name);
            $letter_template->setValue('NAME', $paper->user_name);
            $letter_template->setValue('ID_CARD', $paper->id_card);
            $letter_template->setValue('EMAIL', $paper->email);
            $letter_template->setValue('NIP', $paper->id_card);
            $letter_template->setValue('NIK', $paper->id_card);
            $letter_template->setValue('KTP', $paper->ktp);
            $letter_template->setValue('OCCUPATION', $occupation);
            $letter_template->setValue('POSITION', $occupation);
            $letter_template->setValue('DIVISION_AND_OCCUPATION', ($department . ' / ' . $occupation));
            $letter_template->setValue('DEP_AND_COMP', ($department . ' / ' . $paper->company));
            $letter_template->setValue('COMPANY', $paper->company);
            $letter_template->setValue('NO_HP', $paper->no_hp);
            $letter_template->setValue('ADDRESS', $paper->address);
            $letter_template->setValue('PERIOD_STAY', $paper->period_stay);
            $letter_template->setValue('REASON', $paper->reason);
            $letter_template->setValue('DATE_OUT', $paper->date_out);
            $letter_template->setValue('DESTINATION', $paper->destination);
            $letter_template->setValue('FULL_ADDRESS', $paper->address);
            $letter_template->setValue('DATE_IN', $paper->date_in);
            $letter_template->setValue('TRANSPORTATION', $paper->transportation);
            $letter_template->setValue('JOURNEY', $paper->route);
            $letter_template->setValue('PAPER_NO', $paper->paper_no);
            $letter_template->setValue('EMP', $this->signatureName($paper->user_name));
            $letter_template->setValue('EMP_NAME', $paper->user_name);
            $letter_template->setValue('BOS', $this->signatureName($direct_superior->DirectSuperiorName));
            $letter_template->setValue('BOS_NAME', $direct_superior->DirectSuperiorName);
            $letter_template->setValue(
                'PAPER_DATE',
                Carbon::parse($paper->paper_date)
                    ->locale('id')
                    ->isoFormat('D MMMM Y')
            );
            $letter_template->setValue('OTHER_PURPOSE', $paper->reason_purpose);
            $letter_template->setValue('SA', $paper->notes);
            $letter_template->setValue('NOTE', $paper->notes);
            $letter_template->setValue('SEAT', $paper->total_seat);
            $letter_template->setValue('REQ_DATE', $paper->request_date);
            $letter_template->setValue('FLIGHT_ORIGIN', $paper->flight_origin);
            $letter_template->setValue('FLIGHT_DESTINATION', $paper->flight_destination);
            $letter_template->setValue('FLIGHT_DAY', Carbon::parse($paper->request_date)->format('N'));
            $letter_template->setValue('NAME_BOSS', $paper->name_boss);
            $letter_template->setValue('BOSS', $this->signatureName($paper->name_boss));
            $letter_template->setValue('POSITION_BOSS', $paper->position_boss);
            $letter_template->setValue('NIK_BOS', $paper->nik_boss);
            $letter_template->setValue('SIGN', 'Zulkifli Arman');
            $letter_template->setImageValue('PAY1', [
                'path' => $paper->payment === 'Dibayar Tunai' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 27, 'height' => 27,
            ]);

            $letter_template->setImageValue('C1', [
                'path' => $paper->resv_for === 'Own Purpose' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C2', [
                'path' => $paper->resv_for === 'Subordinate' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C3', [
                'path' => $paper->resv_for === 'Superior' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C4', [
                'path' => $paper->travel_purpose === 'Duty Travel' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C5', [
                'path' => $paper->travel_purpose === 'Family Visit/Yearly Leave' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C6', [
                'path' => $paper->travel_purpose === 'Special Permit' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C7', [
                'path' => $paper->travel_purpose === 'Others Purpose' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C8', [
                'path' => $paper->cost_cover === 'IMIP' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C9', [
                'path' => $paper->cost_cover === 'Guest Company' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('C10', [
                'path' => $paper->cost_cover === 'Contractor' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 23, 'height' => 23,
            ]);

            $letter_template->setImageValue('PAY2', [
                'path' => $paper->payment !== 'Dibayar Tunai' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 27, 'height' => 27,
            ]);


            $details = PaperDetails::where('paper_id', '=', $paper->id)->get();
            if (count($details) > 0) {
                $data_detail = [];
                foreach ($details as $index => $detail) {
                    $data_detail[] = [
                        'NO' => ($index + 1),
                        'PASSENGER_NAME' => $detail->name,
                        'NATIONALITY' => $detail->nationality,
                        'ID_CARD_NO' => $detail->id_card,
                        'EMPLOYEE_TYPE' => $detail->employee_type,
                        'COMPANY_NAME' => $detail->company,
                    ];
                }
                $letter_template->cloneRowAndSetValues('NO', $data_detail);
            }

            $letter_template->saveAs($file_path_name);

            // convert to pdf
            $pdf_path_name = public_path('documents/');
            if (!file_exists($pdf_path_name)) {
                if (!mkdir($pdf_path_name, 0777, true) && !is_dir($pdf_path_name)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Directory "%s" was not created',
                            $pdf_path_name
                        )
                    );
                }
            }

            $pdf_file_name = $pdf_path_name . $file_export_name . '.pdf';

            // Create PDF File
            $this->createPdfFile($pdf_file_name, $file_path_name);

            $headers = [
                'Content-Type' => 'application/pdf',
            ];

            // Update print document date
            $paper->print_date = Carbon::now();
            $paper->save();

            unlink(public_path('images/qrcode/' . $file_export_name . '.png'));

            if ($item->alias === 'stkpd') {
                $pdfMerger = PDFMerger::init(); //Initialize the merger
                $pdfMerger->addPDF($pdf_file_name, 'all');
                $pdfMerger->addPDF(public_path('template/IMIP_IOMKI.pdf'), 'all');
                $pdfMerger->merge(); //For a normal merge (No blank page added)
                $pdfMerger->save($pdf_file_name);
            }

            return response()->download($pdf_file_name, $file_export_name . '.pdf', $headers);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422, [
                'trace' => $exception->getTrace()
            ]);
        }
    }

    /**
     * @param $name
     * @return string
     */
    protected function signatureName($name)
    {
        $without_space = str_replace(' ', '', $name);
        $limit = substr($without_space, 0, 10);
        return ucwords(strtolower($limit));
    }

    /**
     * @param $paper
     * @param $request
     * @param $type
     *
     * @return mixed
     */
    protected function saveData($paper, $request, $type)
    {
        $for_paper = array_key_exists('for_self', $request->form) ? $request->form['for_self'] : null;

        $master_paper = $this->getMasterPaper($request->alias);
        $username = $request->username;

        if ($type === 'post') {
            $paper->paper_no = $this->generateDocNum(date('Y-m-d'), $request->alias, $master_paper->id);
            $paper->str_url = array_key_exists('str_url', $request->form) ? $request->form['str_url'] : Str::random(40);
        }

        $paper->master_paper_id = $master_paper->id;
        $paper->user_id = $username;
        $paper->created_name = $request->created_name;
        $paper->status = array_key_exists('status', $request->form) ? $request->form['status'] : 'pending';
        $paper->leave_from_to = array_key_exists('leave_from_to', $request->form)
            ? $request->form['leave_from_to'] : null;
        $paper->reference_number = array_key_exists('reference_number', $request->form)
            ? $request->form['reference_number'] : null;
        $paper->user_name = array_key_exists('user_name', $request->form) ? $request->form['user_name'] : null;
        $paper->address = array_key_exists('address', $request->form) ? $request->form['address'] : null;
        $paper->no_hp = array_key_exists('no_hp', $request->form) ? $request->form['no_hp'] : null;
        $paper->ktp = array_key_exists('ktp', $request->form) ? $request->form['ktp'] : null;
        $paper->id_card = array_key_exists('id_card', $request->form) ? $request->form['id_card'] : null;
        $paper->department = array_key_exists('department', $request->form) ? $request->form['department'] : null;
        $paper->company = array_key_exists('company', $request->form) ? $request->form['company'] : null;
        $paper->payment = array_key_exists('payment', $request->form) ? $request->form['payment'] : null;
        $paper->company = array_key_exists('company', $request->form) ? $request->form['company'] : null;
        $paper->occupation = array_key_exists('occupation', $request->form) ? $request->form['occupation'] : null;
        $paper->reason = array_key_exists('reason', $request->form) ? $request->form['reason'] : null;
        $paper->date_out = array_key_exists('date_out', $request->form) ? $request->form['date_out'] : null;
        $paper->date_in = array_key_exists('date_in', $request->form) ? $request->form['date_in'] : null;
        $paper->period_stay = array_key_exists('period_stay', $request->form) ? $request->form['period_stay'] : null;
        $paper->destination = array_key_exists('destination', $request->form) ? $request->form['destination'] : null;
        $paper->transportation = array_key_exists('transportation', $request->form) ?
            $request->form['transportation'] : null;
        $paper->route = array_key_exists('route', $request->form) ? $request->form['route'] : null;
        $paper->name_boss = array_key_exists('name_boss', $request->form) ? $request->form['name_boss'] : null;
        $paper->position_boss = array_key_exists('position_boss', $request->form) ?
            $request->form['position_boss'] : null;
        $paper->nik_boss = array_key_exists('nik_boss', $request->form) ? $request->form['nik_boss'] : null;
        $paper->for_self = $for_paper;
        $paper->created_by = $username;
        $paper->paper_date = array_key_exists('paper_date', $request->form) ? $request->form['paper_date'] : null;
        $paper->swab_date = array_key_exists('swab_date', $request->form) ? $request->form['swab_date'] : null;
        $paper->reason_swab = array_key_exists('reason_swab', $request->form) ? $request->form['reason_swab'] : null;
        $paper->is_complete = array_key_exists('is_complete', $request->form) ? $request->form['is_complete'] : 'N';
        $paper->resv_for = array_key_exists('resv_for', $request->form) ? $request->form['resv_for'] : null;
        $paper->resv_for = array_key_exists('resv_for', $request->form) ? $request->form['resv_for'] : null;
        $paper->travel_purpose = array_key_exists('travel_purpose', $request->form) ? $request->form['travel_purpose'] : null;
        $paper->reason_purpose = array_key_exists('reason_purpose', $request->form) ? $request->form['reason_purpose'] : null;
        $paper->cost_cover = array_key_exists('cost_cover', $request->form) ? $request->form['cost_cover'] : null;
        $paper->work_location = array_key_exists('work_location', $request->form) ? $request->form['work_location'] : null;
        $paper->total_seat = array_key_exists('total_seat', $request->form) ? $request->form['total_seat'] : null;
        $paper->request_date = array_key_exists('request_date', $request->form) ? $request->form['request_date'] : null;
        $paper->flight_origin = array_key_exists('flight_origin', $request->form) ? $request->form['flight_origin'] : null;
        $paper->flight_destination = array_key_exists('flight_destination', $request->form) ? $request->form['flight_destination'] : null;
        $paper->notes = array_key_exists('notes', $request->form) ? $request->form['notes'] : null;
        $paper->email = array_key_exists('email', $request->form) ? $request->form['email'] : null;
        $paper->flight_no = array_key_exists('flight_no', $request->form) ? $request->form['flight_no'] : null;
        $paper->host_company = array_key_exists('host_company', $request->form) ? $request->form['host_company'] : null;
        $paper->visitor_company = array_key_exists('visitor_company', $request->form) ? $request->form['visitor_company'] : null;
        $paper->company_officer = array_key_exists('company_officer', $request->form) ? $request->form['company_officer'] : null;
        $paper->visitor_officer = array_key_exists('visitor_officer', $request->form) ? $request->form['visitor_officer'] : null;
        $paper->visitor_address = array_key_exists('visitor_address', $request->form) ? $request->form['visitor_address'] : null;
        $paper->company_email = array_key_exists('company_email', $request->form) ? $request->form['company_email'] : null;
        $paper->visitor_email = array_key_exists('visitor_email', $request->form) ? $request->form['visitor_email'] : null;
        $paper->plan_visit_area = array_key_exists('plan_visit_area', $request->form) ? $request->form['plan_visit_area'] : null;
        $paper->purpose_visit = array_key_exists('purpose_visit', $request->form) ? $request->form['purpose_visit'] : null;
        $paper->total_guest = array_key_exists('total_guest', $request->form) ? $request->form['total_guest'] : null;
        $paper->facilities = array_key_exists('facilities', $request->form) ? $request->form['facilities'] : null;
        $paper->paper_place = array_key_exists('paper_place', $request->form) ? $request->form['paper_place'] : null;
        $paper->save();

        return $paper;
    }

    /**
     * @param $detail
     * @param $paper
     * @param $status
     *
     * @return mixed
     */
    protected function saveDetails($detail, $paper, $status, $paper_id)
    {
        $paper->name_title = array_key_exists('name_title', $detail) ? $detail['name_title'] : null;
        $paper->name = array_key_exists('name', $detail) ? $detail['name'] : null;
        $paper->position = array_key_exists('position', $detail) ? $detail['position'] : null;
        $paper->body_weight = array_key_exists('body_weight', $detail) ? $detail['body_weight'] : null;
        $paper->departing_city = array_key_exists('departing_city', $detail) ? $detail['departing_city'] : null;
        $paper->arrival_date = array_key_exists('arrival_date', $detail) ? $detail['arrival_date'] : null;
        $paper->arrival_flight_no = array_key_exists('arrival_flight_no', $detail) ? $detail['arrival_flight_no'] : null;
        $paper->arrival_time = array_key_exists('arrival_time', $detail) ? $detail['arrival_time'] : null;
        $paper->departure_date = array_key_exists('departure_date', $detail) ? $detail['departure_date'] : null;
        $paper->departure_flight_no = array_key_exists('departure_flight_no', $detail) ? $detail['departure_flight_no'] : null;
        $paper->departure_time = array_key_exists('departure_time', $detail) ? $detail['departure_time'] : null;
        $paper->destination_city = array_key_exists('destination_city', $detail) ? $detail['destination_city'] : null;
        $paper->transport_to = array_key_exists('transport_to', $detail) ? $detail['transport_to'] : null;
        $paper->transport_from = array_key_exists('transport_from', $detail) ? $detail['transport_from'] : null;
        $paper->notes = array_key_exists('notes', $detail) ? $detail['notes'] : null;
        $paper->nationality = array_key_exists('nationality', $detail) ? $detail['nationality'] : null;
        $paper->id_card = array_key_exists('id_card', $detail) ? $detail['id_card'] : null;
        $paper->employee_type = array_key_exists('employee_type', $detail) ? $detail['employee_type'] : null;
        $paper->company = array_key_exists('company', $detail) ? $detail['company'] : null;
        $paper->seat_no = array_key_exists('seat_no', $detail) ? $detail['seat_no'] : null;

        if ($status == 'create') {
            $paper->paper_id = $paper_id;
            $paper->created_by = auth()->user()->id;
        } else {
            $paper->updated_by = auth()->user()->id;
        }
        return $paper->save();
    }

    /**
     * @param $sysDate
     * @param $alias
     * @param $master_paper_id
     *
     * @return string
     */
    protected function generateDocNum($sysDate, $alias, $master_paper_id): string
    {
        $data_date = strtotime($sysDate);
        $year_val = date('y', $data_date);
        $full_year = date('Y', $data_date);
        $month = date('m', $data_date);
        $day_val = date('j', $data_date);
        $end_date = date('t', $data_date);

        if ((int)$day_val === 1) {
            return Str::upper($alias) . '/IMIP/' . $full_year . '/' . $month . '/' . sprintf('%05s', '1');
        }
        $first_date = "${full_year}-${month}-01";
        $second_date = "${full_year}-${month}-${end_date}";
        $doc_num = Paper::selectRaw('paper_no')
            ->where('master_paper_id', '=', $master_paper_id)
            ->whereBetween(DB::raw('CONVERT(date, created_at)'), [$first_date, $second_date])
            ->orderBy('paper_no', 'DESC')
            ->first();
        //SIK/IMIP/2021/06/xxxxx
        //STKPD/IMIP/2021/06/xxxxx
        $number = empty($doc_num) ? '0000000000' : $doc_num->paper_no;
        if (Str::upper($alias) == 'STKPD') {
            $clear_doc_num = (int)substr($number, 19, 24);
        } else {
            $clear_doc_num = (int)substr($number, 17, 22);
        }
        $number = $clear_doc_num + 1;
        return Str::upper($alias) . '/IMIP/' . $full_year . '/' . $month . '/' . sprintf('%05s', $number);
    }

    /**
     * @param $alias
     *
     * @return mixed
     */
    protected function getMasterPaper($alias)
    {
        return MasterPaper::where('alias', '=', $alias)->first();
    }

    /**
     * @param $pdf_file_name
     * @param $file_path_name
     */
    protected function createPdfFile($pdf_file_name, $file_path_name)
    {
        $word_file = new \COM('Word.Application') or die('Could not initialise Object.');
        $word_file->Visible = 0;
        $word_file->DisplayAlerts = 0;
        $word_file->Documents->Open($file_path_name);
        $word_file->ActiveDocument->ExportAsFixedFormat(
            $pdf_file_name,
            17,
            false,
            0,
            0,
            0,
            0,
            7,
            true,
            true,
            2,
            true,
            true,
            false
        );
        // quit the Word process
        $word_file->Quit(false);
        // clean up
        unset($word_file);

        unlink($file_path_name);
    }
}
