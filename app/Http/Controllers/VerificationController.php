<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    /**
     * @param Request $request
     * @param $str_url
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verification(Request $request, $str_url)
    {
        try {
            $paper = Paper::select(
                'papers.*',
                'B.name as paper_name',
                'B.alias',
                'B.alias as paper_alias',
            )
                ->leftJoin('master_papers as B', 'b.id', 'papers.master_paper_id')
                ->where('papers.deleted', '=', 'N')
                ->where('papers.str_url', '=', $str_url)
                ->first();

            $html = null;
            if ($paper) {
                if ($paper->alias === 'stkpd') {
                    $html = view('validation.form_duty', compact('paper'))->render();
                } else {
                    $html = view('validation.form_other', compact('paper'))->render();
                }
            }

            return response()->json([
                'rows' => $html
            ]);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
