<?php

namespace App\Http\Controllers\Export;

use App\Exports\PaperReportExport;
use App\Http\Controllers\Controller;
use App\Models\Paper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportRapid(Request $request)
    {
        try {
            $form = json_decode($request->form);
            $paper = Paper::leftJoin('master_papers', 'master_papers.id', 'papers.master_paper_id')
                ->whereIn('master_papers.alias', ['srm', 'srk'])
                ->where('status', '=', 'active')
                ->whereBetween('papers.swab_date', [$form->date_from, $form->date_to])
                ->get();

            return Excel::download(new PaperReportExport($paper, $form), 'paper.xlsx');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422, [
                'trace' => $exception->getTrace()
            ]);
        }
    }
}
