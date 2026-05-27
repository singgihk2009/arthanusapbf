<?php

namespace App\Http\Controllers\Apps\Outbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InternalUsageRequest;
use App\Services\WarehouseAccessService;
use App\Models\Sales\Sale;
use App\Services\Inventory\UomConversionService;
use App\Models\Inventory\FacilityScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'facilitySchemes' => $this->getFacilitySchemes(),
            'transactionCodes' => self::TRANSACTION_CODE_OPTIONS,
        ]);
    }


    public function createFromSalesOrder(Sale $salesOrder): Response
    {
        $user = auth()->user();
        abort_if(! $user, 401);

        $status = strtolower((string) $salesOrder->status);
        abort_if(! in_array($status, ['approved', 'partially_shipped'], true), 422, 'Sales Order must be approved before shipment.');

        $salesOrder->load(['customer', 'lines.item', 'lines.uom', 'lines.facilityScheme']);
        $lines = $salesOrder->lines->map(function ($line) {
            $remaining = max(0, (float) $line->qty_sold - (float) $line->qty_shipped);
            if ($remaining <= 0) return null;
            return [
                'sale_line_id' => $line->id,
                'source_line_id' => $line->id,
                'item_id' => (string) $line->item_id,
                'batch_id' => '',
                'qty_used' => (string) $remaining,
                'uom_id' => (string) $line->uom_id,
                'notes' => '',
                'qty_ordered' => (float) $line->qty_sold,
                'qty_already_shipped' => (float) $line->qty_shipped,
                'qty_remaining' => $remaining,
            ];
        })->filter()->values();

        abort_if($lines->isEmpty(), 422, 'All items in this Sales Order have already been shipped.');

        $allowedWarehouseIds = $this->warehouseAccessService->getAllowedWarehouseIds($user);
        $customer = $salesOrder->customer;

        return Inertia::render('Apps/Outbound/InternalUsage/Create', [
            'mode' => 'sales_shipment',
            'source' => ['type' => 'sales_order', 'id' => $salesOrder->id, 'number' => $salesOrder->number],
            'customer' => $customer ? ['id'=>$customer->id,'customer_code'=>$customer->customer_code,'customer_name'=>$customer->customer_name,'address'=>$customer->address,'phone'=>$customer->phone,'npwp'=>$customer->npwp] : null,
            'dispatchDefaults' => [
                'warehouse_id' => (string) $salesOrder->warehouse_id,
                'document_date' => now()->toDateString(),
                'transaction_code' => 'PENJUALAN',
                'sender_receiver_name' => (string) ($customer->customer_name ?? ''),
                'department' => 'Sales',
                'cost_center' => '',
                'notes' => 'Shipment for Sales Order '.$salesOrder->number.' - '.($customer->customer_name ?? ''),
                'source_type' => 'sales_order','source_id' => $salesOrder->id,'source_number' => $salesOrder->number,'customer_id' => $customer->id ?? null,'sale_id' => $salesOrder->id,
            ],
            'prefilledLines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->whereIn('id', $allowedWarehouseIds)->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
            'facilitySchemes' => $this->getFacilitySchemes(),
            'transactionCodes' => self::TRANSACTION_CODE_OPTIONS,
        ]);
    }

    public function store(InternalUsageRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->warehouseAccessService->assertWarehouseAccess($request->user(), $validated['warehouse_id']);

        DB::transaction(function () use ($validated): void {
            $entryPayload = [
                'number' => $this->generateNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'facility_scheme_id' => $validated['facility_scheme_id'],
                'transaction_code' => $validated['transaction_code'],
                'outbound_number' => $validated['outbound_number'] ?? null,
                'sender_receiver_name' => $validated['sender_receiver_name'] ?? null,
                'department' => $validated['department'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'document_date' => $validated['document_date'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
                'source_type' => $validated['source_type'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'source_number' => $validated['source_number'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'sale_id' => $validated['sale_id'] ?? null,
            ];
            $entryPayload = $this->appendDispatchHeaderPayload($entryPayload, $validated);
            $entryId = DB::table('internal_usages')->insertGetId($entryPayload);

            $this->replaceLines($entryId, $validated['lines']);
        });

        return to_route('apps.outbound.internal-usage.index')->with('success', 'Dispatch berhasil disimpan.');
    }

    public function edit(Request $request, int $internalUsage): Response
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

        $isPosted = strtoupper((string) $entry->status) === 'POSTED';
        $viewOnly = $isPosted || $request->boolean('view');

        return Inertia::render('Apps/Outbound/InternalUsage/Edit', [
            'entry' => [
                'id' => $entry->id,
                'warehouse_id' => (string) $entry->warehouse_id,
                'facility_scheme_id' => (string) ($entry->facility_scheme_id ?? ''),
                'document_date' => (string) $entry->document_date,
                'outbound_number' => (string) ($entry->outbound_number ?? ''),
                'sender_receiver_name' => (string) ($entry->sender_receiver_name ?? ''),
                'department' => (string) ($entry->department ?? ''),
                'cost_center' => (string) ($entry->cost_center ?? ''),
                'transaction_code' => (string) ($entry->transaction_code ?? ''),
                'notes' => (string) ($entry->notes ?? ''),
                'status' => (string) $entry->status,
                'sale_id' => $entry->sale_id ? (int) $entry->sale_id : null,
                'source_id' => $entry->source_id ? (int) $entry->source_id : null,
                'source_type' => (string) ($entry->source_type ?? ''),
                'source_number' => (string) ($entry->source_number ?? ''),
                'view_only' => $viewOnly,
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->whereIn('id', $allowedWarehouseIds)->orderBy('name')->get(),
            'batches' => DB::table('item_batches')->select('id', 'item_id', 'batch_no', 'expired_date')->orderBy('batch_no')->get(),
            'facilitySchemes' => $this->getFacilitySchemes(),
            'transactionCodes' => self::TRANSACTION_CODE_OPTIONS,
        ]);
    }

    public function update(InternalUsageRequest $request, int $internalUsage): RedirectResponse
    {
        $validated = $request->validated();
        $this->warehouseAccessService->assertWarehouseAccess($request->user(), $validated['warehouse_id']);

        DB::transaction(function () use ($validated, $internalUsage, $request): void {
            $entry = DB::table('internal_usages')->where('id', $internalUsage)->first();
            abort_if(! $entry, 404);
            $this->warehouseAccessService->assertWarehouseAccess($request->user(), $entry->warehouse_id);
            abort_if($entry->status === 'POSTED', 422, 'Dokumen POSTED tidak dapat diubah.');
            $linked = DB::table('inv_transactions')->where('source_table', 'internal_usages')->where('source_id', $entry->id)->exists();
            abort_if($linked, 422, 'Dokumen sudah terikat GL, gunakan reversal/adjustment.');

            $entryPayload = [
                'warehouse_id' => $validated['warehouse_id'],
                'facility_scheme_id' => $validated['facility_scheme_id'],
                'transaction_code' => $validated['transaction_code'],
                'outbound_number' => $validated['outbound_number'] ?? null,
                'sender_receiver_name' => $validated['sender_receiver_name'] ?? null,
                'department' => $validated['department'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'document_date' => $validated['document_date'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
                'source_type' => $validated['source_type'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'source_number' => $validated['source_number'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'sale_id' => $validated['sale_id'] ?? null,
            ];
            $entryPayload = $this->appendDispatchHeaderPayload($entryPayload, $validated);
            DB::table('internal_usages')->where('id', $internalUsage)->update($entryPayload);

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
                'sale_line_id' => $line['sale_line_id'] ?? null,
                'source_line_id' => $line['source_line_id'] ?? null,
                'qty_ordered' => $line['qty_ordered'] ?? null,
                'qty_already_shipped' => $line['qty_already_shipped'] ?? 0,
                'qty_remaining' => $line['qty_remaining'] ?? null,
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

    private function appendDispatchHeaderPayload(array $payload, array $validated): array
    {
        if (Schema::hasColumn('internal_usages', 'facility_scheme_id')) {
            $payload['facility_scheme_id'] = $validated['facility_scheme_id'] ?? null;
        }

        if (Schema::hasColumn('internal_usages', 'outbound_number')) {
            $payload['outbound_number'] = $validated['outbound_number'] ?? null;
        }

        if (Schema::hasColumn('internal_usages', 'sender_receiver_name')) {
            $payload['sender_receiver_name'] = $validated['sender_receiver_name'] ?? null;
        }

        return $payload;
    }

    private function getFacilitySchemes()
    {
        if (! Schema::hasTable('facility_schemes')) {
            return collect();
        }

        return FacilityScheme::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
