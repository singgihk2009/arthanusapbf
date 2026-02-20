<?php

namespace App\Http\Controllers\Apps\Outbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockOpnameRequest;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
        $header = DB::table('stock_opnames')->where('id', $stockOpname)->first();
        abort_unless($header, 404, 'Stock opname not found');
        abort_if($header->status === 'POSTED', 422, 'Stock opname already posted');

        $lines = DB::table('stock_opname_lines')->where('stock_opname_id', $stockOpname)->get();

        $adjustmentId = null;
        $varianceLines = $lines->filter(fn (object $line) => (float) $line->variance_qty_base !== 0.0)->values();

        DB::transaction(function () use ($header, $request, $stockOpname, $varianceLines, &$adjustmentId): void {
            if ($varianceLines->isNotEmpty()) {
                $adjustmentId = DB::table('stock_adjustments')->insertGetId([
                    'number' => $this->generateAdjustmentNumber(),
                    'warehouse_id' => $header->warehouse_id,
                    'document_date' => $header->document_date,
                    'reason_code' => 'OPNAME',
                    'status' => 'POSTED',
                    'notes' => 'Auto generated from stock opname '.$header->number,
                    'posted_by' => $request->user()?->id,
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
                        'created_by' => $request->user()?->id,
                    ]);
                }
            }

            DB::table('stock_opnames')->where('id', $stockOpname)->update([
                'status' => 'POSTED',
                'adjustment_id' => $adjustmentId,
                'posted_by' => $request->user()?->id,
                'posted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Stock opname posted', 'id' => $stockOpname]);
    }

    private function replaceLines(int $entryId, int $warehouseId, array $lines): void
    {
        DB::table('stock_opname_lines')->where('stock_opname_id', $entryId)->delete();

        foreach ($lines as $line) {
            $systemQty = (float) DB::table('stock_balances')
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $line['item_id'])
                ->when(! empty($line['batch_id']), fn ($q) => $q->where('batch_id', $line['batch_id']), fn ($q) => $q->whereNull('batch_id'))
                ->value('on_hand_base') ?: 0;

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
