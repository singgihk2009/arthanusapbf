<?php

namespace App\Http\Controllers\Apps\Transfer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\WarehouseTransferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseTransferController extends Controller
{
    public function index(): Response
    {
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');

        $entries = DB::table('warehouse_transfers')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (object $entry) use ($warehouseCodes): object {
                $entry->from_warehouse_label = (string) ($warehouseCodes->get((int) $entry->from_warehouse_id) ?? '-');
                $entry->to_warehouse_label = (string) ($warehouseCodes->get((int) $entry->to_warehouse_id) ?? '-');

                return $entry;
            });

        return Inertia::render('Apps/Transfer/WarehouseTransfer/Index', [
            'entries' => $entries,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Apps/Transfer/WarehouseTransfer/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
        ]);
    }

    public function store(WarehouseTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $entryId = DB::table('warehouse_transfers')->insertGetId([
                'number' => $this->generateNumber(),
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'document_date' => $validated['document_date'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->replaceLines($entryId, $validated['lines']);
        });

        return to_route('apps.transfer.warehouse.index')->with('success', 'Transfer antar gudang berhasil disimpan.');
    }

    public function edit(int $warehouseTransfer): Response
    {
        $entry = DB::table('warehouse_transfers')->where('id', $warehouseTransfer)->first();
        abort_if(! $entry, 404);

        $lines = DB::table('warehouse_transfer_lines')
            ->where('warehouse_transfer_id', $warehouseTransfer)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): array => [
                'item_id' => (string) $line->item_id,
                'batch_id' => $line->batch_id ? (string) $line->batch_id : '',
                'qty_requested' => (string) $line->qty_requested,
                'uom_id' => (string) $line->uom_id,
            ]);

        return Inertia::render('Apps/Transfer/WarehouseTransfer/Edit', [
            'entry' => [
                'id' => $entry->id,
                'from_warehouse_id' => (string) $entry->from_warehouse_id,
                'to_warehouse_id' => (string) $entry->to_warehouse_id,
                'document_date' => (string) $entry->document_date,
                'notes' => (string) ($entry->notes ?? ''),
                'status' => (string) $entry->status,
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
        ]);
    }

    public function update(WarehouseTransferRequest $request, int $warehouseTransfer): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $warehouseTransfer): void {
            $entry = DB::table('warehouse_transfers')->where('id', $warehouseTransfer)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'RECEIVED', 422, 'Dokumen yang sudah diposting tidak dapat diubah.');

            DB::table('warehouse_transfers')->where('id', $warehouseTransfer)->update([
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'document_date' => $validated['document_date'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->replaceLines($warehouseTransfer, $validated['lines']);
        });

        return to_route('apps.transfer.warehouse.index')->with('success', 'Transfer antar gudang berhasil diperbarui.');
    }

    public function destroy(int $warehouseTransfer): RedirectResponse
    {
        DB::transaction(function () use ($warehouseTransfer): void {
            $entry = DB::table('warehouse_transfers')->where('id', $warehouseTransfer)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'RECEIVED', 422, 'Dokumen yang sudah diposting tidak dapat dihapus.');

            DB::table('warehouse_transfer_lines')->where('warehouse_transfer_id', $warehouseTransfer)->delete();
            DB::table('warehouse_transfers')->where('id', $warehouseTransfer)->delete();
        });

        return back()->with('success', 'Transfer antar gudang berhasil dihapus.');
    }

    private function replaceLines(int $entryId, array $lines): void
    {
        DB::table('warehouse_transfer_lines')->where('warehouse_transfer_id', $entryId)->delete();

        foreach ($lines as $line) {
            $qty = (float) $line['qty_requested'];

            DB::table('warehouse_transfer_lines')->insert([
                'warehouse_transfer_id' => $entryId,
                'item_id' => $line['item_id'],
                'batch_id' => $line['batch_id'] ?? null,
                'qty_requested' => $qty,
                'uom_id' => $line['uom_id'],
                'qty_base' => $qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'WTR';
        $datePart = now()->format('Ymd');
        $lastSequence = DB::table('warehouse_transfers')->where('number', 'like', "$prefix-$datePart-%")->count();
        $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }
}
