import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });

export default function Page({ invoices }) {
  const rows = invoices?.data ?? [];
  const [selectedInvoiceIds, setSelectedInvoiceIds] = useState([]);
  const payableInvoiceIds = useMemo(() => rows.filter((invoice) => ['posted', 'partially_paid', 'overdue'].includes(String(invoice.status || '').toLowerCase()) && Number(invoice.balance_due || 0) > 0).map((invoice) => invoice.id), [rows]);
  const allPayableChecked = payableInvoiceIds.length > 0 && payableInvoiceIds.every((id) => selectedInvoiceIds.includes(id));
  const paymentUrl = selectedInvoiceIds.length ? `/apps/customer-payments/create?invoice_ids=${selectedInvoiceIds.join(',')}` : '#';

  const toggleInvoiceSelection = (id) => {
    setSelectedInvoiceIds((previous) => previous.includes(id) ? previous.filter((item) => item !== id) : [...previous, id]);
  };

  const toggleAllPayable = () => {
    if (!payableInvoiceIds.length) return;
    setSelectedInvoiceIds((previous) => allPayableChecked ? previous.filter((id) => !payableInvoiceIds.includes(id)) : Array.from(new Set([...previous, ...payableInvoiceIds])));
  };

  return (
    <AppLayout>
      <Head title='Customer Invoices' />
      <div className='space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <h1 className='text-xl font-semibold'>Customer Invoices</h1>
          <p className='mt-1 text-sm text-gray-600'>Invoice dibuat dari satu atau beberapa dispatch POSTED pada tab Fulfillment customer. Pilih invoice posted untuk proses collection payment.</p>
          <div className='mt-3 flex flex-wrap items-center gap-2 text-sm'>
            <Link href={paymentUrl} className={`rounded px-3 py-2 font-medium ${selectedInvoiceIds.length ? 'bg-indigo-600 text-white' : 'pointer-events-none bg-gray-200 text-gray-500'}`}>Create Payment ({selectedInvoiceIds.length})</Link>
          </div>
        </div>

        <div className='overflow-x-auto rounded border bg-white shadow-sm'>
          <table className='min-w-full text-sm'>
            <thead className='bg-gray-50'>
              <tr>
                <th className='px-3 py-2 text-center'><input type='checkbox' checked={allPayableChecked} onChange={toggleAllPayable} disabled={!payableInvoiceIds.length} /></th>
                <th className='px-3 py-2 text-left'>Number</th>
                <th className='px-3 py-2 text-left'>Customer</th>
                <th className='px-3 py-2 text-left'>Tanggal</th>
                <th className='px-3 py-2 text-left'>Due Date</th>
                <th className='px-3 py-2 text-left'>Status</th>
                <th className='px-3 py-2 text-right'>Subtotal</th>
                <th className='px-3 py-2 text-right'>Diskon</th>
                <th className='px-3 py-2 text-right'>PPN</th>
                <th className='px-3 py-2 text-right'>Biaya Kirim</th>
                <th className='px-3 py-2 text-right'>Grand Total</th>
                <th className='px-3 py-2 text-center'>Aksi</th>
              </tr>
            </thead>
            <tbody className='divide-y'>
              {!rows.length && <tr><td colSpan={12} className='px-3 py-4 text-center text-gray-500'>Belum ada invoice.</td></tr>}
              {rows.map((invoice) => {
                const payable = payableInvoiceIds.includes(invoice.id);
                const draft = String(invoice.status || '').toLowerCase() === 'draft';
                return (
                <tr key={invoice.id}>
                  <td className='px-3 py-2 text-center'>{payable ? <input type='checkbox' checked={selectedInvoiceIds.includes(invoice.id)} onChange={() => toggleInvoiceSelection(invoice.id)} /> : '-'}</td>
                  <td className='px-3 py-2'>{invoice.number}</td>
                  <td className='px-3 py-2'>{invoice.customer_name}</td>
                  <td className='px-3 py-2'>{invoice.invoice_date}</td>
                  <td className='px-3 py-2'>{invoice.due_date || '-'}</td>
                  <td className='px-3 py-2'><span className='rounded border px-2 py-1 text-xs uppercase'>{invoice.status}</span></td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(invoice.subtotal)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(invoice.discount_total)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(invoice.tax_total)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(invoice.freight_amount)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(invoice.grand_total)}</td>
                  <td className='px-3 py-2 text-center'>
                      <div className='flex justify-center gap-1'>
                        <Link href={route('apps.customer-invoices.show', invoice.id)} className='rounded border px-2 py-1 text-xs'>View</Link>
                        {draft && <Link href={route('apps.customer-invoices.edit', invoice.id)} className='rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700'>Edit</Link>}
                      </div>
                    </td>
                </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
