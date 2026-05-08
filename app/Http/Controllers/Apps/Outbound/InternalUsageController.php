<?php

namespace App\Http\Controllers\Apps\Outbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InternalUsageRequest;
use App\Services\WarehouseAccessService;
use App\Services\Inventory\UomConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InternalUsageController extends Controller
{
    private const TRANSACTION_CODE_OPTIONS = [
        ['value' => 'PENJUALAN', 'label' => 'Penjualan'],
        ['value' => 'RETUR', 'label' => 'Retur'],
        ['value' => 'DAMAGED', 'label' => 'Damaged'],
        ['value' => 'SAMPLE', 'label' => 'Sample'],
        ['value' => 'INTERNAL_USE', 'label' => 'Internal Use'],
    ];

    public function __construct(
        private readonly UomConversionService $uomConversionService,
        private readonly WarehouseAccessService $warehouseAccessService
    )
    {
    }

    public function index(): Response
    {
        $user = auth()->user();
        abort_if(! $user, 401);
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');

        $query = DB::table('internal_usages');
        $this->warehouseAccessService->scopeInventoryQuery($query, $user);

        $entries = $query
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
        $user = auth()->user();
        abort_if(! $user, 401);
        $allowedWarehouseIds = $this->warehouseAccessService->getAllowedWarehouseIds($user);

        return Inertia::render('Apps/Outbound/InternalUsage/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->whereIn('id', $allowedWarehouseIds)->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
            'transactionCodes' => self::TRANSACTION_CODE_OPTIONS,
        ]);
    }

    public function store(InternalUsageRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->warehouseAccessService->assertWarehouseAccess($request->user(), $validated['warehouse_id']);

        DB::transaction(function () use ($validated): void {
            $entryId = DB::table('internal_usages')->insertGetId([
                'number' => $this->generateNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'transaction_code' => $validated['transaction_code'],
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

        return to_route('apps.outbound.internal-usage.index')->with('success', 'Dispatch berhasil disimpan.');
    }

    public function edit(int $internalUsage): Response
    {
        $user = auth()->user();
        abort_if(! $user, 401);
        $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
        abort_if(! $entry, 404);
        $this->warehouseAccessService->assertWarehouseAccess($user, $entry->warehouse_id);
        $allowedWarehouseIds = $this->warehouseAccessService->getAllowedWarehouseIds($user);

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
                'transaction_code' => (string) ($entry->transaction_code ?? ''),
                'notes' => (string) ($entry->notes ?? ''),
                'status' => (string) $entry->status,
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->whereIn('id', $allowedWarehouseIds)->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
            'transactionCodes' => self::TRANSACTION_CODE_OPTIONS,
        ]);
    }

    public function update(InternalUsageRequest $request, int $internalUsage): RedirectResponse
    {
        $validated = $request->validated();
        $this->warehouseAccessService->assertWarehouseAccess($request->user(), $validated['warehouse_id']);

        DB::transaction(function () use ($validated, $internalUsage): void {
            $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
            abort_if(! $entry, 404);
            $this->warehouseAccessService->assertWarehouseAccess($request->user(), $entry->warehouse_id);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat diubah.');
            $linked = DB::table('inv_transactions')->where('source_table', 'internal_usages')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            DB::table('internal_usages')->where('id', $internalUsage)->update([
                'warehouse_id' => $validated['warehouse_id'],
                'transaction_code' => $validated['transaction_code'],
                'department' => $validated['department'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'document_date' => $validated['document_date'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->replaceLines($internalUsage, $validated['lines']);
        });

        return to_route('apps.outbound.internal-usage.index')->with('success', 'Dispatch berhasil diperbarui.');
    }

    public function destroy(int $internalUsage): RedirectResponse
    {
        $user = auth()->user();
        abort_if(! $user, 401);
        DB::transaction(function () use ($internalUsage): void {
            $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
            abort_if(! $entry, 404);
            $this->warehouseAccessService->assertWarehouseAccess(auth()->user(), $entry->warehouse_id);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat dihapus.');
            $linked = DB::table('inv_transactions')->where('source_table', 'internal_usages')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            DB::table('stock_ledgers')
                ->where('trx_type', 'USAGE_OUT')
                ->where('trx_id', $internalUsage)
                ->delete();

            DB::table('internal_usage_lines')->where('internal_usage_id', $internalUsage)->delete();
            DB::table('internal_usages')->where('id', $internalUsage)->delete();
        });

        return back()->with('success', 'Dispatch berhasil dihapus.');
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
