<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Sheet;

class PaperReportExport implements FromView, WithEvents, ShouldAutoSize
{
    private $paper;
    private $form;

    /**
     * ReportExport constructor.
     * @param $paper
     * @param $form
     */
    public function __construct($paper, $form)
    {
        $this->paper = $paper;
        $this->form = $form;
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
        return view('export.paperRapid', [
            'paper' => $this->paper,
            'form' => $this->form,
        ]);
    }
}
