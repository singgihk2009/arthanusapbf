<?php

namespace App\Http\Controllers\Apps\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $selectedItem = null;

        if ($filters['item_id']) {
            $selectedItem = DB::table('items')
                ->select('id', 'sku', 'name')
                ->where('id', $filters['item_id'])
                ->first();
        }

        return inertia('Apps/Reports/Inventory/Index', [
            'filters' => $filters,
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('code')->get(),
            'categories' => DB::table('categories')->select('id', 'name')->orderBy('name')->get(),
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->limit(200)->get(),
            'selectedItem' => $selectedItem,
            'facilitySchemes' => DB::table('facility_schemes')->select('id', 'code', 'name')->orderBy('code')->get(),
            'reportData' => $reportData,
        ]);
    }

    public function searchItems(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = trim($validated['q']);
        $limit = $validated['limit'] ?? 20;
        $keyword = '%'.$query.'%';

        $items = DB::table('items')
            ->select('id', 'sku', 'name')
            ->where(function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'like', $keyword)
                    ->orWhere('sku', 'like', $keyword);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function exportStockBalanceExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->resolveFilters($request);
        $rows = $this->baseQuery($filters)->limit(10000)->get();

        $isIncoming = $filters['type'] === 'incoming-items';
        $isUsage = $filters['type'] === 'item-usage';
        $isStockPosition = $filters['type'] === 'stock-position';
        $isStockCard = $filters['type'] === 'stock-card-movement';
        $tempPath = storage_path('app/'.$filters['type'].'-report-'.now()->format('YmdHis').'.xlsx');

        if ($isStockPosition) {
            $xlsxRows = [[
                'Warehouse',
                'Item',
                'Kategori',
                'SKU',
                'On Hand',
                'Reserved',
                'Available',
            ]];

            $numberColumns = [4, 5, 6];
            $dateColumns = [];
        } elseif ($isStockCard) {
            $xlsxRows = [[
                'Warehouse',
                'Tanggal',
                'Referensi',
                'Item',
                'SKU',
                'Qty Movement',
                'Saldo Berjalan',
                'Unit Cost',
                'Value Movement',
            ]];

            $numberColumns = [5, 6, 7, 8];
            $dateColumns = [1];
        } elseif ($isIncoming || $isUsage) {
            $xlsxRows = [[
                'Jenis Dok',
                $isUsage ? 'No Daftar Pengeluaran Barang' : 'Nomor Daftar',
                'Tgl Daftar',
                $isUsage ? 'Nomor Bukti Pengeluaran Barang' : 'No Penerimaan Barang',
                $isUsage ? 'Tanggal Keluar' : 'Tanggal Terima',
                'Nama Pengirim Barang',
                'Kode Barang',
                'Kategory Barang',
                'Nama Barang',
                'Satuan',
                'Jumlah Barang',
                'Harga Satuan',
                'Total Harga',
            ]];

            $numberColumns = [10, 11, 12];
            $dateColumns = [2, 4];
        } else {
            $xlsxRows = [[
                'Warehouse',
                'Tanggal',
                'Referensi',
                'Kode Transaksi',
                'Item',
                'Kategori',
                'SKU',
                'UoM',
                'Unit Price',
                $isIncoming ? 'Qty Masuk' : 'Qty Keluar',
                'Value',
            ]];

            $numberColumns = [8, 9, 10];
            $dateColumns = [1];
        }

        foreach ($rows as $row) {
            if ($isStockPosition) {
                $line = [
                    $row->warehouse_name,
                    $row->item_name,
                    $row->category_name,
                    $row->sku,
                    (float) $row->on_hand,
                    (float) $row->reserved,
                    (float) $row->available,
                ];
            } elseif ($isStockCard) {
                $line = [
                    $row->warehouse_name,
                    $row->trx_datetime,
                    $row->reference,
                    $row->item_name,
                    $row->sku,
                    (float) $row->qty,
                    (float) $row->running_balance,
                    (float) $row->unit_price,
                    (float) $row->value,
                ];
            } elseif ($isIncoming || $isUsage) {
                $line = [
                    $row->facility_name,
                    $isUsage ? $row->gr_number : $row->facility_reference_no,
                    $row->facility_reference_date,
                    $isUsage ? $row->facility_reference_no : $row->gr_number,
                    $row->trx_datetime,
                    $row->vendor_name,
                    $row->sku,
                    $row->category_name,
                    $row->item_name,
                    $row->uom_name,
                    (float) $row->qty,
                    (float) $row->unit_price,
                    (float) $row->value,
                ];
            } else {
                $line = [
                    $row->warehouse_name,
                    $row->trx_datetime,
                    $row->reference,
                    $row->transaction_code,
                    $row->item_name,
                    $row->category_name,
                    $row->sku,
                    $row->uom_name,
                    (float) $row->unit_price,
                    (float) $row->qty,
                    (float) $row->value,
                ];
            }

            $xlsxRows[] = $line;
        }

        $this->buildTemplateXlsx($tempPath, $xlsxRows, $numberColumns, $dateColumns);

        return response()->download(
            $tempPath,
            $filters['type'].'-report-'.now()->format('Ymd-His').'.xlsx'
        )->deleteFileAfterSend(true);
    }

    private function resolveFilters(Request $request): array
    {
        $type = $request->string('type')->toString() ?: 'incoming-items';
        $allowed = ['incoming-items', 'item-usage', 'stock-position', 'stock-card-movement'];

        if (! in_array($type, $allowed, true)) {
            $type = 'incoming-items';
        }

        $sortBy = $request->string('sort_by')->toString() ?: 'trx_datetime';
        if (! in_array($sortBy, ['trx_datetime', 'warehouse', 'item', 'category', 'qty', 'value', 'unit_price', 'status', 'vendor', 'on_hand', 'reserved', 'available', 'running_balance'], true)) {
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
            'item_id' => $request->integer('item_id') ?: null,
            'search' => trim((string) $request->string('search')->toString()),
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'per_page' => $perPage,
            'status' => strtolower($request->string('status')->toString() ?: 'all'),
            'facility_scheme_id' => $request->integer('facility_scheme_id') ?: null,
            'start_date' => $request->date('start_date')?->toDateString() ?? now()->startOfYear()->toDateString(),
            'end_date' => $request->date('end_date')?->toDateString() ?? now()->toDateString(),
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
        return match ($filters['type']) {
            'incoming-items' => $this->incomingItemsQuery($filters),
            'item-usage' => $this->itemUsageQuery($filters),
            'stock-position' => $this->stockPositionQuery($filters),
            'stock-card-movement' => $this->stockCardMovementQuery($filters),
            default => $this->incomingItemsQuery($filters),
        };
    }

    private function stockPositionQuery(array $filters)
    {
        $sortable = [
            'warehouse' => 'warehouses.name',
            'item' => 'items.name',
            'category' => 'categories.name',
            'on_hand' => DB::raw('ending_balance'),
            'reserved' => DB::raw('SUM(stock_balances.reserved_base)'),
            'available' => DB::raw('(ending_balance - SUM(stock_balances.reserved_base))'),
        ];

        $sortColumn = $sortable[$filters['sort_by']] ?? $sortable['warehouse'];

        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate = Carbon::parse($filters['end_date'])->endOfDay();

        $ledgerSums = DB::table('stock_ledgers')
            ->select([
                'warehouse_id',
                'item_id',
                DB::raw('SUM(CASE WHEN trx_datetime < "'.$startDate->format('Y-m-d H:i:s').'" THEN qty_base ELSE 0 END) as beginning_balance'),
                DB::raw('SUM(CASE WHEN trx_datetime BETWEEN "'.$startDate->format('Y-m-d H:i:s').'" AND "'.$endDate->format('Y-m-d H:i:s').'" THEN qty_base ELSE 0 END) as movement_qty'),
            ])
            ->where('trx_datetime', '<=', $endDate)
            ->when($filters['facility_scheme_id'], fn ($query, $facilitySchemeId) => $query->where('facility_scheme_id', $facilitySchemeId))
            ->groupBy('warehouse_id', 'item_id');

        return DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoinSub($ledgerSums, 'ledger_sums', function ($join) {
                $join->on('ledger_sums.warehouse_id', '=', 'stock_balances.warehouse_id')
                    ->on('ledger_sums.item_id', '=', 'stock_balances.item_id');
            })
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('stock_balances.warehouse_id', $warehouseId))
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->where('items.category_id', $categoryId))
            ->when($filters['item_id'], fn ($query, $itemId) => $query->where('stock_balances.item_id', $itemId))
            ->where(function ($query) use ($filters) {
                $query->whereNotNull('ledger_sums.item_id');

                if (! $filters['facility_scheme_id']) {
                    $query->orWhere('stock_balances.on_hand_base', '!=', 0)
                        ->orWhere('stock_balances.reserved_base', '!=', 0);
                }
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $keyword = '%'.$filters['search'].'%';
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('warehouses.name', 'like', $keyword)
                        ->orWhere('items.name', 'like', $keyword)
                        ->orWhere('items.sku', 'like', $keyword)
                        ->orWhere('categories.name', 'like', $keyword);
                });
            })
            ->groupBy('stock_balances.warehouse_id', 'stock_balances.item_id', 'warehouses.name', 'items.name', 'items.sku', 'categories.name', 'ledger_sums.beginning_balance', 'ledger_sums.movement_qty')
            ->select([
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                DB::raw('COALESCE(categories.name, "-") as category_name'),
                'items.sku',
                DB::raw('COALESCE(ledger_sums.beginning_balance, 0) + COALESCE(ledger_sums.movement_qty, 0) as ending_balance'),
                DB::raw('SUM(stock_balances.reserved_base) as reserved'),
                DB::raw('(COALESCE(ledger_sums.beginning_balance, 0) + COALESCE(ledger_sums.movement_qty, 0) - SUM(stock_balances.reserved_base)) as available'),
            ])
            ->selectRaw('(COALESCE(ledger_sums.beginning_balance, 0) + COALESCE(ledger_sums.movement_qty, 0)) as on_hand')
            ->orderBy($sortColumn, $filters['sort_dir']);
    }

    private function stockCardMovementQuery(array $filters)
    {
        if (! $filters['item_id']) {
            return DB::table('stock_ledgers')->whereRaw('1 = 0');
        }

        $startDate = $filters['start_date'].' 00:00:00';
        $endDate = $filters['end_date'].' 23:59:59';

        $openingBalancePerWarehouse = DB::table('stock_ledgers')
            ->select([
                'warehouse_id',
                DB::raw('SUM(qty_base) as opening_balance'),
            ])
            ->where('item_id', $filters['item_id'])
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['facility_scheme_id'], fn ($query, $facilitySchemeId) => $query->where('facility_scheme_id', $facilitySchemeId))
            ->where('trx_datetime', '<', $startDate)
            ->groupBy('warehouse_id');

        return DB::table('stock_ledgers')
            ->join('warehouses', 'warehouses.id', '=', 'stock_ledgers.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_ledgers.item_id')
            ->leftJoinSub($openingBalancePerWarehouse, 'opening_balances', function ($join) {
                $join->on('opening_balances.warehouse_id', '=', 'stock_ledgers.warehouse_id');
            })
            ->where('stock_ledgers.item_id', $filters['item_id'])
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('stock_ledgers.warehouse_id', $warehouseId))
            ->when($filters['facility_scheme_id'], fn ($query, $facilitySchemeId) => $query->where('stock_ledgers.facility_scheme_id', $facilitySchemeId))
            ->whereBetween('stock_ledgers.trx_datetime', [$startDate, $endDate])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $keyword = '%'.$filters['search'].'%';
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('warehouses.name', 'like', $keyword)
                        ->orWhere('items.name', 'like', $keyword)
                        ->orWhere('items.sku', 'like', $keyword)
                        ->orWhere('stock_ledgers.trx_type', 'like', $keyword)
                        ->orWhereRaw('CAST(stock_ledgers.trx_id AS CHAR) like ?', [$keyword]);
                });
            })
            ->select([
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                'items.sku',
                DB::raw("DATE_FORMAT(stock_ledgers.trx_datetime, '%Y-%m-%d %H:%i:%s') as trx_datetime"),
                DB::raw("CONCAT(stock_ledgers.trx_type, '-', stock_ledgers.trx_id) as reference"),
                DB::raw('stock_ledgers.qty_base as qty'),
                DB::raw('COALESCE(stock_ledgers.unit_cost, 0) as unit_price'),
                DB::raw('stock_ledgers.qty_base * COALESCE(stock_ledgers.unit_cost, 0) as value'),
                DB::raw('(COALESCE(opening_balances.opening_balance, 0) + SUM(stock_ledgers.qty_base) OVER (PARTITION BY stock_ledgers.warehouse_id ORDER BY stock_ledgers.trx_datetime, stock_ledgers.id)) as running_balance'),
            ])
            ->orderBy('stock_ledgers.trx_datetime')
            ->orderBy('stock_ledgers.id');
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
            ->leftJoin('facility_schemes', 'facility_schemes.id', '=', 'receiving_entry_lines.facility_scheme_id')
            ->leftJoin('purchase_orders', function ($join) {
                $join->on('purchase_orders.id', '=', 'receiving_entries.source_id')
                    ->where('receiving_entries.source_type', '=', 'purchase_order');
            })
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'posted') {
                    $query->where('receiving_entries.status', 'POSTED');

                    return;
                }

                $query->where('receiving_entries.status', '!=', 'POSTED');
            })
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('receiving_entries.warehouse_id', $warehouseId))
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->where('items.category_id', $categoryId))
            ->when($filters['facility_scheme_id'], fn ($query, $facilitySchemeId) => $query->where('receiving_entry_lines.facility_scheme_id', $facilitySchemeId))
            ->whereBetween('receiving_entries.transaction_date', [$filters['start_date'], $filters['end_date']])
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
                DB::raw("COALESCE(receiving_entries.transaction_code, '-') as transaction_code"),
                DB::raw("COALESCE(receiving_entries.number, receiving_entries.reference, '-') as gr_number"),
                DB::raw("COALESCE(receiving_entries.reference, receiving_entries.number) as reference"),
                DB::raw("COALESCE(DATE_FORMAT(purchase_orders.po_date, '%Y-%m-%d'), '-') as po_date"),
                DB::raw('COALESCE(uoms.code, uoms.name) as uom_name'),
                DB::raw('COALESCE(receiving_entry_lines.price, 0) as unit_price'),
                DB::raw('ABS(receiving_entry_lines.qty) as qty'),
                DB::raw('ABS(COALESCE(receiving_entry_lines.value, receiving_entry_lines.qty * COALESCE(receiving_entry_lines.price, 0))) as value'),
                DB::raw("COALESCE(receiving_entries.status, 'DRAFT') as status"),
                DB::raw("COALESCE(receiving_entries.vendor_name, '-') as vendor_name"),
                'receiving_entries.vendor_id as vendor_id',
                'purchase_orders.id as purchase_order_id',
                DB::raw("COALESCE(facility_schemes.name, facility_schemes.code, '-') as facility_name"),
                DB::raw("COALESCE(receiving_entry_lines.facility_reference_no, '-') as facility_reference_no"),
                DB::raw("COALESCE(DATE_FORMAT(receiving_entry_lines.facility_reference_date, '%Y-%m-%d'), '-') as facility_reference_date"),
            ])
            ->orderBy($sortColumn, $filters['sort_dir'])
            ->orderBy('receiving_entry_lines.id', 'desc');
    }

    private function itemUsageQuery(array $filters)
    {
        $unitPriceSortExpression = DB::raw('ABS(COALESCE(stock_ledgers.unit_cost, 0) * (stock_ledgers.qty_base / NULLIF(stock_ledgers.qty_input, 0)))');
        $valueSortExpression = DB::raw('ABS(stock_ledgers.qty_base * COALESCE(stock_ledgers.unit_cost, 0))');

        $sortable = [
            'trx_datetime' => 'stock_ledgers.trx_datetime',
            'warehouse' => 'warehouses.name',
            'item' => 'items.name',
            'category' => 'categories.name',
            'qty' => DB::raw('ABS(stock_ledgers.qty_input)'),
            'value' => $valueSortExpression,
            'unit_price' => $unitPriceSortExpression,
        ];

        $sortColumn = $sortable[$filters['sort_by']] ?? $sortable['trx_datetime'];

        $sourceLedgerSubquery = DB::table('stock_ledgers as usage_ledgers')
            ->select([
                'usage_ledgers.id as usage_ledger_id',
                DB::raw("(SELECT src.id FROM stock_ledgers src
                    WHERE src.qty_base > 0
                      AND src.item_id = usage_ledgers.item_id
                      AND src.warehouse_id = usage_ledgers.warehouse_id
                      AND (usage_ledgers.batch_id IS NULL OR src.batch_id = usage_ledgers.batch_id)
                      AND src.trx_datetime <= usage_ledgers.trx_datetime
                      AND src.trx_type IN ('RCV_IN', 'TRANSFER_IN', 'OPENING_BALANCE')
                    ORDER BY src.trx_datetime DESC, src.id DESC
                    LIMIT 1) as source_ledger_id"),
            ])
            ->where('usage_ledgers.trx_type', 'USAGE_OUT')
            ->where('usage_ledgers.qty_base', '<', 0);

        return DB::table('stock_ledgers')
            ->join('warehouses', 'warehouses.id', '=', 'stock_ledgers.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_ledgers.item_id')
            ->leftJoin('uoms', 'uoms.id', '=', 'stock_ledgers.uom_id')
            ->leftJoin('internal_usages', function ($join) {
                $join->on('internal_usages.id', '=', 'stock_ledgers.trx_id')
                    ->where('stock_ledgers.trx_type', '=', 'USAGE_OUT');
            })
            ->leftJoinSub($sourceLedgerSubquery, 'source_ledgers_map', function ($join) {
                $join->on('source_ledgers_map.usage_ledger_id', '=', 'stock_ledgers.id');
            })
            ->leftJoin('stock_ledgers as source_ledgers', 'source_ledgers.id', '=', 'source_ledgers_map.source_ledger_id')
            ->leftJoin('receiving_entries as source_receiving_entries', function ($join) {
                $join->on('source_receiving_entries.id', '=', 'source_ledgers.trx_id')
                    ->where('source_ledgers.trx_type', '=', 'RCV_IN');
            })
            ->leftJoin('receiving_entry_lines as source_receiving_lines', 'source_receiving_lines.id', '=', 'source_ledgers.trx_line_id')
            ->leftJoin('purchase_orders as source_purchase_orders', function ($join) {
                $join->on('source_purchase_orders.id', '=', 'source_receiving_entries.source_id')
                    ->where('source_receiving_entries.source_type', '=', 'purchase_order');
            })
            ->leftJoin('facility_schemes as source_facility_schemes', 'source_facility_schemes.id', '=', 'source_receiving_lines.facility_scheme_id')
            ->leftJoin('facility_schemes as usage_facility_schemes', 'usage_facility_schemes.id', '=', 'internal_usages.facility_scheme_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('stock_ledgers.trx_type', 'USAGE_OUT')
            ->where('stock_ledgers.qty_base', '<', 0)
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('stock_ledgers.warehouse_id', $warehouseId))
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->where('items.category_id', $categoryId))
            ->whereBetween('stock_ledgers.trx_datetime', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay(),
            ])
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
                DB::raw("COALESCE(DATE_FORMAT(internal_usages.document_date, '%Y-%m-%d'), DATE_FORMAT(stock_ledgers.trx_datetime, '%Y-%m-%d %H:%i:%s')) as trx_datetime"),
                DB::raw("COALESCE(internal_usages.transaction_code, '-') as transaction_code"),
                DB::raw("CONCAT(stock_ledgers.trx_type, '-', stock_ledgers.trx_id) as reference"),
                DB::raw('ABS(COALESCE(stock_ledgers.unit_cost, 0) * (stock_ledgers.qty_base / NULLIF(stock_ledgers.qty_input, 0))) as unit_price'),
                DB::raw('ABS(stock_ledgers.qty_input) as qty'),
                DB::raw('ABS(stock_ledgers.qty_base * COALESCE(stock_ledgers.unit_cost, 0)) as value'),
                DB::raw("COALESCE(internal_usages.outbound_number, '-') as gr_number"),
                DB::raw("COALESCE(internal_usages.sender_receiver_name, '-') as vendor_name"),
                'source_receiving_entries.vendor_id as vendor_id',
                'source_purchase_orders.id as purchase_order_id',
                DB::raw("COALESCE(DATE_FORMAT(source_purchase_orders.po_date, '%Y-%m-%d'), '-') as po_date"),
                DB::raw("COALESCE(usage_facility_schemes.name, usage_facility_schemes.code, source_facility_schemes.name, source_facility_schemes.code, '-') as facility_name"),
                DB::raw("COALESCE(internal_usages.number, '-') as facility_reference_no"),
                DB::raw("COALESCE(DATE_FORMAT(internal_usages.document_date, '%Y-%m-%d'), '-') as facility_reference_date"),
                DB::raw("COALESCE(source_receiving_entries.status, '-') as status"),
            ])
            ->orderBy($sortColumn, $filters['sort_dir'])
            ->orderBy('stock_ledgers.id', 'desc');
    }

    private function buildTemplateXlsx(string $path, array $rows, array $numberColumns = [], array $dateColumns = []): void
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
                $coordinate = "{$column}".($rowIndex + 1);

                if ($rowIndex > 0 && in_array($colIndex, $dateColumns, true)) {
                    $excelDate = $this->toExcelDateValue($value);

                    if ($excelDate !== null) {
                        $cellXml .= "<c r=\"{$coordinate}\" s=\"1\"><v>{$excelDate}</v></c>";

                        continue;
                    }
                }

                if ($rowIndex > 0 && in_array($colIndex, $numberColumns, true) && is_numeric($value)) {
                    $cellXml .= "<c r=\"{$coordinate}\"><v>".(float) $value."</v></c>";

                    continue;
                }

                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$coordinate}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= "<row r=\"".($rowIndex + 1)."\">{$cellXml}</row>";
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd hh:mm:ss"/></numFmts><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }

    private function toExcelDateValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }

        return ($date->getTimestamp() / 86400) + 25569;
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
