<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PeriodReport;
use App\Support\ReportWorkbook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $report = $this->report($request);

        return Inertia::render('reports/Index', [
            'filters' => [
                'from' => $report->from()->toDateString(),
                'to' => $report->to()->toDateString(),
                'days' => $report->days(),
            ],
            'collected' => $report->collected(),
            'operations' => $report->operations(),
            'change' => $report->changes(),
            'receivable' => $report->receivable(),
            'delivery' => $report->deliveryTimes(),
            'perDay' => $report->perDay(),
            'perCourier' => $report->perCourier(),
            'perClient' => $report->perClient(),
            'perMethod' => $report->perMethod(),
            'topProducts' => $report->topProducts(),
        ]);
    }

    /**
     * The same report the page shows, as a spreadsheet. It is built from the same
     * object the page is built from, so the file can never disagree with the screen
     * it was downloaded from.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $workbook = new ReportWorkbook($this->report($request));

        $path = tempnam(sys_get_temp_dir(), 'reporte').'.xlsx';
        $workbook->writeTo($path);

        return response()
            ->download($path, $workbook->filename())
            ->deleteFileAfterSend();
    }

    private function report(Request $request): PeriodReport
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return PeriodReport::between($validated['from'] ?? null, $validated['to'] ?? null);
    }
}
