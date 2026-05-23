import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';

const tabs = ['Overview', 'Profile', 'Sales Orders', 'Shipments', 'Invoices', 'Payments', 'Ledger Placeholder'];

export default function Page({ customer, summary }) {
  const [activeTab, setActiveTab] = useState('Overview');

  const statusClassName = customer.status === 'active'
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-gray-100 text-gray-700';

  const stats = useMemo(() => ([
    ['Credit Limit', Number(customer.credit_limit || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })],
    ['Payment Term', `${customer.payment_term_days} days`],
    ['Total Sales Orders', summary.total_sales_orders],
    ['Outstanding Balance', Number(summary.outstanding_balance || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })],
  ]), [customer.credit_limit, customer.payment_term_days, summary.outstanding_balance, summary.total_sales_orders]);

  const { data, setData, put, processing, errors, reset } = useForm({
    customer_code: customer?.customer_code ?? '',
    customer_name: customer?.customer_name ?? '',
    customer_type: customer?.customer_type ?? '',
    contact_person: customer?.contact_person ?? '',
    phone: customer?.phone ?? '',
    email: customer?.email ?? '',
    address: customer?.address ?? '',
    city: customer?.city ?? '',
    province: customer?.province ?? '',
    postal_code: customer?.postal_code ?? '',
    country: customer?.country ?? 'Indonesia',
    npwp: customer?.npwp ?? '',
    payment_term_days: customer?.payment_term_days ?? 0,
    credit_limit: customer?.credit_limit ?? 0,
    status: customer?.status ?? 'active',
    notes: customer?.notes ?? '',
  });

  const submitProfile = (e) => {
    e.preventDefault();
    put(route('apps.customers.update', customer.id));
  };

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
              <span className={`rounded-full px-2 py-1 text-xs ${statusClassName}`}>{customer.status}</span>
            </div>
          </div>
        </div>

        <div className='rounded-lg border bg-white p-3 shadow-sm'>
          <div className='mb-3 flex flex-wrap gap-3 text-sm'>
            {tabs.map((tab) => (
              <button
                key={tab}
                type='button'
                onClick={() => setActiveTab(tab)}
                className={`rounded-md border px-2 py-1 ${activeTab === tab ? 'border-gray-300 bg-gray-100 font-medium text-gray-900' : 'border-gray-200 bg-gray-50 text-gray-700'}`}
              >
                {tab}
              </button>
            ))}
          </div>

          {activeTab === 'Overview' && (
            <>
              <div className='grid grid-cols-2 gap-3 md:grid-cols-4'>
                {stats.map(([label, value]) => (
                  <div key={label} className='rounded-lg border bg-white p-3 shadow-sm'>
                    <div className='text-xs text-gray-500'>{label}</div>
                    <div className='font-semibold text-gray-900'>{value}</div>
                  </div>
                ))}
              </div>

              <div className='mt-4 space-y-1 text-sm text-gray-700'>
                <div>Contact: {customer.contact_person || '-'}</div>
                <div>Phone: {customer.phone || '-'}</div>
                <div>Email: {customer.email || '-'}</div>
                <div>Address: {[customer.address, customer.city, customer.province, customer.postal_code, customer.country].filter(Boolean).join(', ') || '-'}</div>
                <div>NPWP: {customer.npwp || '-'}</div>
                <div>Notes: {customer.notes || '-'}</div>
                <p className='mt-3 text-gray-600'>Customer Ledger will be available in Phase 2.</p>
                <p className='text-gray-600'>No data available yet.</p>
              </div>
            </>
          )}

          {activeTab === 'Profile' && (
            <form onSubmit={submitProfile} className='space-y-3'>
              <div className='grid grid-cols-1 gap-3 md:grid-cols-2'>
                <Input label='Customer Code' value={data.customer_code} onChange={(e) => setData('customer_code', e.target.value)} errors={errors.customer_code} />
                <Input label='Customer Name' value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} errors={errors.customer_name} />
                <Input label='Customer Type' value={data.customer_type} onChange={(e) => setData('customer_type', e.target.value)} errors={errors.customer_type} />
                <Input label='Contact Person' value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} errors={errors.contact_person} />
                <Input label='Phone' value={data.phone} onChange={(e) => setData('phone', e.target.value)} errors={errors.phone} />
                <Input label='Email' type='email' value={data.email} onChange={(e) => setData('email', e.target.value)} errors={errors.email} />
                <Input label='Address' value={data.address} onChange={(e) => setData('address', e.target.value)} errors={errors.address} />
                <Input label='City' value={data.city} onChange={(e) => setData('city', e.target.value)} errors={errors.city} />
                <Input label='Province' value={data.province} onChange={(e) => setData('province', e.target.value)} errors={errors.province} />
                <Input label='Postal Code' value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} errors={errors.postal_code} />
                <Input label='Country' value={data.country} onChange={(e) => setData('country', e.target.value)} errors={errors.country} />
                <Input label='NPWP' value={data.npwp} onChange={(e) => setData('npwp', e.target.value)} errors={errors.npwp} />
                <Input label='Payment Term (Days)' type='number' value={data.payment_term_days} onChange={(e) => setData('payment_term_days', e.target.value)} errors={errors.payment_term_days} />
                <Input label='Credit Limit' type='number' value={data.credit_limit} onChange={(e) => setData('credit_limit', e.target.value)} errors={errors.credit_limit} />

                <div className='flex flex-col gap-2'>
                  <label className='text-gray-600 text-sm'>Status</label>
                  <select
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                    className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none focus:ring-0 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800'
                  >
                    <option value='active'>Active</option>
                    <option value='inactive'>Inactive</option>
                  </select>
                  {errors.status && <small className='text-xs text-red-500'>{errors.status}</small>}
                </div>

                <Input label='Notes' value={data.notes} onChange={(e) => setData('notes', e.target.value)} errors={errors.notes} className='md:col-span-2' />
              </div>

              <div className='flex flex-wrap items-center gap-2'>
                <Button type='submit' label='Simpan' variant='gray' disabled={processing} />
                <Button type='button' label='Reset' variant='orange' onClick={() => reset()} disabled={processing} />
              </div>
            </form>
          )}

          {activeTab !== 'Overview' && activeTab !== 'Profile' && (
            <p className='text-gray-600 text-sm'>No data available yet.</p>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
