import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });

export default function Page({ payments }) {
  const rows = payments?.data ?? [];

  return (
    <AppLayout>
      <Head title='Customer Payments' />
      <div className='space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <h1 className='text-xl font-semibold'>Customer Payments</h1>
          <p className='mt-1 text-sm text-gray-600'>Collection payment dapat dialokasikan ke satu atau beberapa invoice customer dengan opsi WHT dan potongan lainnya.</p>
        </div>

        <div className='overflow-x-auto rounded border bg-white shadow-sm'>
          <table className='min-w-full text-sm'>
            <thead className='bg-gray-50'>
              <tr>
                <th className='px-3 py-2 text-left'>Number</th>
                <th className='px-3 py-2 text-left'>Customer</th>
                <th className='px-3 py-2 text-left'>Tanggal</th>
                <th className='px-3 py-2 text-left'>Metode</th>
                <th className='px-3 py-2 text-left'>Status</th>
                <th className='px-3 py-2 text-right'>Kas</th>
                <th className='px-3 py-2 text-right'>Diskon</th>
                <th className='px-3 py-2 text-right'>WHT</th>
                <th className='px-3 py-2 text-right'>Potongan Lain</th>
                <th className='px-3 py-2 text-right'>Settlement</th>
                <th className='px-3 py-2 text-center'>Aksi</th>
              </tr>
            </thead>
            <tbody className='divide-y'>
              {!rows.length && <tr><td colSpan={11} className='px-3 py-4 text-center text-gray-500'>Belum ada payment.</td></tr>}
              {rows.map((payment) => (
                <tr key={payment.id}>
                  <td className='px-3 py-2'>{payment.number}</td>
                  <td className='px-3 py-2'>{payment.customer_name}</td>
                  <td className='px-3 py-2'>{payment.payment_date}</td>
                  <td className='px-3 py-2'>{payment.payment_method || '-'}</td>
                  <td className='px-3 py-2'><span className='rounded border px-2 py-1 text-xs uppercase'>{payment.status}</span></td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(payment.amount)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(payment.discount_taken)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(payment.wht_amount)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(payment.other_deduction_amount)}</td>
                  <td className='px-3 py-2 text-right'>{formatCurrency(payment.gross_settlement_amount)}</td>
                  <td className='px-3 py-2 text-center'><Link href={route('apps.customer-payments.show', payment.id)} className='rounded border px-2 py-1 text-xs'>View</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
