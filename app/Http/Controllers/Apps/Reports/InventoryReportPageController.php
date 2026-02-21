<?php

namespace App\Http\Controllers\Apps\Reports;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class InventoryReportPageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory-reports-access'),
        ];
    }

    public function __invoke(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $reportData = $this->resolveReportData($filters);

        return inertia('Apps/Reports/Inventory/Index', [
            'filters' => $filters,
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('code')->get(),
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('sku')->limit(300)->get(),
            'categories' => DB::table('categories')->select('id', 'name')->orderBy('name')->get(),
            'reportData' => $reportData,
        ]);
    }

    public function exportStockBalanceExcel(Request $request): Response
    {
        $filters = $this->resolveFilters($request);
        $rows = $this->stockBalanceBaseQuery($filters)
            ->limit(5000)
            ->get();

        $tempPath = storage_path('app/stock-balance-report-'.now()->format('YmdHis').'.xlsx');

        $xlsxRows = [
            ['Warehouse', 'Item', 'Kategori', 'SKU', 'On Hand Base', 'Reserved Base', 'Batch', 'Expired Date'],
        ];

        foreach ($rows as $row) {
            $xlsxRows[] = [
                $row->warehouse_name,
                $row->item_name,
                $row->category_name,
                $row->sku,
                (string) $row->on_hand_base,
                (string) $row->reserved_base,
                $row->batch_no,
                $row->expired_date,
            ];
        }

        $this->buildTemplateXlsx($tempPath, $xlsxRows);

        return response()->download($tempPath, 'stock-balance-report-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend(true);
    }

    private function resolveReportData(array $filters): array
    {
        return match ($filters['type']) {
            'stock-card' => $this->stockCardData($filters),
            'expired-soon' => $this->expiredSoonData($filters),
            'minimum-stock-alerts' => $this->minimumStockData($filters),
            default => $this->stockBalanceData($filters),
        };
    }

    private function stockBalanceData(array $filters): array
    {
        $rows = $this->stockBalanceBaseQuery($filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return [
            'title' => 'Stock Balance',
            'rows' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
        ];
    }

    private function stockBalanceBaseQuery(array $filters)
    {
        $sortable = [
            'warehouse' => 'warehouses.name',
            'item' => 'items.name',
            'category' => 'categories.name',
            'sku' => 'items.sku',
            'on_hand_base' => 'stock_balances.on_hand_base',
        ];
        $sortColumn = $sortable[$filters['sort_by']] ?? $sortable['warehouse'];

        return DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->when($filters['warehouse_id'], fn ($q, $v) => $q->where('stock_balances.warehouse_id', $v))
            ->when($filters['item_id'], fn ($q, $v) => $q->where('stock_balances.item_id', $v))
            ->when($filters['category_id'], fn ($q, $v) => $q->where('items.category_id', $v))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $keyword = '%'.$filters['search'].'%';

                $q->where(function ($query) use ($keyword) {
                    $query->where('warehouses.name', 'like', $keyword)
                        ->orWhere('items.name', 'like', $keyword)
                        ->orWhere('items.sku', 'like', $keyword)
                        ->orWhere('categories.name', 'like', $keyword);
                });
            })
            ->select([
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                DB::raw('COALESCE(categories.name, \'-\') as category_name'),
                'items.sku',
                'item_batches.batch_no',
                'item_batches.expired_date',
                'stock_balances.on_hand_base',
                'stock_balances.reserved_base',
            ])
            ->orderBy($sortColumn, $filters['sort_dir'])
            ->orderBy('items.sku');
    }

    private function resolveFilters(Request $request): array
    {
        $type = $request->string('type')->toString() ?: 'stock-balance';
        $allowed = ['stock-balance', 'stock-card', 'expired-soon', 'minimum-stock-alerts'];

        if (! in_array($type, $allowed, true)) {
            $type = 'stock-balance';
        }

        $sortBy = $request->string('sort_by')->toString() ?: 'warehouse';
        if (! in_array($sortBy, ['warehouse', 'item', 'category', 'sku', 'on_hand_base'], true)) {
            $sortBy = 'warehouse';
        }

        $sortDir = strtolower($request->string('sort_dir')->toString() ?: 'asc');
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $perPage = $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        return [
            'type' => $type,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'item_id' => $request->integer('item_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'search' => trim((string) $request->string('search')->toString()),
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'per_page' => $perPage,
            'start_date' => $request->string('start_date')->toString() ?: now()->startOfMonth()->toDateString(),
            'end_date' => $request->string('end_date')->toString() ?: now()->toDateString(),
            'days' => $request->integer('days', 30),
        ];
    }

    private function buildTemplateXlsx(string $path, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';
            foreach ($row as $colIndex => $value) {
                $column = $this->columnLabelFromIndex($colIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$column}".($rowIndex + 1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= "<row r=\"".($rowIndex + 1)."\">{$cellXml}</row>";
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }

    private function columnLabelFromIndex(int $index): string
    {
        $label = '';
        $position = $index + 1;

        while ($position > 0) {
            $modulo = ($position - 1) % 26;
            $label = chr(65 + $modulo).$label;
            $position = intdiv($position - 1, 26);
        }

        return $label;
    }

    private function stockCardData(array $filters): array
    {
        if (! $filters['item_id']) {
            return [
                'title' => 'Stock Card',
                'opening_balance' => 0,
                'closing_balance' => 0,
                'rows' => [],
            ];
        }

        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->endOfDay();

        $opening = (float) StockLedger::query()
            ->where('item_id', $filters['item_id'])
            ->when($filters['warehouse_id'], fn ($q, $warehouseId) => $q->where('warehouse_id', $warehouseId))
            ->where('trx_datetime', '<', $start)
            ->sum('qty_base');

        $movements = StockLedger::query()
            ->join('warehouses', 'warehouses.id', '=', 'stock_ledgers.warehouse_id')
            ->where('item_id', $filters['item_id'])
            ->when($filters['warehouse_id'], fn ($q, $warehouseId) => $q->where('stock_ledgers.warehouse_id', $warehouseId))
            ->whereBetween('trx_datetime', [$start, $end])
            ->orderBy('stock_ledgers.trx_datetime')
            ->orderBy('stock_ledgers.id')
            ->limit(500)
            ->get([
                'stock_ledgers.*',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ]);

        $running = $opening;
        $rows = $movements->map(function (StockLedger $ledger) use (&$running) {
            $running += (float) $ledger->qty_base;

            return [
                'trx_datetime' => optional($ledger->trx_datetime)->format('Y-m-d H:i:s'),
                'warehouse_code' => $ledger->warehouse_code,
                'warehouse_name' => $ledger->warehouse_name,
                'trx_type' => $ledger->trx_type,
                'qty_base' => (float) $ledger->qty_base,
                'running_balance' => $running,
            ];
        });

        return [
            'title' => 'Stock Card',
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'rows' => $rows,
        ];
    }

    private function expiredSoonData(array $filters): array
    {
        $days = max(1, (int) $filters['days']);
        $today = now()->toDateString();
        $limit = now()->addDays($days)->toDateString();

        $rows = DB::table('stock_balances')
            ->join('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('stock_balances.on_hand_base', '>', 0)
            ->whereBetween('item_batches.expired_date', [$today, $limit])
            ->when($filters['warehouse_id'], fn ($q, $v) => $q->where('stock_balances.warehouse_id', $v))
            ->select([
                'warehouses.code as warehouse_code',
                'items.sku',
                'items.name as item_name',
                'item_batches.batch_no',
                'item_batches.expired_date',
                'stock_balances.on_hand_base',
            ])
            ->orderBy('item_batches.expired_date')
            ->limit(300)
            ->get();

        return [
            'title' => 'Expired Soon',
            'rows' => $rows,
        ];
    }

    private function minimumStockData(array $filters): array
    {
        $rows = DB::table('warehouse_item_settings as wis')
            ->join('warehouses as w', 'w.id', '=', 'wis.warehouse_id')
            ->join('items as i', 'i.id', '=', 'wis.item_id')
            ->leftJoin('stock_balances as sb', function ($join) {
                $join->on('sb.warehouse_id', '=', 'wis.warehouse_id')
                    ->on('sb.item_id', '=', 'wis.item_id');
            })
            ->when($filters['warehouse_id'], fn ($q, $v) => $q->where('wis.warehouse_id', $v))
            ->groupBy('w.code', 'i.sku', 'i.name', 'wis.min_stock_base')
            ->havingRaw('COALESCE(SUM(sb.on_hand_base), 0) <= wis.min_stock_base')
            ->selectRaw('w.code as warehouse_code, i.sku, i.name as item_name, wis.min_stock_base, COALESCE(SUM(sb.on_hand_base), 0) as on_hand_base')
            ->orderBy('w.code')
            ->orderBy('i.sku')
            ->limit(300)
            ->get();

        return [
            'title' => 'Minimum Stock Alerts',
            'rows' => $rows,
        ];
    }
}
