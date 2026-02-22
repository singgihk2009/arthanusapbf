<?php

namespace App\Http\Controllers\Apps\Outbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InternalUsageRequest;
use App\Services\Inventory\UomConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InternalUsageController extends Controller
{
    public function __construct(private readonly UomConversionService $uomConversionService)
    {
    }

    public function index(): Response
    {
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');

        $entries = DB::table('internal_usages')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (object $entry) use ($warehouseCodes): object {
                $entry->warehouse_label = (string) ($warehouseCodes->get((int) $entry->warehouse_id) ?? '-');

                return $entry;
            });

        return Inertia::render('Apps/Outbound/InternalUsage/Index', [
            'entries' => $entries,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Apps/Outbound/InternalUsage/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
        ]);
    }

    public function store(InternalUsageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $entryId = DB::table('internal_usages')->insertGetId([
                'number' => $this->generateNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'department' => $validated['department'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'document_date' => $validated['document_date'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->replaceLines($entryId, $validated['lines']);
        });

        return to_route('apps.outbound.internal-usage.index')->with('success', 'Internal usage berhasil disimpan.');
    }

    public function edit(int $internalUsage): Response
    {
        $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
        abort_if(! $entry, 404);

        $lines = DB::table('internal_usage_lines')
            ->where('internal_usage_id', $internalUsage)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): array => [
                'item_id' => (string) $line->item_id,
                'batch_id' => $line->batch_id ? (string) $line->batch_id : '',
                'qty_used' => (string) $line->qty_used,
                'uom_id' => (string) $line->uom_id,
                'notes' => (string) ($line->notes ?? ''),
            ]);

        return Inertia::render('Apps/Outbound/InternalUsage/Edit', [
            'entry' => [
                'id' => $entry->id,
                'warehouse_id' => (string) $entry->warehouse_id,
                'document_date' => (string) $entry->document_date,
                'department' => (string) ($entry->department ?? ''),
                'cost_center' => (string) ($entry->cost_center ?? ''),
                'notes' => (string) ($entry->notes ?? ''),
                'status' => (string) $entry->status,
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
        ]);
    }

    public function update(InternalUsageRequest $request, int $internalUsage): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $internalUsage): void {
            $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat diubah.');
            $linked = DB::table('inv_transactions')->where('source_table', 'internal_usages')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            DB::table('internal_usages')->where('id', $internalUsage)->update([
                'warehouse_id' => $validated['warehouse_id'],
                'department' => $validated['department'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'document_date' => $validated['document_date'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->replaceLines($internalUsage, $validated['lines']);
        });

        return to_route('apps.outbound.internal-usage.index')->with('success', 'Internal usage berhasil diperbarui.');
    }

    public function destroy(int $internalUsage): RedirectResponse
    {
        DB::transaction(function () use ($internalUsage): void {
            $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
            abort_if(! $entry, 404);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat dihapus.');
            $linked = DB::table('inv_transactions')->where('source_table', 'internal_usages')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            DB::table('internal_usage_lines')->where('internal_usage_id', $internalUsage)->delete();
            DB::table('internal_usages')->where('id', $internalUsage)->delete();
        });

        return back()->with('success', 'Internal usage berhasil dihapus.');
    }

    private function replaceLines(int $entryId, array $lines): void
    {
        DB::table('internal_usage_lines')->where('internal_usage_id', $entryId)->delete();

        foreach ($lines as $line) {
            $qty = (float) $line['qty_used'];
            $qtyBase = $this->uomConversionService->toBase((int) $line['item_id'], (int) $line['uom_id'], $qty);

            DB::table('internal_usage_lines')->insert([
                'internal_usage_id' => $entryId,
                'item_id' => $line['item_id'],
                'batch_id' => ! empty($line['batch_id']) ? (int) $line['batch_id'] : null,
                'qty_used' => $qty,
                'uom_id' => $line['uom_id'],
                'qty_base' => $qtyBase,
                'notes' => $line['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'IUS';
        $datePart = now()->format('Ymd');
        $lastSequence = DB::table('internal_usages')->where('number', 'like', "$prefix-$datePart-%")->count();
        $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }
}
