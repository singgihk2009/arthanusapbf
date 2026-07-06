import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const money = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Index({ purchaseReturns, filters = {}, reasons = [] }) {
  const [form, setForm] = useState({ status: filters.status || '', reason: filters.reason || '', date_from: filters.date_from || '', date_to: filters.date_to || '' });
  const rows = purchaseReturns?.data || [];
  const apply = (event) => { event.preventDefault(); router.get(route('apps.procurement.purchase-returns.index'), form, { preserveState: true, preserveScroll: true }); };

  return <>
    <Head title="Purchase Return" />
    <div className="space-y-4">
      <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Purchase Return</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400">Retur barang rusak/expired/tidak sesuai ke vendor dan potong tagihan.</p>
          </div>
          <Link href={route('apps.procurement.purchase-returns.create')} className="rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white dark:bg-gray-100 dark:text-gray-900">Create Return</Link>
        </div>

        <form onSubmit={apply} className="mb-4 grid gap-3 md:grid-cols-5">
          <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className="rounded border-gray-300 text-sm"><option value="">Semua Status</option>{['DRAFT','SUBMITTED','APPROVED','POSTED','CANCELLED','VOID'].map((s) => <option key={s}>{s}</option>)}</select>
          <select value={form.reason} onChange={(e) => setForm((p) => ({ ...p, reason: e.target.value }))} className="rounded border-gray-300 text-sm"><option value="">Semua Reason</option>{reasons.map((s) => <option key={s}>{s}</option>)}</select>
          <input type="date" value={form.date_from} onChange={(e) => setForm((p) => ({ ...p, date_from: e.target.value }))} className="rounded border-gray-300 text-sm" />
          <input type="date" value={form.date_to} onChange={(e) => setForm((p) => ({ ...p, date_to: e.target.value }))} className="rounded border-gray-300 text-sm" />
          <button className="rounded border border-indigo-500 px-3 py-2 text-sm text-indigo-600">Filter</button>
        </form>

        <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
          <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead className="bg-gray-50 dark:bg-gray-900"><tr>{['No','Return No','Tanggal','Vendor','Goods Receipt','Warehouse','Reason','Qty','Amount','Status','Action'].map((h) => <th key={h} className="px-3 py-2 text-left font-semibold">{h}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {rows.length === 0 && <tr><td colSpan={11} className="px-3 py-4 text-center text-gray-500">Belum ada purchase return.</td></tr>}
              {rows.map((row, idx) => <tr key={row.id}>
                <td className="px-3 py-2">{purchaseReturns.from ? purchaseReturns.from + idx : idx + 1}</td>
                <td className="px-3 py-2 font-medium">{row.return_no}</td>
                <td className="px-3 py-2">{row.return_date}</td>
                <td className="px-3 py-2">{row.vendor?.vendor_name || row.vendor?.name || '-'}</td>
                <td className="px-3 py-2">{row.goods_receipt?.gr_number || row.goods_receipt?.number || '-'}</td>
                <td className="px-3 py-2">{row.warehouse?.code || row.warehouse?.name || '-'}</td>
                <td className="px-3 py-2">{row.reason_category}</td>
                <td className="px-3 py-2 text-right">{money(row.total_qty)}</td>
                <td className="px-3 py-2 text-right">{money(row.total_amount)}</td>
                <td className="px-3 py-2"><span className="rounded border px-2 py-1 text-xs">{row.status}</span></td>
                <td className="px-3 py-2"><Link href={route('apps.procurement.purchase-returns.show', row.id)} className="rounded bg-gray-100 px-2 py-1">View</Link></td>
              </tr>)}
            </tbody>
          </table>
        </div>
        {purchaseReturns?.last_page > 1 && <div className="mt-4"><Pagination links={purchaseReturns.links} /></div>}
      </div>
    </div>
  </>;
}

Index.layout = (page) => <AppLayout children={page} />;
