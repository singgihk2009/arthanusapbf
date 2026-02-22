<?php

namespace App\Http\Controllers\Apps\Reports;

use App\Http\Controllers\Controller;
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
            'categories' => DB::table('categories')->select('id', 'name')->orderBy('name')->get(),
            'reportData' => $reportData,
        ]);
    }

    public function exportStockBalanceExcel(Request $request): Response
    {
        $filters = $this->resolveFilters($request);
        $rows = $this->baseQuery($filters)->limit(10000)->get();

        $isIncoming = $filters['type'] === 'incoming-items';
        $tempPath = storage_path('app/'.$filters['type'].'-report-'.now()->format('YmdHis').'.xlsx');

        $xlsxRows = [[
            'Warehouse',
            'Tanggal',
            'Referensi',
            'Item',
            'Kategori',
            'SKU',
            'UoM',
            'Unit Price',
            $isIncoming ? 'Qty Masuk' : 'Qty Pemakaian',
            $isIncoming ? 'Value' : 'Valuation Rp',
        ]];

        if ($isIncoming) {
            $xlsxRows[0][] = 'Status';
            $xlsxRows[0][] = 'Vendor';
        }

        foreach ($rows as $row) {
            $line = [
                $row->warehouse_name,
                $row->trx_datetime,
                $row->reference,
                $row->item_name,
                $row->category_name,
                $row->sku,
                $row->uom_name,
                (string) $row->unit_price,
                (string) $row->qty,
                (string) $row->value,
            ];

            if ($isIncoming) {
                $line[] = $row->status;
                $line[] = $row->vendor_name;
            }

            $xlsxRows[] = $line;
        }

        $this->buildTemplateXlsx($tempPath, $xlsxRows);

        return response()->download(
            $tempPath,
            $filters['type'].'-report-'.now()->format('Ymd-His').'.xlsx'
        )->deleteFileAfterSend(true);
    }

    private function resolveFilters(Request $request): array
    {
        $type = $request->string('type')->toString() ?: 'incoming-items';
        $allowed = ['incoming-items', 'item-usage'];

        if (! in_array($type, $allowed, true)) {
            $type = 'incoming-items';
        }

        $sortBy = $request->string('sort_by')->toString() ?: 'trx_datetime';
        if (! in_array($sortBy, ['trx_datetime', 'warehouse', 'item', 'category', 'qty', 'value', 'unit_price', 'status', 'vendor'], true)) {
            $sortBy = 'trx_datetime';
        }

        $sortDir = strtolower($request->string('sort_dir')->toString() ?: 'desc');
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $perPage = $request->integer('per_page', 15);
        if (! in_array($perPage, [15, 50, 100], true)) {
            $perPage = 15;
        }

        return [
            'type' => $type,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'search' => trim((string) $request->string('search')->toString()),
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'per_page' => $perPage,
            'status' => strtolower($request->string('status')->toString() ?: 'all'),
        ];
    }

    private function resolveReportData(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return [
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

    private function baseQuery(array $filters)
    {
        return $filters['type'] === 'incoming-items'
            ? $this->incomingItemsQuery($filters)
            : $this->itemUsageQuery($filters);
    }

    private function incomingItemsQuery(array $filters)
    {
        $sortable = [
            'trx_datetime' => 'receiving_entries.transaction_date',
            'warehouse' => 'warehouses.name',
            'item' => 'items.name',
            'category' => 'categories.name',
            'qty' => 'receiving_entry_lines.qty',
            'value' => 'receiving_entry_lines.value',
            'unit_price' => 'receiving_entry_lines.price',
            'status' => 'receiving_entries.status',
            'vendor' => 'receiving_entries.vendor_name',
        ];

        $sortColumn = $sortable[$filters['sort_by']] ?? $sortable['trx_datetime'];
        $status = in_array($filters['status'], ['posted', 'unposted', 'all'], true) ? $filters['status'] : 'all';

        return DB::table('receiving_entry_lines')
            ->join('receiving_entries', 'receiving_entries.id', '=', 'receiving_entry_lines.receiving_entry_id')
            ->join('warehouses', 'warehouses.id', '=', 'receiving_entries.warehouse_id')
            ->join('items', 'items.id', '=', 'receiving_entry_lines.item_id')
            ->join('uoms', 'uoms.id', '=', 'receiving_entry_lines.uom_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'posted') {
                    $query->where('receiving_entries.status', 'POSTED');

                    return;
                }

                $query->where('receiving_entries.status', '!=', 'POSTED');
            })
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('receiving_entries.warehouse_id', $warehouseId))
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->where('items.category_id', $categoryId))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $keyword = '%'.$filters['search'].'%';
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('warehouses.name', 'like', $keyword)
                        ->orWhere('items.name', 'like', $keyword)
                        ->orWhere('items.sku', 'like', $keyword)
                        ->orWhere('categories.name', 'like', $keyword)
                        ->orWhere('receiving_entries.reference', 'like', $keyword)
                        ->orWhere('receiving_entries.vendor_name', 'like', $keyword)
                        ->orWhere('receiving_entries.number', 'like', $keyword)
                        ->orWhere('uoms.name', 'like', $keyword)
                        ->orWhere('uoms.code', 'like', $keyword);
                });
            })
            ->select([
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                DB::raw('COALESCE(categories.name, \'-\') as category_name'),
                'items.sku',
                DB::raw("DATE_FORMAT(receiving_entries.transaction_date, '%Y-%m-%d') as trx_datetime"),
                DB::raw("COALESCE(receiving_entries.reference, receiving_entries.number) as reference"),
                DB::raw('COALESCE(uoms.code, uoms.name) as uom_name'),
                DB::raw('COALESCE(receiving_entry_lines.price, 0) as unit_price'),
                DB::raw('ABS(receiving_entry_lines.qty) as qty'),
                DB::raw('ABS(COALESCE(receiving_entry_lines.value, receiving_entry_lines.qty * COALESCE(receiving_entry_lines.price, 0))) as value'),
                DB::raw("COALESCE(receiving_entries.status, 'DRAFT') as status"),
                DB::raw("COALESCE(receiving_entries.vendor_name, '-') as vendor_name"),
            ])
            ->orderBy($sortColumn, $filters['sort_dir'])
            ->orderBy('receiving_entry_lines.id', 'desc');
    }

    private function itemUsageQuery(array $filters)
    {
        $sortable = [
            'trx_datetime' => 'stock_ledgers.trx_datetime',
            'warehouse' => 'warehouses.name',
            'item' => 'items.name',
            'category' => 'categories.name',
            'qty' => DB::raw('ABS(stock_ledgers.qty_input)'),
            'value' => DB::raw('ABS(stock_ledgers.qty_base * COALESCE(stock_ledgers.unit_cost, 0))'),
            'unit_price' => DB::raw('COALESCE(stock_ledgers.unit_cost, 0)'),
        ];

        $sortColumn = $sortable[$filters['sort_by']] ?? $sortable['trx_datetime'];

        return DB::table('stock_ledgers')
            ->join('warehouses', 'warehouses.id', '=', 'stock_ledgers.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_ledgers.item_id')
            ->leftJoin('uoms', 'uoms.id', '=', 'stock_ledgers.uom_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('stock_ledgers.trx_type', 'USAGE_OUT')
            ->where('stock_ledgers.qty_base', '<', 0)
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('stock_ledgers.warehouse_id', $warehouseId))
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->where('items.category_id', $categoryId))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $keyword = '%'.$filters['search'].'%';
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('warehouses.name', 'like', $keyword)
                        ->orWhere('items.name', 'like', $keyword)
                        ->orWhere('items.sku', 'like', $keyword)
                        ->orWhere('categories.name', 'like', $keyword)
                        ->orWhere('stock_ledgers.trx_type', 'like', $keyword)
                        ->orWhereRaw('CAST(stock_ledgers.trx_id AS CHAR) like ?', [$keyword]);
                });
            })
            ->select([
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                DB::raw('COALESCE(categories.name, \'-\') as category_name'),
                'items.sku',
                DB::raw("COALESCE(uoms.code, uoms.name) as uom_name"),
                DB::raw("DATE_FORMAT(stock_ledgers.trx_datetime, '%Y-%m-%d %H:%i:%s') as trx_datetime"),
                DB::raw("CONCAT(stock_ledgers.trx_type, '-', stock_ledgers.trx_id) as reference"),
                DB::raw('COALESCE(stock_ledgers.unit_cost, 0) as unit_price'),
                DB::raw('ABS(stock_ledgers.qty_input) as qty'),
                DB::raw('ABS(stock_ledgers.qty_input * COALESCE(stock_ledgers.unit_cost, 0)) as value'),
                DB::raw("'-' as status"),
                DB::raw("'-' as vendor_name"),
            ])
            ->orderBy($sortColumn, $filters['sort_dir'])
            ->orderBy('stock_ledgers.id', 'desc');
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
}
