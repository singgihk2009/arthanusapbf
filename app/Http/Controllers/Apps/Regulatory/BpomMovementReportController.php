<?php

namespace App\Http\Controllers\Apps\Regulatory;

use App\Http\Controllers\Controller;
use App\Services\Regulatory\BpomMovementReportExcelExporter;
use App\Services\Regulatory\BpomMovementReportQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BpomMovementReportController extends Controller
{
    public function __construct(
        private readonly BpomMovementReportQuery $query,
        private readonly BpomMovementReportExcelExporter $exporter,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);

        return Inertia::render('Apps/Regulatory/BpomMovementReports/Index', [
            'filters' => $filters,
            'rows' => $rows->take(100)->values(),
            'summary' => $this->query->validationSummary($rows),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $summary = $this->query->validationSummary($rows);

        abort_if($summary['invalid_rows'] > 0, 422, 'Laporan masih memiliki data wajib yang kosong. Perbaiki data atau gunakan preview untuk melihat error.');

        $path = storage_path('app/'.$this->exporter->filename($filters['type'], $filters['start_date'], $filters['end_date']));
        $this->exporter->export($filters['type'], $rows, $path);

        return response()->download($path, basename($path))->deleteFileAfterSend(true);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:incoming,outgoing'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            'type' => $validated['type'] ?? BpomMovementReportQuery::TYPE_INCOMING,
            'start_date' => $validated['start_date'] ?? now()->startOfMonth()->toDateString(),
            'end_date' => $validated['end_date'] ?? now()->toDateString(),
        ];
    }

    private function rows(array $filters)
    {
        return $filters['type'] === BpomMovementReportQuery::TYPE_OUTGOING
            ? $this->query->outgoing($filters['start_date'], $filters['end_date'])
            : $this->query->incoming($filters['start_date'], $filters['end_date']);
    }
}
