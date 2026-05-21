import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const money = (value) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));

export default function Show({ invoice }) {
  const fields = [
    ['Internal Invoice No', invoice.invoice_no_internal],
    ['Vendor Invoice No', invoice.vendor_invoice_no],
    ['Invoice Date', invoice.invoice_date],
    ['Due Date', invoice.due_date],
    ['Subtotal', money(invoice.subtotal)],
    ['Discount', money(invoice.discount_amount)],
    ['PPN', money(invoice.tax_amount)],
    ['WHT', money(invoice.wht_tax_amount)],
    ['Grand Total', money(invoice.grand_total)],
    ['Net Payable', money(invoice.net_payable_amount)],
    ['Paid', money(invoice.paid_amount)],
    ['Outstanding', money(invoice.outstanding_amount)],
    ['Status', invoice.status],
  ];

  return (
    <AppLayout>
      <Head title='Vendor Invoice Detail' />
      <div className='p-6 space-y-4'>
        <div className='flex items-center justify-between'>
          <h1 className='text-xl font-semibold'>Vendor Invoice Detail</h1>
          <Link href={`/apps/procurement/vendors/${invoice.vendor_id}?tab=invoices`} className='px-3 py-2 bg-gray-100 rounded'>Back</Link>
        </div>
        <div className='rounded border bg-white'>
          {fields.map(([label, value]) => (
            <div key={label} className='grid grid-cols-3 border-b last:border-b-0'>
              <div className='px-4 py-3 font-medium text-gray-600'>{label}</div>
              <div className='px-4 py-3 col-span-2'>{value ?? '-'}</div>
            </div>
          ))}
        </div>
      </div>
    </AppLayout>
  );
}
