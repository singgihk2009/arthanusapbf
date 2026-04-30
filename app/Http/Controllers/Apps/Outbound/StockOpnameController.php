<?php

namespace App\Http\Controllers\Apps\Outbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockOpnameRequest;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class StockOpnameController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(): Response
    {
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');

        $entries = DB::table('stock_opnames')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (object $entry) use ($warehouseCodes): object {
                $entry->warehouse_label = (string) ($warehouseCodes->get((int) $entry->warehouse_id) ?? '-');

                return $entry;
            });

        return Inertia::render('Apps/Outbound/StockOpname/Index', [
            'entries' => $entries,
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('code')->get(),
        ]);
    }

    public function downloadTemplateExcel(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouse = DB::table('warehouses')->where('id', $validated['warehouse_id'])->first();
        abort_if(! $warehouse, 404);

        $rows = $this->buildTemplateRows((int) $validated['warehouse_id'], (string) $warehouse->code, (string) $warehouse->name);
        $tempPath = storage_path('app/stock-opname-template-'.$validated['warehouse_id'].'-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($tempPath, $rows);

        return response()->download($tempPath, 'stock-opname-template-'.$warehouse->code.'.xlsx')->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'document_date' => ['required', 'date'],
            'type' => ['required', 'in:FULL,CYCLE'],
            'notes' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt'],
        ]);

        $rows = $this->parseImportRows($request->file('file'));
        $lines = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $countedQty = trim((string) ($row['counted_qty_base'] ?? ''));
            if ($countedQty === '') {
                continue;
            }

            try {
                $data = validator($row, [
                    'item_sku' => ['required', 'string'],
                    'counted_qty_base' => ['required', 'numeric', 'gte:0'],
                    'batch_number_system' => ['nullable', 'string'],
                    'counted_batch_number' => ['nullable', 'string'],
                    'counted_expired_date' => ['nullable', 'date'],
                ])->validate();

                $item = DB::table('items')->where('sku', $data['item_sku'])->first();
                if (! $item) {
                    throw new \RuntimeException('SKU tidak ditemukan: '.$data['item_sku']);
                }

                $batchNumber = trim((string) ($data['counted_batch_number'] ?: $data['batch_number_system'] ?: ''));
                $batchId = null;
                if ($batchNumber !== '') {
                    $expiredDate = ! empty($data['counted_expired_date']) ? $data['counted_expired_date'] : null;
                    $batchId = DB::table('item_batches')
                        ->where('item_id', $item->id)
                        ->where('batch_no', $batchNumber)
                        ->where('expired_date', $expiredDate)
                        ->value('id');

                    if (! $batchId) {
                        $batchId = DB::table('item_batches')->insertGetId([
                            'item_id' => $item->id,
                            'batch_no' => $batchNumber,
                            'expired_date' => $expiredDate,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $lineKey = $item->id.'|'.($batchId ?: 0);
                if (! isset($lines[$lineKey])) {
                    $lines[$lineKey] = [
                        'item_id' => (int) $item->id,
                        'batch_id' => $batchId ? (int) $batchId : null,
                        'counted_qty_base' => 0,
                    ];
                }

                $lines[$lineKey]['counted_qty_base'] += (float) $data['counted_qty_base'];
            } catch (\Throwable $exception) {
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'message' => 'Import gagal, periksa data file.',
                'errors' => $errors,
            ], 422);
        }

        if (empty($lines)) {
            return response()->json([
                'message' => 'Tidak ada baris qty opname yang dapat diproses.',
            ], 422);
        }

        $entryId = 0;
        DB::transaction(function () use ($validated, $request, &$entryId, $lines): void {
            $entryId = DB::table('stock_opnames')->insertGetId([
                'number' => $this->generateNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'document_date' => $validated['document_date'],
                'type' => $validated['type'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? 'Imported from stock opname excel',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->replaceLines($entryId, (int) $validated['warehouse_id'], array_values($lines));
            $this->postById($entryId, $request->user()?->id);
        });

        return response()->json([
            'message' => 'Import stock opname berhasil dan adjustment otomatis dibuat.',
            'id' => $entryId,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Apps/Outbound/StockOpname/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no')->orderBy('batch_no')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(StockOpnameRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $entryId = DB::table('stock_opnames')->insertGetId([
                'number' => $this->generateNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'document_date' => $validated['document_date'],
                'type' => $validated['type'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->replaceLines($entryId, (int) $validated['warehouse_id'], $validated['lines']);
        });

        return to_route('apps.outbound.stock-opname.index')->with('success', 'Stock opname berhasil disimpan.');
    }

    public function edit(int $stockOpname): Response
    {
        $entry = DB::table('stock_opnames')->where('id', $stockOpname)->first();
        abort_if(! $entry, 404);

        $lines = DB::table('stock_opname_lines')
            ->where('stock_opname_id', $stockOpname)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): array => [
                'item_id' => (string) $line->item_id,
                'batch_id' => $line->batch_id ? (string) $line->batch_id : '',
                'system_qty_base' => (string) $line->system_qty_base,
                'counted_qty_base' => (string) $line->counted_qty_base,
                'variance_qty_base' => (string) $line->variance_qty_base,
            ]);

        return Inertia::render('Apps/Outbound/StockOpname/Edit', [
            'entry' => [
                'id' => $entry->id,
                'warehouse_id' => (string) $entry->warehouse_id,
                'document_date' => (string) $entry->document_date,
                'type' => (string) $entry->type,
                'notes' => (string) ($entry->notes ?? ''),
                'status' => (string) $entry->status,
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no')->orderBy('batch_no')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(StockOpnameRequest $request, int $stockOpname): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $stockOpname): void {
            $entry = DB::table('stock_opnames')->where('id', $stockOpname)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat diubah.');

            DB::table('stock_opnames')->where('id', $stockOpname)->update([
                'warehouse_id' => $validated['warehouse_id'],
                'document_date' => $validated['document_date'],
                'type' => $validated['type'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->replaceLines($stockOpname, (int) $validated['warehouse_id'], $validated['lines']);
        });

        return to_route('apps.outbound.stock-opname.index')->with('success', 'Stock opname berhasil diperbarui.');
    }

    public function destroy(int $stockOpname): RedirectResponse
    {
        DB::transaction(function () use ($stockOpname): void {
            $entry = DB::table('stock_opnames')->where('id', $stockOpname)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat dihapus.');

            DB::table('stock_opname_lines')->where('stock_opname_id', $stockOpname)->delete();
            DB::table('stock_opnames')->where('id', $stockOpname)->delete();
        });

        return back()->with('success', 'Stock opname berhasil dihapus.');
    }

    public function post(Request $request, int $stockOpname): JsonResponse
    {
        $this->postById($stockOpname, $request->user()?->id);

        return response()->json(['message' => 'Stock opname posted', 'id' => $stockOpname]);
    }

    private function postById(int $stockOpname, ?int $userId): void
    {
        $header = DB::table('stock_opnames')->where('id', $stockOpname)->first();
        abort_unless($header, 404, 'Stock opname not found');
        abort_if($header->status === 'POSTED', 422, 'Stock opname already posted');

        $lines = DB::table('stock_opname_lines')->where('stock_opname_id', $stockOpname)->get();

        $recalculatedLines = $lines->map(function (object $line) use ($header): object {
            $systemQty = $this->getSystemQtyBase(
                (int) $header->warehouse_id,
                (int) $line->item_id,
                $line->batch_id ? (int) $line->batch_id : null,
            );

            $line->system_qty_base = $systemQty;
            $line->variance_qty_base = (float) $line->counted_qty_base - $systemQty;

            return $line;
        });

        $adjustmentId = null;
        $varianceLines = $recalculatedLines->filter(fn (object $line) => (float) $line->variance_qty_base !== 0.0)->values();

        DB::transaction(function () use ($header, $userId, $stockOpname, $recalculatedLines, $varianceLines, &$adjustmentId): void {
            foreach ($recalculatedLines as $line) {
                DB::table('stock_opname_lines')
                    ->where('id', $line->id)
                    ->update([
                        'system_qty_base' => $line->system_qty_base,
                        'variance_qty_base' => $line->variance_qty_base,
                        'updated_at' => now(),
                    ]);
            }

            if ($varianceLines->isNotEmpty()) {
                $adjustmentId = DB::table('stock_adjustments')->insertGetId([
                    'number' => $this->generateAdjustmentNumber(),
                    'warehouse_id' => $header->warehouse_id,
                    'document_date' => $header->document_date,
                    'reason_code' => 'OPNAME',
                    'status' => 'POSTED',
                    'notes' => 'Auto generated from stock opname '.$header->number,
                    'posted_by' => $userId,
                    'posted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($varianceLines as $line) {
                    DB::table('stock_adjustment_lines')->insert([
                        'stock_adjustment_id' => $adjustmentId,
                        'item_id' => $line->item_id,
                        'batch_id' => $line->batch_id,
                        'qty_adjusted' => $line->variance_qty_base,
                        'uom_id' => $this->resolveDefaultUomId((int) $line->item_id),
                        'qty_base' => $line->variance_qty_base,
                        'notes' => 'Generated from stock opname',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->stockService->postMutation([
                        'trx_type' => 'ADJ_OPNAME',
                        'trx_id' => $adjustmentId,
                        'trx_line_id' => $line->id,
                        'warehouse_id' => $header->warehouse_id,
                        'item_id' => $line->item_id,
                        'batch_id' => $line->batch_id,
                        'qty_base' => $line->variance_qty_base,
                        'uom_id' => $this->resolveDefaultUomId((int) $line->item_id),
                        'qty_input' => abs((float) $line->variance_qty_base),
                        'created_by' => $userId,
                    ]);
                }
            }

            DB::table('stock_opnames')->where('id', $stockOpname)->update([
                'status' => 'POSTED',
                'adjustment_id' => $adjustmentId,
                'posted_by' => $userId,
                'posted_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function buildTemplateRows(int $warehouseId, string $warehouseCode, string $warehouseName): array
    {
        $items = DB::table('items')
            ->join('uoms', 'uoms.id', '=', 'items.base_uom_id')
            ->select('items.id', 'items.sku', 'items.name', 'uoms.code as base_uom')
            ->orderBy('items.sku')
            ->get();

        $balancesByItem = DB::table('stock_balances')
            ->leftJoin('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.warehouse_id', $warehouseId)
            ->select('stock_balances.item_id', 'stock_balances.on_hand_base', 'item_batches.batch_no')
            ->orderBy('stock_balances.item_id')
            ->orderBy('item_batches.batch_no')
            ->get()
            ->groupBy('item_id');

        $rows = [[
            'warehouse_code',
            'warehouse_name',
            'item_sku',
            'item_name',
            'system_qty_base',
            'base_uom',
            'batch_number_system',
            'counted_qty_base',
            'counted_batch_number',
            'counted_expired_date',
        ]];

        foreach ($items as $item) {
            $itemBalances = $balancesByItem->get($item->id);

            if (! $itemBalances || $itemBalances->isEmpty()) {
                $rows[] = [$warehouseCode, $warehouseName, $item->sku, $item->name, '0', $item->base_uom, '', '', '', ''];
                continue;
            }

            foreach ($itemBalances as $balance) {
                $rows[] = [
                    $warehouseCode,
                    $warehouseName,
                    $item->sku,
                    $item->name,
                    (string) $balance->on_hand_base,
                    $item->base_uom,
                    (string) ($balance->batch_no ?? ''),
                    '',
                    '',
                    '',
                ];
            }
        }

        return $rows;
    }

    private function parseImportRows(UploadedFile $file): Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'xlsx' => $this->parseXlsxRows($file->getRealPath()),
            default => $this->parseCsvRows($file->getRealPath()),
        };
    }

    private function parseCsvRows(string $path): Collection
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return collect();
        }

        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if (! $header) {
                $header = array_map(fn ($item) => trim((string) $item), $data);
                continue;
            }

            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($data) {
                return [$key => trim((string) ($data[$index] ?? ''))];
            })->all();
        }

        fclose($handle);

        return collect($rows);
    }

    private function parseXlsxRows(string $path): Collection
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path);
        if ($opened !== true) {
            return collect();
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared !== false && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    $sharedStrings[] = isset($si->t) ? (string) $si->t : '';
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! $sheetXml) {
            return collect();
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || ! isset($xml->sheetData->row)) {
            return collect();
        }

        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $type = (string) ($cell['t'] ?? '');
                $value = '';

                if ($type === 's') {
                    $idx = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                $line[] = trim($value);
            }

            $table[] = $line;
        }

        if (count($table) < 2) {
            return collect();
        }

        $header = $table[0];
        $rows = [];

        foreach (array_slice($table, 1) as $line) {
            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($line) {
                return [$key => trim((string) ($line[$index] ?? ''))];
            })->all();
        }

        return collect($rows);
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
                $column = chr(65 + $colIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$column}".($rowIndex + 1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= "<row r=\"".($rowIndex + 1)."\">{$cellXml}</row>";
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function replaceLines(int $entryId, int $warehouseId, array $lines): void
    {
        DB::table('stock_opname_lines')->where('stock_opname_id', $entryId)->delete();

        foreach ($lines as $line) {
            $systemQty = $this->getSystemQtyBase(
                $warehouseId,
                (int) $line['item_id'],
                ! empty($line['batch_id']) ? (int) $line['batch_id'] : null,
            );

            $countedQty = (float) $line['counted_qty_base'];

            DB::table('stock_opname_lines')->insert([
                'stock_opname_id' => $entryId,
                'item_id' => $line['item_id'],
                'batch_id' => $line['batch_id'] ?: null,
                'system_qty_base' => $systemQty,
                'counted_qty_base' => $countedQty,
                'variance_qty_base' => $countedQty - $systemQty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function resolveDefaultUomId(int $itemId): int
    {
        return (int) DB::table('items')->where('id', $itemId)->value('base_uom_id');
    }

    private function getSystemQtyBase(int $warehouseId, int $itemId, ?int $batchId): float
    {
        return (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->when($batchId, fn ($q, $id) => $q->where('batch_id', $id))
            ->sum('on_hand_base');
    }

    private function generateNumber(): string
    {
        $prefix = 'OPN';
        $datePart = now()->format('Ymd');
        $lastSequence = DB::table('stock_opnames')->where('number', 'like', "$prefix-$datePart-%")->count();
        $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }

    private function generateAdjustmentNumber(): string
    {
        $prefix = 'ADJ';
        $datePart = now()->format('Ymd');
        $lastSequence = DB::table('stock_adjustments')->where('number', 'like', "$prefix-$datePart-%")->count();
        $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }
}
