<?php

namespace App\Http\Controllers\Apps\Inbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceivingEntryRequest;
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

    public function create(): Response
    {
        return Inertia::render('Apps/Inbound/Receiving/Create', [
            'items' => DB::table('items')->select('id', 'sku', 'name', 'base_uom_id')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'transactionCodes' => ['PEMBELIAN', 'RETUR', 'ADJUSTMENT'],
        ]);
    }

    public function store(ReceivingEntryRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($validated, $userId): void {
            $entryId = $this->insertEntryHeader($validated, $userId);
            $this->replaceEntryLines($entryId, $validated['lines']);
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
            abort_if(($entry->status ?? null) === 'POSTED', 422, 'Dokumen POSTED tidak dapat diubah.');

            $warehouse = DB::table('warehouses')->where('id', $validated['warehouse_id'])->first(['id', 'code']);
            $headerPayload = [
                'transaction_date' => $validated['transaction_date'],
                'transaction_code' => $validated['transaction_code'],
                'reference' => $validated['reference'] ?? null,
                'vendor_name' => $validated['vendor_name'] ?? null,
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

            $this->replaceEntryLines($receivingEntry, $validated['lines']);
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
            abort_if(($entry->status ?? null) === 'POSTED', 422, 'Dokumen POSTED tidak dapat dihapus.');

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

    private function replaceEntryLines(int $entryId, array $lines): void
    {
        $lineForeignKey = $this->resolveLineForeignKeyColumn();
        $batchColumn = $this->resolveBatchColumn();

        DB::table('receiving_entry_lines')->where($lineForeignKey, $entryId)->delete();

        $totalValue = 0;
        foreach ($lines as $line) {
            $qty = (float) $line['qty'];
            $price = (float) $line['price'];
            $value = round($qty * $price, 6);
            $totalValue += $value;

            $linePayload = [
                $lineForeignKey => $entryId,
                'item_id' => $line['item_id'],
                'uom_id' => $line['uom_id'],
                'qty' => $qty,
                'price' => $price,
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
