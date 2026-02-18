# Inventory System Blueprint (Laravel + Inertia + MySQL)

Dokumen ini adalah implementasi awal modul inventory laboratorium dengan pendekatan **stock ledger first**: stok tidak diubah manual, semua perubahan berasal dari dokumen transaksi yang diposting.

## 1. Prinsip inti implementasi

- Seluruh dokumen (PO, GRN, Transfer, Sales, Usage, Adjustment, Opname) memiliki status workflow.
- Hanya status `POSTED` / `RECEIVED` yang boleh menghasilkan data ke `stock_ledgers`.
- Seluruh kuantitas stok disimpan dalam `qty_base` (base UOM).
- Snapshot realtime disimpan di `stock_balances` untuk query cepat.

## 2. Struktur tabel yang sudah dibuat (migration)

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

## 3. Alur posting transaksi (disarankan untuk service layer)

1. Validasi status dokumen = boleh diposting.
2. Konversi `qty_input` ke `qty_base` via `item_uom_conversions`.
3. Tentukan batch (FEFO untuk item `track_expired = true`).
4. Insert ke `stock_ledgers`:
   - IN: qty_base positif
   - OUT: qty_base negatif
5. Update `stock_balances` per `(warehouse_id, item_id, batch_id)`.
6. Simpan audit user (`created_by`, `posted_by`) dan timestamp posting.

## 4. Cara menjalankan migrasi

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

## 5. Next step yang direkomendasikan

- Implement `StockService`, `UomConversionService`, `BatchAllocationService`.
- Tambah Observer/Event: saat ledger tercipta, sinkronisasi `stock_balances`.
- Buat endpoint report:
  - Stock balance
  - Stock card / mutasi dengan running balance
  - Expired monitoring (H-30, H-60)
  - Minimum stock alert per gudang
