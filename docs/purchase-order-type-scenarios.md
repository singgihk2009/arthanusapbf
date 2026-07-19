# Rekomendasi Dukungan 4 Jenis Purchase Order

Struktur PO sekarang dapat dibedakan memakai `purchase_orders.po_type` dengan nilai `regular`, `precursor`, `oot`, dan `alkes`. Nomor PO dibuat otomatis sesuai jenis agar format dokumen dapat dipisahkan tanpa membuat tabel header/line baru.

## Mapping jenis, nomor, dan format cetak

| Jenis | `po_type` | Prefix nomor | Format cetak yang disarankan | Penandatangan utama |
| --- | --- | --- | --- | --- |
| PO Reguler obat | `regular` | `POMed-YYYYMM-XXXX` | Template PO obat standar | Apoteker Penanggung Jawab dan Direktur |
| PO Prekursor | `precursor` | `POMedPre-YYYYMM-XXXX` | Surat Pesanan Prekursor dengan identitas apoteker, daftar zat aktif/prekursor, kebutuhan, dan alamat gudang | Apoteker Penanggung Jawab dan Direktur |
| PO OOT | `oot` | `POMedOOT-YYYYMM-XXXX` | Template PO obat standar dengan label OOT serta lampiran/rujukan dokumen OOT bila diperlukan | Apoteker Penanggung Jawab dan Direktur |
| PO Alkes | `alkes` | `POAlk-YYYYMM-XXXX` | Template PO alat kesehatan | Penanggung Jawab Teknis dan Direktur |

## Evaluasi struktur data

Struktur header `purchase_orders` + detail `purchase_order_items` tetap mencukupi untuk empat jenis PO karena perbedaannya terutama berada di nomor dokumen, judul/format cetak, penandatangan, dan metadata pendukung. Item, vendor, tanggal, qty, satuan, harga, pajak, lampiran dokumen, dan status approval tetap menggunakan alur yang sama.

## Skenario create

1. User memilih vendor qualified.
2. User memilih `Jenis PO`.
3. Sistem memvalidasi item minimal satu baris dan field standar PO.
4. Sistem membuat nomor berdasarkan jenis PO.
5. Dokumen pendukung dapat diunggah ke Document Center dengan owner `purchase_order`.
6. User menyimpan draft atau langsung approve.
7. Halaman detail menyediakan print sesuai `po_type`.

## Rekomendasi lanjutan

- Master signer/employee per jenis PO sudah disiapkan melalui `purchase_order_signers` dan dapat ditautkan ke header PO memakai `signer_profile_id`.
- Field khusus prekursor/OOT sudah disiapkan: `usage_purpose`, `warehouse_address`, serta line `active_ingredient`, `dosage_form_strength`, dan `regulatory_note`.
- Konfigurasi template cetak sudah dipisahkan ke modul `PrintTemplates` agar judul/label/signature per jenis dapat diubah tanpa mengubah seluruh halaman detail.
