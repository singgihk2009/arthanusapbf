<?php

namespace App\Http\Controllers\Apps\Inbound;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceivingEntryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ReceivingEntryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Apps/Inbound/Receiving/Index', [
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('name')->get(),
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('name')->get(),
            'transactionCodes' => ['PEMBELIAN', 'RETUR', 'ADJUSTMENT'],
        ]);
    }

    public function store(ReceivingEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($validated, $userId): void {
            $number = $this->generateNumber($validated['transaction_code']);
            $warehouse = DB::table('warehouses')->where('id', $validated['warehouse_id'])->first(['id', 'code', 'name']);

            $entryPayload = [
                'number' => $number,
                'transaction_date' => $validated['transaction_date'],
                'transaction_code' => $validated['transaction_code'],
                'reference' => $validated['reference'] ?? null,
                'vendor_name' => $validated['vendor_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'total_value' => 0,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $warehouseColumn = $this->resolveWarehouseColumn('receiving_entries');
            if ($warehouseColumn) {
                $entryPayload[$warehouseColumn] = $this->resolveWarehouseValue($warehouseColumn, $validated['warehouse_id'], $warehouse?->code);
            }

            $entryId = DB::table('receiving_entries')->insertGetId($this->filterColumns('receiving_entries', $entryPayload));

            $totalValue = 0;
            $linesToInsert = [];
            $lineForeignKey = $this->resolveColumn('receiving_entry_lines', ['receiving_entry_id', 'receiving_id', 'entry_id', 'header_id']) ?? 'receiving_entry_id';
            $batchColumn = $this->resolveColumn('receiving_entry_lines', ['batch_number', 'batch_no', 'no_batch']) ?? 'batch_number';

            foreach ($validated['lines'] as $line) {
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

                $linesToInsert[] = $this->filterColumns('receiving_entry_lines', $linePayload);
            }

            DB::table('receiving_entry_lines')->insert($linesToInsert);
            DB::table('receiving_entries')->where('id', $entryId)->update($this->filterColumns('receiving_entries', [
                'total_value' => round($totalValue, 6),
                'updated_at' => now(),
            ]));
        });

        return back()->with('success', 'Receiving entry berhasil disimpan.');
    }

    private function generateNumber(string $transactionCode): string
    {
        $prefix = match ($transactionCode) {
            'PEMBELIAN' => 'RCV-PBL',
            'RETUR' => 'RCV-RTR',
            default => 'RCV-ADJ',
        };

        $datePart = now()->format('Ymd');
        $lastSequence = DB::table('receiving_entries')
            ->where('number', 'like', "$prefix-$datePart-%")
            ->count();

        $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return "$prefix-$datePart-$sequence";
    }


    private function resolveWarehouseColumn(string $table): ?string
    {
        $column = $this->resolveColumn($table, [
            'warehouse_id',
            'gudang_id',
            'id_warehouse',
            'id_gudang',
            'warehouse_code',
            'kode_gudang',
            'warehouse',
            'gudang',
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
