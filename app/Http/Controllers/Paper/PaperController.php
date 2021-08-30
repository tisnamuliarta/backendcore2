<?php

namespace App\Http\Controllers\Paper;

use App\Http\Controllers\Controller;
use App\Models\MasterPaper;
use App\Models\Paper;
use App\Traits\CherryApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

class PaperController extends Controller
{
    use CherryApproval;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $options = json_decode($request->options);
        $item = json_decode($request->item);
        $pages = isset($options->page) ? (int)$options->page : 1;
        $row_data = isset($options->itemsPerPage) ? (int)$options->itemsPerPage : 20;
        $sorts = isset($options->sortBy[0]) ? (string)$options->sortBy[0] : 'papers.paper_no';
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
            ->where('B.alias', 'LIKE', '%' . $type . '%')
            ->where('papers.deleted', '=', 'N')
            ->where('papers.status', 'LIKE', '%' . $status . '%')
            ->orderBY($sorts, $order);

        if ($type != 'stkpd') {
            $query = $query->where("papers.user_id", '=', $user_id);
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
        $filter_status = ['All'];

        foreach ($document_status as $value) {
            $filter_status[] = $value->status;
        }

        $result = array_merge($result, [
            'rows' => $all_data,
            'filter' => ['Paper No', 'Employee', 'Created By'],
            'document_status' => $filter_status,
            'form' => $default_form,
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

            $paper = new Paper();
            $paper = $this->saveData($paper, $request, 'post');

            $paper = Paper::leftJoin('master_papers', 'papers.master_paper_id', 'master_papers.id')
                ->select('papers.*', 'master_papers.name as paper_type', 'master_papers.alias as paper_alias')
                ->where('papers.id', '=', $paper->id)
                ->first();

            $response_approval = $this->submitPaperApproval($paper, $request);

            DB::commit();
            if ($response_approval['error']) {
                return $this->error($response_approval['message']);
            } else {
                return $this->success([], $response_approval['message']);
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage(), '422', [
                'trace' => $exception->getTrace(),
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
        return response()->json([
            'form' => Paper::where('id', '=', $id)->first(),
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

            $file_export_name = $paper->str_url;

            $qr_file = env('APP_URL') . 'validation/' . $file_export_name;

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

            $letter_template->setValue('NAME', $paper->user_name);
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
            $letter_template->setValue(
                'PAPER_DATE',
                Carbon::parse($paper->paper_date)
                    ->locale('id')
                    ->isoFormat('D MMMM Y')
            );
            $letter_template->setValue('NAME_BOSS', $paper->name_boss);
            $letter_template->setValue('POSITION_BOSS', $paper->position_boss);
            $letter_template->setValue('NIK_BOS', $paper->nik_boss);
            $letter_template->setValue('SIGN', 'Zulkifli Arman');
            $letter_template->setImageValue('PAY1', [
                'path' => $paper->payment === 'Dibayar Tunai' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 27, 'height' => 27,
            ]);

            $letter_template->setImageValue('PAY2', [
                'path' => $paper->payment !== 'Dibayar Tunai' ?
                    public_path('images/icons_checked.png') : public_path('images/icons_unchecked.png'),
                'width' => 27, 'height' => 27,
            ]);

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
            return $this->error($exception->getMessage(), 422);
        }
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
            $paper->str_url = Str::random(40);
        }

        $paper->master_paper_id = $master_paper->id;
        $paper->user_id = $username;
        $paper->created_name = $request->created_name;
        $paper->status = array_key_exists('status', $request->form) ? $request->form['status'] : null;
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
        $paper->save();

        return $paper;
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
