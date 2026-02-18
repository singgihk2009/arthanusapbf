# Inventory System Blueprint (Laravel + Inertia + MySQL)

Dokumen ini menjelaskan implementasi awal modul inventory laboratorium dengan pendekatan **stock ledger first**: stok tidak diubah manual, semua perubahan berasal dari dokumen transaksi yang diposting.

## 1. Prinsip inti implementasi

- Seluruh dokumen (PO, GRN, Transfer, Sales, Usage, Adjustment, Opname) memiliki status workflow.
- Hanya status `POSTED` / `RECEIVED` yang boleh menghasilkan data ke `stock_ledgers`.
- Seluruh kuantitas stok disimpan dalam `qty_base` (base UOM).
- Snapshot realtime disimpan di `stock_balances` untuk query cepat.

## 2. Struktur tabel (migration)

### Master
- `warehouses`
- `categories`
- `uoms`
- `suppliers`
- `customers`
- `items`
- `item_barcodes`
- `item_uom_conversions`
- `warehouse_item_settings`
- `item_batches`
- `tax_configs`

### Procurement
- `purchase_requisitions` + `purchase_requisition_lines`
- `purchase_orders` + `purchase_order_lines`
- `goods_receipts` + `goods_receipt_lines`

### Outbound / Warehouse Operations
- `warehouse_transfers` + `warehouse_transfer_lines`
- `sales` + `sales_lines`
- `internal_usages` + `internal_usage_lines`
- `stock_adjustments` + `stock_adjustment_lines`
- `stock_opnames` + `stock_opname_lines`

### Ledger / Reporting Core
- `stock_ledgers`
- `stock_balances`
- `document_charges`
- `document_taxes`

## 3. Service layer yang sudah ditambahkan

- `App\Services\Inventory\StockService`
  - method `postMutation(array $payload)` untuk membuat ledger dan trigger event sinkronisasi balance.
- `App\Services\Inventory\UomConversionService`
  - method `toBase(itemId, uomId, qty)` untuk konversi qty input ke base UOM.
- `App\Services\Inventory\BatchAllocationService`
  - method `allocateFefo(warehouseId, itemId, requiredQtyBase)` untuk alokasi FEFO dari `stock_balances`.

## 4. Event / Listener sinkronisasi stock balance

- Event: `App\Events\Inventory\StockLedgerCreated`
- Listener: `App\Listeners\Inventory\UpdateStockBalanceFromLedger`
- Registrasi listener ada di `AppServiceProvider::boot()`.

Flow:
1. Transaksi posting panggil `StockService::postMutation(...)`
2. Ledger baru dibuat di tabel `stock_ledgers`
3. Event `StockLedgerCreated` dipublish
4. Listener upsert + update `stock_balances.on_hand_base`

## 5. Endpoint report yang sudah tersedia

Semua endpoint berada di bawah middleware `auth`:

- `GET /apps/reports/inventory/stock-balance`
  - filter opsional: `warehouse_id`, `item_id`
- `GET /apps/reports/inventory/stock-card`
  - wajib: `warehouse_id`, `item_id`, `start_date`, `end_date`
  - return: opening balance, rows mutasi, closing balance
- `GET /apps/reports/inventory/expired-soon`
  - filter opsional: `warehouse_id`, `days` (default 30)
- `GET /apps/reports/inventory/minimum-stock-alerts`
  - filter opsional: `warehouse_id`

## 6. Cara menjalankan migrasi

### Lokal (SQLite default starter)
1. Pastikan file DB ada:
   ```bash
   touch database/database.sqlite
   ```
2. Atur `.env`:
   ```env
   DB_CONNECTION=sqlite
   DB_DATABASE=/workspace/inventory/database/database.sqlite
   ```
3. Jalankan migrasi:
   ```bash
   php artisan migrate
   ```

### MySQL (sesuai target Ubuntu VPS)
1. Buat database baru, contoh: `inventory_lab`.
2. Atur `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventory_lab
   DB_USERNAME=<user_mysql>
   DB_PASSWORD=<password_mysql>
   ```
3. Jalankan migrasi:
   ```bash
   php artisan migrate
   ```

### Perintah tambahan berguna
- rollback 1 batch:
  ```bash
  php artisan migrate:rollback
  ```
- reset + migrate ulang:
  ```bash
  php artisan migrate:fresh
  ```
- lihat status migration:
  ```bash
  php artisan migrate:status
  ```

## 7. Next step yang direkomendasikan

- Integrasikan service ke controller posting dokumen (GRN, Transfer, Sales, Usage, Adjustment).
- Tambahkan test feature untuk report endpoint.
- Tambahkan permission per endpoint report (mis. `report-stock-balance`, dst).
- Tambahkan UI Inertia untuk report filters + tabel result.
