# Inventory System Blueprint (Laravel + Inertia + MySQL)

Dokumen ini menjelaskan implementasi awal modul inventory laboratorium dengan pendekatan **stock ledger first**: stok tidak diubah manual, semua perubahan berasal dari dokumen transaksi yang diposting.

## 1. Prinsip inti implementasi

- Seluruh dokumen (PO, GRN, Transfer, Sales, Usage, Adjustment, Opname) memiliki status workflow.
- Hanya status `POSTED` / `RECEIVED` yang boleh menghasilkan data ke `stock_ledgers`.
- Seluruh kuantitas stok disimpan dalam `qty_base` (base UOM).
- Snapshot realtime disimpan di `stock_balances` untuk query cepat.

## 2. Struktur tabel (migration)

### Master
- `warehouses`, `categories`, `uoms`, `suppliers`, `customers`
- `items`, `item_barcodes`, `item_uom_conversions`, `warehouse_item_settings`
- `item_batches`, `tax_configs`

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

- `StockService::postMutation(array $payload)`
- `UomConversionService::toBase(itemId, uomId, qty)`
- `BatchAllocationService::allocateFefo(warehouseId, itemId, requiredQtyBase)`

## 4. Event / Listener sinkronisasi stock balance

- Event: `App\Events\Inventory\StockLedgerCreated`
- Listener: `App\Listeners\Inventory\UpdateStockBalanceFromLedger`
- Registrasi listener: `AppServiceProvider::boot()`

## 5. Integrasi posting dokumen (sudah diimplementasikan)

Controller: `App\Http\Controllers\Apps\InventoryPostingController`

Endpoint (auth + permission):
- `POST /apps/inventory/posting/grn/{goodsReceipt}`
- `POST /apps/inventory/posting/transfer/{transferId}`
- `POST /apps/inventory/posting/sale/{saleId}`
- `POST /apps/inventory/posting/usage/{usageId}`
- `POST /apps/inventory/posting/adjustment/{adjustmentId}`

Ringkas flow:
1. Validasi dokumen ada dan belum `POSTED`/`RECEIVED`.
2. Resolve `qty_base` (pakai nilai existing line atau konversi UOM bila kosong).
3. Buat ledger IN/OUT sesuai jenis transaksi.
4. Untuk sale item expired-tracked: alokasi batch FEFO.
5. Update status dokumen ke posted.

## 6. Endpoint report API + permission

Controller: `App\Http\Controllers\Apps\Reports\InventoryReportController`

- `GET /apps/reports/inventory/stock-balance` → `permission:report-stock-balance`
- `GET /apps/reports/inventory/stock-card` → `permission:report-stock-card`
- `GET /apps/reports/inventory/expired-soon` → `permission:report-expired-soon`
- `GET /apps/reports/inventory/minimum-stock-alerts` → `permission:report-minimum-stock-alerts`

## 7. UI Inertia report (sudah ditambahkan)

- Route halaman: `GET /apps/reports/inventory` (`permission:inventory-reports-access`)
- Controller: `InventoryReportPageController`
- Page: `resources/js/Pages/Apps/Reports/Inventory/Index.jsx`
- Fitur UI:
  - filter tipe report
  - filter gudang, item, date range, dan days
  - tabel hasil report dinamis
  - ringkasan opening/closing balance untuk stock card

## 8. Seeder permission (ditambahkan)

Permission tambahan:
- `inventory-reports-access`
- `report-stock-balance`, `report-stock-card`, `report-expired-soon`, `report-minimum-stock-alerts`
- `inventory-posting-grn`, `inventory-posting-transfer`, `inventory-posting-sale`, `inventory-posting-usage`, `inventory-posting-adjustment`

Role tambahan:
- `inventory-reports-access` (permission report + page access)
- `inventory-posting-access`

## 9. Feature test report (ditambahkan)

File test:
- `tests/Feature/Inventory/InventoryReportEndpointsTest.php`

Cakupan test:
- endpoint stock balance
- endpoint stock card (termasuk running/closing balance)
- endpoint minimum stock alerts

## 10. Cara menjalankan migrasi

### Lokal (SQLite default starter)
1. `touch database/database.sqlite`
2. `.env`:
   ```env
   DB_CONNECTION=sqlite
   DB_DATABASE=/workspace/inventory/database/database.sqlite
   ```
3. `php artisan migrate`

### MySQL (Ubuntu VPS)
1. Buat DB, contoh `inventory_lab`
2. `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventory_lab
   DB_USERNAME=<user_mysql>
   DB_PASSWORD=<password_mysql>
   ```
3. `php artisan migrate`

Perintah tambahan:
- `php artisan migrate:rollback`
- `php artisan migrate:fresh`
- `php artisan migrate:status`



## 11. CRUD Master Data (sudah ditambahkan)

CRUD backend + Inertia pages:
- Warehouses (`/apps/master-data/warehouses`)
- Categories (`/apps/master-data/categories`)
- UOM (`/apps/master-data/uoms`)
- Items (`/apps/master-data/items`)

Masing-masing sudah mencakup:
- index + search + pagination
- create
- edit
- delete (single dan bulk halaman)

Permission master data:
- `master-warehouse-*`
- `master-category-*`
- `master-uom-*`
- `master-item-*`

Route resources berada pada group:
- `Route::prefix('master-data')->name('master-data.')`
