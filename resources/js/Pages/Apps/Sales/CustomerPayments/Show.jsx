import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });

export default function Page({ payment, allocations = [] }) {
  const postPayment = () => {
    if (!confirm('Post payment ini? Balance due invoice akan diperbarui.')) return;
    router.post(route('apps.customer-payments.post', payment.id));
  };

  return (
    <AppLayout>
      <Head title={`Payment ${payment.number}`} />
      <div className='space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-3'>
            <div>
              <h1 className='text-xl font-semibold'>{payment.number}</h1>
              <p className='text-sm text-gray-600'>{payment.customer_name} ({payment.customer_code})</p>
              <p className='text-sm text-gray-600'>Status: <span className='font-semibold uppercase'>{payment.status}</span></p>
            </div>
            <div className='flex gap-2'>
              {payment.status === 'draft' && <button type='button' onClick={postPayment} className='rounded bg-indigo-600 px-3 py-2 text-sm text-white'>Post Payment</button>}
              <Link href={route('apps.customer-payments.index')} className='rounded border px-3 py-2 text-sm'>Back to List</Link>
            </div>
          </div>
        </div>

        <div className='grid gap-4 lg:grid-cols-3'>
          <div className='rounded border bg-white p-4 shadow-sm lg:col-span-2'>
            <h2 className='mb-3 font-semibold'>Alokasi Invoice</h2>
            <div className='overflow-x-auto'>
              <table className='min-w-full text-sm'>
                <thead className='bg-gray-50'>
                  <tr><th className='px-3 py-2 text-left'>Invoice</th><th className='px-3 py-2 text-right'>Kas</th><th className='px-3 py-2 text-right'>Diskon</th><th className='px-3 py-2 text-right'>WHT</th><th className='px-3 py-2 text-right'>Potongan Lain</th><th className='px-3 py-2 text-right'>Settlement</th></tr>
                </thead>
                <tbody className='divide-y'>
                  {allocations.map((allocation) => {
                    const settlement = Number(allocation.amount_applied || 0) + Number(allocation.discount_taken || 0) + Number(allocation.wht_amount || 0) + Number(allocation.other_deduction_amount || 0) + Number(allocation.writeoff_amount || 0);
                    return (
                      <tr key={allocation.id}>
                        <td className='px-3 py-2'><Link href={route('apps.customer-invoices.show', allocation.customer_invoice_id)} className='text-blue-600 hover:underline'>{allocation.invoice_number}</Link></td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(allocation.amount_applied)}</td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(allocation.discount_taken)}</td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(allocation.wht_amount)}</td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(Number(allocation.other_deduction_amount || 0) + Number(allocation.writeoff_amount || 0))}</td>
                        <td className='px-3 py-2 text-right font-semibold'>{formatCurrency(settlement)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>

          <div className='rounded border bg-white p-4 shadow-sm'>
            <h2 className='mb-3 font-semibold'>Ringkasan</h2>
            <div className='space-y-2 text-sm'>
              <div className='flex justify-between'><span>Tanggal</span><b>{payment.payment_date}</b></div>
              <div className='flex justify-between'><span>Metode</span><b>{payment.payment_method || '-'}</b></div>
              <div className='flex justify-between'><span>Kas Diterima</span><b>{formatCurrency(payment.amount)}</b></div>
              <div className='flex justify-between'><span>Bank Charge</span><b>{formatCurrency(payment.bank_charge)}</b></div>
              <div className='flex justify-between'><span>Diskon</span><b>{formatCurrency(payment.discount_taken)}</b></div>
              <div className='flex justify-between'><span>WHT</span><b>{formatCurrency(payment.wht_amount)}</b></div>
              <div className='flex justify-between'><span>Potongan Lain</span><b>{formatCurrency(payment.other_deduction_amount)}</b></div>
              <div className='flex justify-between border-t pt-2 text-base'><span>Total Settlement</span><b>{formatCurrency(payment.gross_settlement_amount)}</b></div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
