import { Link } from '@inertiajs/react';

export default function Tab({ data, vendor }) {
  const invoices = data?.invoices?.data ?? [];
  return (
    <div className='space-y-4'>
      <div className='flex justify-end'>
        <Link href={`/apps/procurement/vendors/${vendor.id}/invoices/create`} className='px-3 py-2 bg-indigo-600 text-white rounded'>+ Create Vendor Invoice</Link>
      </div>
      <div className='overflow-auto border rounded'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'><tr>{['Internal Invoice No','Vendor Invoice No','Invoice Date','Due Date','Subtotal','Discount','PPN','WHT','Grand Total','Net Payable','Paid','Outstanding','Status','Action'].map(h=><th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr></thead>
          <tbody>{invoices.map((x)=><tr key={x.id} className='border-t'><td className='px-3 py-2'>{x.invoice_no_internal}</td><td className='px-3 py-2'>{x.vendor_invoice_no}</td><td className='px-3 py-2'>{x.invoice_date}</td><td className='px-3 py-2'>{x.due_date}</td><td className='px-3 py-2'>{x.subtotal}</td><td className='px-3 py-2'>{x.discount_amount}</td><td className='px-3 py-2'>{x.tax_amount}</td><td className='px-3 py-2'>{x.wht_tax_amount}</td><td className='px-3 py-2'>{x.grand_total}</td><td className='px-3 py-2'>{x.net_payable_amount}</td><td className='px-3 py-2'>{x.paid_amount}</td><td className='px-3 py-2'>{x.outstanding_amount}</td><td className='px-3 py-2'>{x.status}</td><td className='px-3 py-2 space-x-1'><button className='px-2 py-1 bg-gray-100 rounded'>View</button><button disabled={x.status!=='draft'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Edit</button><button disabled={x.status!=='draft'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Approve</button><button disabled={x.status!=='approved'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Post</button></td></tr>)}</tbody>
        </table>
      </div>
    </div>
  );
}
