<?php

namespace App\Http\Controllers\Apps\Inbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceivingEntryRequest;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivingEntryController extends Controller
{
    public function index(): Response
    {
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');

        $entries = DB::table('receiving_entries')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (object $entry) use ($warehouseCodes): object {
                $entry->warehouse_label = $this->resolveEntryWarehouseLabel($entry, $warehouseCodes);

                return $entry;
            });

        return Inertia::render('Apps/Inbound/Receiving/Index', [
            'entries' => $entries,
        ]);
    }

    public function create(\Illuminate\Http\Request $request): Response
    {
        $prefill = null;
        $poId = (int) $request->integer('po_id');

        if ($poId > 0) {
            $purchaseOrder = PurchaseOrder::with(['vendor:id,name', 'items.product:id,base_uom_id', 'items.uom:id'])
                ->find($poId);

            if ($purchaseOrder) {
                $poStatus = strtolower((string) ($purchaseOrder->status ?? ''));
                $isFullyReceived = strtolower((string) ($purchaseOrder->fulfillment_status ?? '')) === 'fully_received' || $poStatus === 'fully_received';
                abort_if(in_array($poStatus, ['cancelled', 'closed'], true) || $isFullyReceived, 422, 'PO tidak bisa direceiving lagi.');

                $prefillLines = $purchaseOrder->items
                    ->filter(fn ($line) => (float) ($line->remaining_qty ?? ($line->qty_ordered - ($line->received_qty ?? $line->qty_received ?? 0))) > 0)
                    ->map(fn ($line): array => [
                        'source_item_id' => (string) $line->id,
                        'item_id' => (string) $line->product_id,
                        'ordered_qty' => (string) $line->qty_ordered,
                        'previously_received_qty' => (string) ($line->received_qty ?? $line->qty_received ?? 0),
                        'remaining_qty' => (string) ($line->remaining_qty ?? ($line->qty_ordered - ($line->received_qty ?? $line->qty_received ?? 0))),
                        'max_qty' => (string) ($line->remaining_qty ?? ($line->qty_ordered - ($line->received_qty ?? $line->qty_received ?? 0))),
                        'qty' => (string) ($line->remaining_qty ?? ($line->qty_ordered - ($line->received_qty ?? $line->qty_received ?? 0))),
                        'uom_id' => (string) ($line->uom_id ?? $line->product?->base_uom_id ?? ''),
                        'price' => (string) $line->unit_price,
                        'batch_number' => '',
                        'expired_date' => '',
                        'notes' => '',
                    ])
                    ->values();

                abort_if($prefillLines->isEmpty(), 422, 'Semua item PO sudah diterima.');

                $prefill = [
                    'transaction_code' => 'PEMBELIAN',
                    'source_type' => 'purchase_order',
                    'source_id' => (string) $purchaseOrder->id,
                    'reference' => (string) ($purchaseOrder->po_number ?? ''),
                    'vendor_name' => (string) ($purchaseOrder->vendor?->name ?? ''),
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'lines' => $prefillLines,
                ];
            }
        }

        return Inertia::render('Apps/Inbound/Receiving/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'transactionCodes' => ['PEMBELIAN', 'RETUR', 'ADJUSTMENT'],
            'prefill' => $prefill,
        ]);
    }

    public function store(ReceivingEntryRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($validated, $userId): void {
            $entryId = $this->insertEntryHeader($validated, $userId);
            $this->replaceEntryLines($entryId, $validated['lines'], $validated);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Receiving entry berhasil disimpan.']);
        }

        return to_route('apps.inbound.receiving.index')->with('success', 'Receiving entry berhasil disimpan.');
    }

    public function edit(int $receivingEntry): Response
    {
        $entry = DB::table('receiving_entries')->where('id', $receivingEntry)->first();
        abort_if(! $entry, 404);

        $lineForeignKey = $this->resolveLineForeignKeyColumn();
        $batchColumn = $this->resolveBatchColumn();

        $lines = DB::table('receiving_entry_lines')
            ->where($lineForeignKey, $receivingEntry)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): array => [
                'item_id' => (string) $line->item_id,
                'qty' => (string) $line->qty,
                'uom_id' => (string) $line->uom_id,
                'price' => (string) $line->price,
                'batch_number' => (string) ($line->{$batchColumn} ?? ''),
                'expired_date' => $line->expired_date ? (string) $line->expired_date : '',
                'notes' => (string) ($line->notes ?? ''),
            ]);

        return Inertia::render('Apps/Inbound/Receiving/Edit', [
            'entry' => [
                'id' => $entry->id,
                'warehouse_id' => $this->resolveEntryWarehouseId($entry),
                'transaction_date' => (string) $entry->transaction_date,
                'transaction_code' => (string) $entry->transaction_code,
                'reference' => (string) ($entry->reference ?? ''),
                'vendor_name' => (string) ($entry->vendor_name ?? ''),
                'notes' => (string) ($entry->notes ?? ''),
            ],
            'lines' => $lines,
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'transactionCodes' => ['PEMBELIAN', 'RETUR', 'ADJUSTMENT'],
        ]);
    }

    public function update(ReceivingEntryRequest $request, int $receivingEntry): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $receivingEntry): void {
            $entry = DB::table('receiving_entries')->where('id', $receivingEntry)->first();
            abort_if(! $entry, 404);
            abort_if(strtolower((string) ($entry->status ?? '')) === 'posted', 422, 'Dokumen POSTED tidak dapat diubah.');

            $warehouse = DB::table('warehouses')->where('id', $validated['warehouse_id'])->first(['id', 'code']);
            $headerPayload = [
                'transaction_date' => $validated['transaction_date'],
                'transaction_code' => $validated['transaction_code'],
                'reference' => $validated['reference'] ?? null,
                'vendor_name' => $validated['vendor_name'] ?? null,
            'vendor_id' => $validated['vendor_id'] ?? null,
            'source_type' => $validated['source_type'] ?? null,
            'source_id' => $validated['source_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ];

            $warehouseColumn = $this->resolveWarehouseColumn('receiving_entries');
            if ($warehouseColumn) {
                $headerPayload[$warehouseColumn] = $this->resolveWarehouseValue($warehouseColumn, $validated['warehouse_id'], $warehouse?->code);
            }

            DB::table('receiving_entries')
                ->where('id', $receivingEntry)
                ->update($this->filterColumns('receiving_entries', $headerPayload));

            $this->replaceEntryLines($receivingEntry, $validated['lines'], $validated);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Receiving entry berhasil diperbarui.']);
        }

        return to_route('apps.inbound.receiving.index')->with('success', 'Receiving entry berhasil diperbarui.');
    }

    public function destroy(int $receivingEntry): RedirectResponse
    {
        DB::transaction(function () use ($receivingEntry): void {
            $entry = DB::table('receiving_entries')->where('id', $receivingEntry)->first();
            abort_if(! $entry, 404);
            abort_if(strtolower((string) ($entry->status ?? '')) === 'posted', 422, 'Dokumen POSTED tidak dapat dihapus.');

            DB::table('stock_ledgers')
                ->where('trx_type', 'RCV_IN')
                ->where('trx_id', $receivingEntry)
                ->delete();

            $lineForeignKey = $this->resolveLineForeignKeyColumn();
            DB::table('receiving_entry_lines')->where($lineForeignKey, $receivingEntry)->delete();
            DB::table('receiving_entries')->where('id', $receivingEntry)->delete();
        });

        return back()->with('success', 'Receiving entry berhasil dihapus.');
    }

    public function exportExcel(): StreamedResponse
    {
        $warehouseCodes = DB::table('warehouses')->pluck('code', 'id');
        $rows = DB::table('receiving_entries')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows, $warehouseCodes): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Number', 'Tanggal', 'Kode Transaksi', 'Warehouse', 'Referensi', 'Vendor', 'Total Value', 'Catatan']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->number,
                    $row->transaction_date,
                    $row->transaction_code,
                    $this->resolveEntryWarehouseLabel($row, $warehouseCodes),
                    $row->reference,
                    $row->vendor_name,
                    (float) $row->total_value,
                    $row->notes,
                ]);
            }

            fclose($output);
        }, 'receiving-entries-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function insertEntryHeader(array $validated, ?int $userId): int
    {
        $number = $this->generateNumber($validated['transaction_code']);
        $warehouse = DB::table('warehouses')->where('id', $validated['warehouse_id'])->first(['id', 'code']);

        $entryPayload = [
            'number' => $number,
            'transaction_date' => $validated['transaction_date'],
            'transaction_code' => $validated['transaction_code'],
            'reference' => $validated['reference'] ?? null,
            'vendor_name' => $validated['vendor_name'] ?? null,
            'vendor_id' => $validated['vendor_id'] ?? null,
            'source_type' => $validated['source_type'] ?? null,
            'source_id' => $validated['source_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'total_value' => 0,
            'status' => 'DRAFT',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $warehouseColumn = $this->resolveWarehouseColumn('receiving_entries');
        if ($warehouseColumn) {
            $entryPayload[$warehouseColumn] = $this->resolveWarehouseValue($warehouseColumn, $validated['warehouse_id'], $warehouse?->code);
        }

        return DB::table('receiving_entries')->insertGetId($this->filterColumns('receiving_entries', $entryPayload));
    }

    private function replaceEntryLines(int $entryId, array $lines, array $header = []): void
    {
        $lineForeignKey = $this->resolveLineForeignKeyColumn();
        $batchColumn = $this->resolveBatchColumn();

        DB::table('receiving_entry_lines')->where($lineForeignKey, $entryId)->delete();

        $totalValue = 0;
        $isPoSource = ($header['source_type'] ?? null) === 'purchase_order';
        $poItemsById = collect();
        if ($isPoSource) {
            abort_unless(! empty($header['source_id']), 422, 'source_id wajib untuk receiving dari PO.');
            $poItemsById = DB::table('purchase_order_items')->where('purchase_order_id', $header['source_id'])->get()->keyBy('id');
        }

        foreach ($lines as $line) {
            $sourceItemId = null;
            $previouslyReceived = 0;
            $remainingQty = null;

            if ($isPoSource) {
                $sourceItemId = (int) ($line['source_item_id'] ?? 0);
                abort_if($sourceItemId <= 0, 422, 'source_item_id wajib untuk setiap item PO.');
                $poItem = $poItemsById->get($sourceItemId);
                abort_if(! $poItem, 422, 'Item PO tidak valid untuk source yang dipilih.');
                abort_if((int) $line['item_id'] !== (int) $poItem->product_id, 422, 'Produk receiving harus sama dengan produk PO item.');

                $orderedQty = (float) $poItem->qty_ordered;
                $previouslyReceived = (float) ($poItem->received_qty ?? $poItem->qty_received ?? 0);
                $remainingQty = max(0, $orderedQty - $previouslyReceived);
                $qty = (float) $line['qty'];
                abort_if($qty <= 0 || $qty > $remainingQty, 422, 'Qty receiving melebihi sisa qty PO.');
                $price = (float) $poItem->unit_price;
            } else {
                $qty = (float) $line['qty'];
                $price = (float) $line['price'];
            }

            $value = round($qty * $price, 6);
            $totalValue += $value;

            $linePayload = [
                $lineForeignKey => $entryId,
                'source_item_id' => $sourceItemId,
                'item_id' => $line['item_id'],
                'uom_id' => $line['uom_id'],
                'qty' => $qty,
                'price' => $price,
                'previously_received_qty' => $previouslyReceived,
                'remaining_qty' => $remainingQty,
                'inventory_unit_cost' => $price,
                'inventory_total_cost' => $value,
                'value' => $value,
                $batchColumn => $line['batch_number'] ?? null,
                'expired_date' => $line['expired_date'] ?? null,
                'notes' => $line['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('receiving_entry_lines')->insert($this->filterColumns('receiving_entry_lines', $linePayload));
        }

        DB::table('receiving_entries')->where('id', $entryId)->update($this->filterColumns('receiving_entries', [
            'total_value' => round($totalValue, 6),
            'updated_at' => now(),
        ]));
    }

    private function resolveLineForeignKeyColumn(): string
    {
        $column = $this->resolveColumn('receiving_entry_lines', ['receiving_entry_id', 'receiving_id', 'entry_id', 'header_id']);

        if ($column) {
            return $column;
        }

        foreach (Schema::getColumnListing('receiving_entry_lines') as $candidate) {
            if (preg_match('/(receiving|entry).*(id)/i', $candidate) === 1) {
                return $candidate;
            }
        }

        return 'receiving_entry_id';
    }

    private function resolveBatchColumn(): string
    {
        return $this->resolveColumn('receiving_entry_lines', ['batch_number', 'batch_no', 'no_batch']) ?? 'batch_number';
    }

    private function resolveEntryWarehouseId(object $entry): int
    {
        if (property_exists($entry, 'warehouse_id') && ! empty($entry->warehouse_id)) {
            return (int) $entry->warehouse_id;
        }

        foreach (['gudang_id', 'id_gudang', 'id_warehouse'] as $candidate) {
            if (property_exists($entry, $candidate) && ! empty($entry->{$candidate})) {
                return (int) $entry->{$candidate};
            }
        }

        $warehouseCode = null;
        foreach (['warehouse_code', 'kode_gudang', 'warehouse', 'gudang'] as $candidate) {
            if (property_exists($entry, $candidate) && ! empty($entry->{$candidate})) {
                $warehouseCode = $entry->{$candidate};
                break;
            }
        }

        if (! $warehouseCode) {
            return 0;
        }

        return (int) DB::table('warehouses')->where('code', $warehouseCode)->value('id');
    }

    private function generateNumber(string $transactionCode): string
    {
        $prefix = match ($transactionCode) {
            'PEMBELIAN' => 'RCV-PBL',
            'RETUR' => 'RCV-RTR',
            default => 'RCV-ADJ',
        };

        $datePart = now()->format('Ymd');
        $maxSequence = DB::table('receiving_entries')
            ->where('number', 'like', "$prefix-$datePart-%")
            ->pluck('number')
            ->map(function (string $number): int {
                $parts = explode('-', $number);
                $suffix = end($parts);

                return ctype_digit((string) $suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        $sequence = str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }

    private function resolveWarehouseColumn(string $table): ?string
    {
        $column = $this->resolveColumn($table, [
            'warehouse_id', 'gudang_id', 'id_warehouse', 'id_gudang', 'warehouse_code', 'kode_gudang', 'warehouse', 'gudang',
        ]);

        if ($column) {
            return $column;
        }

        foreach (Schema::getColumnListing($table) as $candidate) {
            if (preg_match('/(warehouse|gudang)/i', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveWarehouseValue(string $column, int $warehouseId, ?string $warehouseCode): int|string|null
    {
        if (str_contains($column, 'code') || str_contains($column, 'kode')) {
            return $warehouseCode;
        }

        if (str_contains($column, '_id') || str_starts_with($column, 'id_')) {
            return $warehouseId;
        }

        return $warehouseCode ?? $warehouseId;
    }

    private function resolveEntryWarehouseLabel(object $entry, Collection $warehouseCodes): string
    {
        if (property_exists($entry, 'warehouse_id') && $entry->warehouse_id) {
            return (string) ($warehouseCodes->get((int) $entry->warehouse_id) ?? $entry->warehouse_id);
        }

        foreach (['warehouse_code', 'kode_gudang', 'warehouse', 'gudang'] as $candidate) {
            if (property_exists($entry, $candidate) && ! empty($entry->{$candidate})) {
                return (string) $entry->{$candidate};
            }
        }

        foreach (['gudang_id', 'id_gudang', 'id_warehouse'] as $candidate) {
            if (property_exists($entry, $candidate) && ! empty($entry->{$candidate})) {
                return (string) ($warehouseCodes->get((int) $entry->{$candidate}) ?? $entry->{$candidate});
            }
        }

        return '-';
    }

    private function resolveColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if ($this->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function filterColumns(string $table, array $payload): array
    {
        $validColumns = array_flip(Schema::getColumnListing($table));

        return array_filter(
            $payload,
            fn (string $column): bool => isset($validColumns[$column]),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
