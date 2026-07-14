import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

const incomingColumns = [
  ['no', 'NO'], ['jenis_transaksi', 'JENIS TRANSAKSI'], ['tanggal_pemasukan', 'TANGGAL PEMASUKAN'],
  ['kode_obat_jadi', 'KODE OBAT JADI'], ['jumlah', 'JUMLAH'], ['batch', 'BATCH'],
  ['tanggal_expired', 'TANGGAL EXPIRED'], ['nomor_faktur', 'NOMOR FAKTUR'], ['sumber', 'SUMBER'],
  ['keterangan', 'KETERANGAN'], ['id_kota_kab_sumber', 'ID KOTA/KAB SUMBER'],
  ['nama_kota_kab_sumber', 'NAMA KOTA/KAB SUMBER'], ['nama_provinsi_sumber', 'NAMA PROVINSI SUMBER'],
];

const outgoingColumns = [
  ['no', 'NO'], ['jenis_distribusi', 'JENIS DISTRIBUSI'], ['tanggal_distribusi', 'TANGGAL DISTRIBUSI'],
  ['kode_obat_jadi', 'KODE OBAT JADI'], ['jumlah_obat_jadi', 'JUMLAH OBAT JADI'], ['batch_obat_jadi', 'BATCH OBAT JADI'],
  ['tanggal_expired', 'TANGGAL EXPIRED'], ['nomor_faktur', 'NOMOR FAKTUR'], ['tujuan', 'TUJUAN'],
  ['alamat', 'ALAMAT'], ['keterangan_peruntukan', 'KETERANGAN/PERUNTUKAN'], ['id_kota_kab_tujuan', 'ID KOTA/KAB TUJUAN'],
  ['nama_kota_kab_tujuan', 'NAMA KOTA/KAB TUJUAN'], ['provinsi_tujuan', 'PROVINSI TUJUAN'],
];

export default function Index({ filters, rows = [], summary = {} }) {
  const [form, setForm] = useState(filters);
  const columns = useMemo(() => form.type === 'outgoing' ? outgoingColumns : incomingColumns, [form.type]);
  const query = new URLSearchParams(form).toString();
  const hasInvalidRows = Number(summary.invalid_rows || 0) > 0;

  const submit = (event) => {
    event.preventDefault();
    router.get(route('apps.regulatory.bpom-movement-reports.index'), form, { preserveState: true, preserveScroll: true });
  };

  return (
    <AppLayout>
      <Head title="Laporan BPOM" />
      <div className="space-y-6 p-6">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Laporan BPOM</h1>
          <p className="text-sm text-gray-500">Generate laporan barang masuk dan barang keluar BPOM dalam format Excel berdasarkan periode tanggal.</p>
        </div>

        <form onSubmit={submit} className="grid gap-4 rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950 md:grid-cols-4">
          <label className="text-sm">
            <span className="mb-1 block font-medium">Jenis Laporan</span>
            <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
              <option value="incoming">Barang Masuk</option>
              <option value="outgoing">Barang Keluar</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Tanggal Mulai</span>
            <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Tanggal Akhir</span>
            <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
          </label>
          <div className="flex items-end gap-2">
            <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Preview</button>
            <a href={`${route('apps.regulatory.bpom-movement-reports.export')}?${query}`} className={`rounded-lg px-4 py-2 text-sm font-medium text-white ${hasInvalidRows ? 'pointer-events-none bg-gray-400' : 'bg-emerald-600 hover:bg-emerald-700'}`}>Export Excel</a>
          </div>
        </form>

        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-xl border bg-white p-4 dark:border-gray-800 dark:bg-gray-950"><div className="text-sm text-gray-500">Total Baris</div><div className="text-2xl font-semibold">{summary.total_rows ?? 0}</div></div>
          <div className="rounded-xl border bg-white p-4 dark:border-gray-800 dark:bg-gray-950"><div className="text-sm text-gray-500">Valid</div><div className="text-2xl font-semibold text-emerald-600">{summary.valid_rows ?? 0}</div></div>
          <div className="rounded-xl border bg-white p-4 dark:border-gray-800 dark:bg-gray-950"><div className="text-sm text-gray-500">Belum Lengkap</div><div className="text-2xl font-semibold text-rose-600">{summary.invalid_rows ?? 0}</div></div>
        </div>

        {hasInvalidRows && <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <div className="font-semibold">Data belum lengkap. Export Excel dinonaktifkan sampai field wajib terisi.</div>
          <ul className="mt-2 list-disc pl-5">
            {(summary.errors || []).slice(0, 10).map((error) => <li key={`${error.no}-${error.nomor_faktur}`}>Baris {error.no} / Faktur {error.nomor_faktur}: {error.errors.join(', ')}</li>)}
          </ul>
        </div>}

        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
          <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead className="bg-gray-50 dark:bg-gray-900"><tr>{columns.map(([key, label]) => <th key={key} className="whitespace-nowrap px-3 py-2 text-left font-semibold">{label}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {rows.length ? rows.map((row, index) => <tr key={`${row.source_document_id}-${row.source_line_id}-${index}`} className={row.validation_errors?.length ? 'bg-rose-50 dark:bg-rose-950/20' : ''}>{columns.map(([key]) => <td key={key} className="whitespace-nowrap px-3 py-2">{row[key] ?? '-'}</td>)}</tr>) : <tr><td colSpan={columns.length} className="px-3 py-8 text-center text-gray-500">Tidak ada data untuk periode ini.</td></tr>}
            </tbody>
          </table>
        </div>
        <p className="text-xs text-gray-500">Preview menampilkan maksimal 100 baris. Export mencakup seluruh data valid pada periode yang dipilih.</p>
      </div>
    </AppLayout>
  );
}
