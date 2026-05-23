import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, Link, router } from '@inertiajs/react';
import { IconRefresh } from '@tabler/icons-react';
import { useState } from 'react';

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

const resolvePaymentStatus = (status) => {
  const key = String(status || '').toLowerCase();
  if (key === 'paid') return { label: 'Lunas', className: 'border-emerald-300 bg-emerald-100 text-emerald-700' };
  if (key === 'partial') return { label: 'Belum Lunas', className: 'border-amber-300 bg-amber-100 text-amber-700' };
  return { label: 'Belum Bayar', className: 'border-rose-300 bg-rose-100 text-rose-700' };
};

export default function Page({ invoices, filters = {}, statusInvoiceOptions = [], statusDokumenOptions = [] }) {
  const rows = invoices?.data ?? [];
  const startNo = ((Number(invoices?.current_page || 1) - 1) * Number(invoices?.per_page || 10));
  const [form, setForm] = useState({
    search: filters.search || '',
    status_invoice: filters.status_invoice || '',
    status_dokumen: filters.status_dokumen || '',
  });

  const applyFilter = (e) => {
    e.preventDefault();
    router.get(route('apps.procurement.vendor-invoices.index'), form, { preserveState: true, preserveScroll: true });
  };

  const resetFilter = () => {
    const initial = { search: '', status_invoice: '', status_dokumen: '' };
    setForm(initial);
    router.get(route('apps.procurement.vendor-invoices.index'), initial, { preserveState: true, preserveScroll: true });
  };

  return <>
    <Head title='List Vendor Invoice' />

    <form onSubmit={applyFilter} className='mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12'>
      <div className='md:col-span-5'>
        <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Global Search</label>
        <input value={form.search} onChange={(e) => setForm((p) => ({ ...p, search: e.target.value }))} className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100' placeholder='Cari vendor / internal invoice / vendor invoice' />
      </div>
      <div className='md:col-span-3'>
        <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Status Invoice</label>
        <select value={form.status_invoice} onChange={(e) => setForm((p) => ({ ...p, status_invoice: e.target.value }))} className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'>
          <option value=''>Semua status</option>
          {statusInvoiceOptions.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
        </select>
      </div>
      <div className='md:col-span-2'>
        <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Status Dokumen</label>
        <select value={form.status_dokumen} onChange={(e) => setForm((p) => ({ ...p, status_dokumen: e.target.value }))} className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'>
          <option value=''>Semua status</option>
          {statusDokumenOptions.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>
      <div className='flex items-end gap-2 md:col-span-2'>
        <button type='submit' className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30'>Terapkan</button>
        <button type='button' onClick={resetFilter} className='inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'><IconRefresh size={16} strokeWidth={1.5} />Reset</button>
      </div>
    </form>

    <Table.Card title='List Vendor Invoice'>
      <div className='overflow-auto'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'>
            <tr>{['No', 'Internal Invoice No', 'Vendor Invoice No', 'Nama Vendor', 'Invoice Date', 'Due Date', 'Subtotal', 'Discount', 'PPN', 'WHT', 'Grand Total', 'Net Payable', 'Paid', 'Outstanding', 'Status Invoice', 'Status Dokumen', 'Action'].map((h) => <th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr>
          </thead>
          <tbody>
            {rows.length === 0 && <tr><td colSpan={17} className='px-3 py-6 text-center text-gray-500'>Data vendor invoice tidak ditemukan.</td></tr>}
            {rows.map((x, idx) => {
              const paymentStatus = resolvePaymentStatus(x.payment_status);
              return <tr key={x.id} className='border-t'>
                <td className='px-3 py-2'>{startNo + idx + 1}</td><td className='px-3 py-2'>{x.invoice_no_internal || '-'}</td><td className='px-3 py-2'>{x.vendor_invoice_no || '-'}</td><td className='px-3 py-2'>{x.vendor_id ? <Link href={`/apps/procurement/vendors/${x.vendor_id}?tab=overview`} className='text-indigo-600 hover:underline'>{x.vendor_name || '-'}</Link> : (x.vendor_name || '-')}</td><td className='px-3 py-2'>{formatDate(x.invoice_date)}</td><td className='px-3 py-2'>{formatDate(x.due_date)}</td><td className='px-3 py-2'>{formatCurrency(x.subtotal)}</td><td className='px-3 py-2'>{formatCurrency(x.discount_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.wht_tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.grand_total)}</td><td className='px-3 py-2'>{formatCurrency(x.net_payable_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.paid_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.outstanding_amount)}</td><td className='px-3 py-2'><span className={`inline-flex rounded-full border px-2 py-1 text-xs font-semibold ${paymentStatus.className}`}>{paymentStatus.label}</span></td><td className='px-3 py-2 uppercase'>{x.status}</td><td className='px-3 py-2'><Link href={`/apps/procurement/vendor-invoices/${x.id}`} className='rounded bg-gray-100 px-2 py-1'>View</Link></td>
              </tr>;
            })}
          </tbody>
        </table>
      </div>
    </Table.Card>

    {invoices?.last_page > 1 && <Pagination links={invoices.links} />}
  </>;
}

Page.layout = (page) => <AppLayout children={page} />;
