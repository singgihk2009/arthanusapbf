# Step-by-step Cetak PO Prekursor

Dokumen ini menjelaskan cara menyiapkan signer dan mencetak **Surat Pesanan Prekursor** dengan format seperti contoh: header perusahaan, nomor/perihal/lampiran, identitas Apoteker Penanggung Jawab, detail supplier, tabel zat aktif prekursor, kebutuhan, alamat gudang, dan tanda tangan Direktur + Apoteker Penanggung Jawab.

## 1. Siapkan data employee penanda tangan

1. Buka menu **Human Resource > Employee**.
2. Buat/validasi employee untuk:
   - **Direktur**: contoh `Harri Kurniawan, S.T`.
   - **Apoteker Penanggung Jawab**: contoh `apt. Eva Meliana BR Tambunan, S.Farm`.
3. Isi jabatan employee pada master **Position** agar jabatan bisa dicetak otomatis.
4. Jika nomor SIPA/SIPTTK perlu dicetak, simpan nomor tersebut sebagai license employee atau salin ke `purchase_order_signers.requester_license_no` / `approver_license_no` saat setup signer profile.

## 2. Setup signer profile untuk PO Prekursor

Saat ini struktur data signer sudah tersedia di tabel `purchase_order_signers`. Setup awal dapat dilakukan melalui database/tinker sampai tersedia halaman maintenance signer.

Contoh via `php artisan tinker`:

```php
use App\Models\Employee;
use App\Models\Procurement\PurchaseOrderSigner;

$apoteker = Employee::where('full_name', 'like', '%Eva Meliana%')->first();
$direktur = Employee::where('full_name', 'like', '%Harri Kurniawan%')->first();

PurchaseOrderSigner::updateOrCreate(
    ['po_type' => 'precursor', 'is_active' => true],
    [
        'requester_employee_id' => $apoteker?->id,
        'approver_employee_id' => $direktur?->id,
        'requester_name' => 'apt. Eva Meliana BR Tambunan, S.Farm',
        'requester_title' => 'Apoteker Penanggung Jawab',
        'requester_license_no' => 'SIPA 510.16/2/1671/SIPA/DPMPTSP/2025',
        'approver_name' => 'Harri Kurniawan, S.T',
        'approver_title' => 'Direktur',
        'approver_license_no' => null,
    ]
);
```

Catatan mapping signer untuk format contoh:

| Posisi format | Field signer | Contoh |
| --- | --- | --- |
| Kanan bawah / Hormat saya | requester | Apoteker Penanggung Jawab |
| Kiri bawah / Mengetahui | approver | Direktur |

## 3. Buat PO Prekursor

1. Buka **Procurement > Purchase Order**.
2. Klik **Create**.
3. Pilih **Vendor**.
4. Pada **Jenis PO**, pilih **PO Prekursor**.
5. Pada **Signer Profile**, pilih profile yang sudah dibuat. Jika dikosongkan, sistem mengambil profile aktif pertama untuk `po_type = precursor`.
6. Isi tanggal PO.
7. Isi field khusus:
   - **Tujuan Penggunaan**: contoh `Obat mengandung Prekursor Farmasi tersebut akan digunakan untuk memenuhi kebutuhan.`
   - **Alamat Gudang/Kebutuhan**: contoh `Jl. Ps. Baru Ruko No. 75 Kec. Cianjur`.
8. Input item:
   - **Nama Produk**: contoh `ALLERIN EXP 60ML`.
   - **Zat Aktif/Prekursor**: contoh `Difenhidramin HCl`.
   - **Bentuk & Kekuatan**: contoh `12,5 mg`.
   - Qty, harga, pajak, dan field lain sesuai kebutuhan.
9. Upload dokumen pendukung bila diperlukan.
10. Klik **Save Draft** atau **Approve**.

## 4. Cetak Surat Pesanan Prekursor

1. Buka detail PO yang sudah dibuat.
2. Pastikan judul kartu menampilkan **PO Prekursor** dan nomor PO ber-prefix `POMedPre-`.
3. Pastikan bagian informasi detail menampilkan:
   - Signer Pemohon = Apoteker Penanggung Jawab.
   - Signer Persetujuan = Direktur.
   - Tujuan Penggunaan.
   - Alamat Gudang/Kebutuhan.
4. Klik tombol **Print PO (PDF)**.
5. Browser akan membuka jendela print dengan title **SURAT PESANAN PREKURSOR**.
6. Pilih printer atau **Save as PDF**.
7. Simpan/cetak dokumen.

## 5. Checklist hasil cetak

Pastikan hasil cetak berisi:

- Logo dan identitas perusahaan.
- Nomor PO ber-prefix `POMedPre-YYYYMM-XXXX`.
- Perihal/judul **SURAT PESANAN PREKURSOR**.
- Nama supplier dan alamat supplier.
- Nama pemesan/apoteker, jabatan, dan SIPA.
- Tabel item yang memuat nama barang, zat aktif prekursor, bentuk/kekuatan, satuan, dan jumlah.
- Tujuan penggunaan dan alamat gudang/kebutuhan.
- Tanda tangan Direktur dan Apoteker Penanggung Jawab beserta jabatan.

## 6. Jika signer/jabatan belum muncul

- Pastikan `purchase_orders.signer_profile_id` terisi atau ada profile aktif `po_type = precursor`.
- Pastikan relasi employee signer memiliki `position_id`, atau isi fallback `requester_title` dan `approver_title` di `purchase_order_signers`.
- Pastikan nama fallback `requester_name` dan `approver_name` diisi jika employee belum lengkap.
- Refresh halaman detail PO sebelum klik print.
