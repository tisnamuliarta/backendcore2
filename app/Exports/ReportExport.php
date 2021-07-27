<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Sheet;

class ReportExport implements FromView
{
    private $result;

    /**
     * ReportExport constructor.
     * @param $result
     */
    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $cellRange = 'A1:W1'; // All headers
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setSize(12);
            },
            BeforeSheet::class => function (BeforeSheet $event) {
                Sheet::macro('styleCells', function (Sheet $sheet, string $cellRange, array $style) {
                    $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($style);
                });
            }
        ];
    }

    /**
     * @return View
     */
    public function view(): View
    {
        return view('report.resv', [
            'summaries' => $this->result['rows'],
        ]);
    }
}
