import { Link, router } from '@inertiajs/react';

const formatCurrency = (value) => {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
};

export default function Tab({ data, vendor }) {
  const invoices = data?.invoices?.data ?? [];

  const approveInvoice = (invoiceId) => {
    router.post(`/apps/procurement/vendor-invoices/${invoiceId}/approve`);
  };

  const deleteInvoice = (invoiceId) => {
    if (!window.confirm('Hapus vendor invoice ini?')) return;
    router.delete(`/apps/procurement/vendor-invoices/${invoiceId}`);
  };

  return (
    <div className='space-y-4'>
      <div className='flex justify-end'>
        <Link href={`/apps/procurement/vendors/${vendor.id}/invoices/create`} className='px-3 py-2 bg-indigo-600 text-white rounded'>+ Create Vendor Invoice</Link>
      </div>
      <div className='overflow-auto border rounded'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'><tr>{['Internal Invoice No','Vendor Invoice No','Invoice Date','Due Date','Subtotal','Discount','PPN','WHT','Grand Total','Net Payable','Paid','Outstanding','Status','Action'].map(h=><th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr></thead>
          <tbody>{invoices.map((x)=><tr key={x.id} className='border-t'><td className='px-3 py-2'>{x.invoice_no_internal}</td><td className='px-3 py-2'>{x.vendor_invoice_no}</td><td className='px-3 py-2'>{x.invoice_date}</td><td className='px-3 py-2'>{x.due_date}</td><td className='px-3 py-2'>{formatCurrency(x.subtotal)}</td><td className='px-3 py-2'>{formatCurrency(x.discount_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.wht_tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.grand_total)}</td><td className='px-3 py-2'>{formatCurrency(x.net_payable_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.paid_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.outstanding_amount)}</td><td className='px-3 py-2'>{x.status}</td><td className='px-3 py-2 space-x-1'><Link href={`/apps/procurement/vendor-invoices/${x.id}`} className='px-2 py-1 bg-gray-100 rounded'>View</Link><Link href={`/apps/procurement/vendor-invoices/${x.id}/edit`} className={`px-2 py-1 bg-gray-100 rounded ${x.status!=='draft' ? 'pointer-events-none opacity-50' : ''}`}>Edit</Link><button type='button' onClick={()=>approveInvoice(x.id)} disabled={x.status!=='draft'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Approve</button><button type='button' onClick={()=>deleteInvoice(x.id)} disabled={x.status!=='draft'} className='px-2 py-1 bg-red-50 text-red-700 rounded disabled:opacity-50'>Delete</button></td></tr>)}</tbody>
        </table>
      </div>

    </div>
  );
}
