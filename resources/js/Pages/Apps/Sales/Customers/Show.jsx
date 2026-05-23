import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const tabs = ['Overview', 'Sales Orders', 'Shipments', 'Invoices', 'Payments', 'Ledger Placeholder'];

export default function Page({ customer, summary }) {
  const statusClassName = customer.status === 'active'
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-gray-100 text-gray-700';

  const stats = [
    ['Credit Limit', Number(customer.credit_limit || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })],
    ['Payment Term', `${customer.payment_term_days} days`],
    ['Total Sales Orders', summary.total_sales_orders],
    ['Outstanding Balance', Number(summary.outstanding_balance || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })],
  ];

  return (
    <AppLayout>
      <Head title='Customer Detail' />

      <div className='p-6 space-y-4'>
        <div className='sticky top-0 z-10 rounded-lg border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-4'>
            <div>
              <h1 className='text-2xl font-bold'>{customer.customer_name}</h1>
              <p className='text-sm text-gray-600'>{customer.customer_code}</p>
            </div>

            <div className='flex flex-col items-end gap-2'>
              <Link href={route('apps.customers.index')} className='rounded border bg-white px-3 py-2 text-sm'>Back to List</Link>
              <Link href={route('apps.customers.edit', customer.id)} className='rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700'>Edit Customer</Link>
              <span className={`rounded-full px-2 py-1 text-xs ${statusClassName}`}>{customer.status}</span>
            </div>
          </div>
        </div>

        <div className='grid grid-cols-2 gap-3 md:grid-cols-4'>
          {stats.map(([label, value]) => (
            <div key={label} className='rounded-lg border bg-white p-3 shadow-sm'>
              <div className='text-xs text-gray-500'>{label}</div>
              <div className='font-semibold text-gray-900'>{value}</div>
            </div>
          ))}
        </div>

        <div className='rounded-lg border bg-white p-3 shadow-sm'>
          <div className='mb-3 flex flex-wrap gap-3 text-sm'>
            {tabs.map((tab) => (
              <span key={tab} className='rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-gray-700'>{tab}</span>
            ))}
          </div>

          <div className='space-y-1 text-sm text-gray-700'>
            <div>Contact: {customer.contact_person || '-'}</div>
            <div>Phone: {customer.phone || '-'}</div>
            <div>Email: {customer.email || '-'}</div>
            <div>Address: {[customer.address, customer.city, customer.province, customer.postal_code, customer.country].filter(Boolean).join(', ') || '-'}</div>
            <div>NPWP: {customer.npwp || '-'}</div>
            <div>Notes: {customer.notes || '-'}</div>
            <p className='mt-3 text-gray-600'>Customer Ledger will be available in Phase 2.</p>
            <p className='text-gray-600'>No data available yet.</p>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
