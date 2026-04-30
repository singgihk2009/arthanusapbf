<?php

namespace App\Http\Controllers\Apps\Outbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function index(): Response
    {
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');

        $entries = DB::table('stock_adjustments')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (object $entry) use ($warehouseCodes): object {
                $entry->warehouse_label = (string) ($warehouseCodes->get((int) $entry->warehouse_id) ?? '-');

                return $entry;
            });

        return Inertia::render('Apps/Outbound/StockAdjustment/Index', [
            'entries' => $entries,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Apps/Outbound/StockAdjustment/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no')->orderBy('batch_no')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(StockAdjustmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $entryId = DB::table('stock_adjustments')->insertGetId([
                'number' => $this->generateNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'document_date' => $validated['document_date'],
                'reason_code' => $validated['reason_code'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->replaceLines($entryId, $validated['lines']);
        });

        return to_route('apps.outbound.stock-adjustment.index')->with('success', 'Stock adjustment berhasil disimpan.');
    }

    public function edit(int $stockAdjustment): Response
    {
        $entry = DB::table('stock_adjustments')->where('id', $stockAdjustment)->first();
        abort_if(! $entry, 404);

        $lines = DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $stockAdjustment)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): array => [
                'item_id' => (string) $line->item_id,
                'batch_id' => $line->batch_id ? (string) $line->batch_id : '',
                'qty_adjusted' => (string) $line->qty_adjusted,
                'uom_id' => (string) $line->uom_id,
                'notes' => (string) ($line->notes ?? ''),
            ]);

        return Inertia::render('Apps/Outbound/StockAdjustment/Edit', [
            'entry' => [
                'id' => $entry->id,
                'warehouse_id' => (string) $entry->warehouse_id,
                'document_date' => (string) $entry->document_date,
                'reason_code' => (string) $entry->reason_code,
                'notes' => (string) ($entry->notes ?? ''),
                'status' => (string) $entry->status,
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no')->orderBy('batch_no')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(StockAdjustmentRequest $request, int $stockAdjustment): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $stockAdjustment): void {
            $entry = DB::table('stock_adjustments')->where('id', $stockAdjustment)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat diubah.');
            $linked = DB::table('inv_transactions')->where('source_table', 'stock_adjustments')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            DB::table('stock_adjustments')->where('id', $stockAdjustment)->update([
                'warehouse_id' => $validated['warehouse_id'],
                'document_date' => $validated['document_date'],
                'reason_code' => $validated['reason_code'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->replaceLines($stockAdjustment, $validated['lines']);
        });

        return to_route('apps.outbound.stock-adjustment.index')->with('success', 'Stock adjustment berhasil diperbarui.');
    }

    public function destroy(int $stockAdjustment): RedirectResponse
    {
        DB::transaction(function () use ($stockAdjustment): void {
            $entry = DB::table('stock_adjustments')->where('id', $stockAdjustment)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat dihapus.');
            $linked = DB::table('inv_transactions')->where('source_table', 'stock_adjustments')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            DB::table('stock_adjustment_lines')->where('stock_adjustment_id', $stockAdjustment)->delete();
            DB::table('stock_adjustments')->where('id', $stockAdjustment)->delete();
        });

        return back()->with('success', 'Stock adjustment berhasil dihapus.');
    }

    private function replaceLines(int $entryId, array $lines): void
    {
        DB::table('stock_adjustment_lines')->where('stock_adjustment_id', $entryId)->delete();

        foreach ($lines as $line) {
            $qty = (float) $line['qty_adjusted'];

            DB::table('stock_adjustment_lines')->insert([
                'stock_adjustment_id' => $entryId,
                'item_id' => $line['item_id'],
                'batch_id' => $line['batch_id'] ?: null,
                'qty_adjusted' => $qty,
                'uom_id' => $line['uom_id'],
                'qty_base' => $qty,
                'notes' => $line['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'ADJ';
        $datePart = now()->format('Ymd');
        $lastSequence = DB::table('stock_adjustments')->where('number', 'like', "$prefix-$datePart-%")->count();
        $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }
}
