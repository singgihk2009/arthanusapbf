import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

const resolvePaymentStatus = (invoice) => {
  const netPayable = Number(invoice?.net_payable_amount ?? invoice?.grand_total ?? 0);
  const paidAmount = Number(invoice?.paid_amount ?? 0);
  const outstandingAmount = Number(invoice?.outstanding_amount ?? Math.max(0, netPayable - paidAmount));

  if (netPayable <= 0) return { label: 'Belum Bayar', className: 'border-gray-300 bg-gray-100 text-gray-700' };
  if (outstandingAmount <= 0) return { label: 'Lunas', className: 'border-emerald-300 bg-emerald-100 text-emerald-700' };
  if (paidAmount > 0 && outstandingAmount > 0) return { label: 'Partial', className: 'border-amber-300 bg-amber-100 text-amber-700' };

  return { label: 'Belum Bayar', className: 'border-rose-300 bg-rose-100 text-rose-700' };
};

export default function Page({ invoices }) {
  const rows = invoices?.data ?? [];

  return <AppLayout>
    <div className='p-6'>
      <h1 className='text-xl font-semibold'>List Vendor Invoice</h1>
      <div className='mt-4 overflow-auto rounded border'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'>
            <tr>{['Internal Invoice No','Vendor Invoice No', 'Nama Vendor', 'Invoice Date','Due Date','Subtotal','Discount','PPN','WHT','Grand Total','Net Payable','Paid','Outstanding','Status Invoice','Status Dokumen','Action'].map((h) => <th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr>
          </thead>
          <tbody>
            {rows.map((x) => {
              const paymentStatus = resolvePaymentStatus(x);
              return <tr key={x.id} className='border-t'>
                <td className='px-3 py-2'>{x.invoice_no_internal || '-'}</td>
                <td className='px-3 py-2'>{x.vendor_invoice_no || '-'}</td>
                <td className='px-3 py-2'>{x.vendor_name || '-'}</td>
                <td className='px-3 py-2'>{formatDate(x.invoice_date)}</td>
                <td className='px-3 py-2'>{formatDate(x.due_date)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.subtotal)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.discount_amount)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.tax_amount)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.wht_tax_amount)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.grand_total)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.net_payable_amount)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.paid_amount)}</td>
                <td className='px-3 py-2'>{formatCurrency(x.outstanding_amount)}</td>
                <td className='px-3 py-2'><span className={`inline-flex rounded-full border px-2 py-1 text-xs font-semibold ${paymentStatus.className}`}>{paymentStatus.label}</span></td>
                <td className='px-3 py-2 uppercase'>{x.status}</td>
                <td className='px-3 py-2'><Link href={`/apps/procurement/vendor-invoices/${x.id}`} className='rounded bg-gray-100 px-2 py-1'>View</Link></td>
              </tr>;
            })}
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>;
}
