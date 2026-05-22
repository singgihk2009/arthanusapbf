import { Link, router } from '@inertiajs/react';

const formatCurrency = (value) => {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
};

const formatDate = (value) => value
  ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
  : '-';

export default function PaymentsTab({ data, vendor }) {
  const payments = data?.payments?.data || [];

  const approvePayment = (paymentId) => {
    router.post(`/apps/procurement/vendors/${vendor.id}/payments/${paymentId}/approve`);
  };
  const submitPayment = (paymentId) => {
    router.post(`/apps/procurement/vendors/${vendor.id}/payments/${paymentId}/submit`);
  };

  const deletePayment = (paymentId) => {
    if (!window.confirm('Hapus payment ini?')) return;
    router.delete(`/apps/procurement/vendor-payments/${paymentId}`);
  };

  return <div className='space-y-4'>
    <div className='flex justify-between'><h3 className='font-semibold'>Vendor Payments</h3><Link className='px-3 py-2 bg-indigo-600 text-white rounded' href={`/apps/procurement/vendors/${vendor.id}/payments/create`}>Create Payment</Link></div>
    <div className='grid grid-cols-2 md:grid-cols-4 gap-3 text-sm'>
      <Card label='Outstanding' value={formatCurrency(data?.summary?.total_outstanding_invoice || 0)} />
      <Card label='Total Paid' value={formatCurrency(data?.summary?.total_paid || 0)} />
      <Card label='Draft/Submitted' value={formatCurrency(data?.summary?.total_payment_draft_submitted || 0)} />
      <Card label='Last Payment' value={formatDate(data?.summary?.last_payment_date)} />
    </div>
    <div className='overflow-auto border rounded'>
      <table className='min-w-full text-sm'>
        <thead className='bg-gray-50'><tr>{['No', 'Date', 'Status', 'Total Invoice', 'WHT', 'Meterai', 'Freight', 'Bank Charge', 'Net', 'Cash Out', 'Action'].map((h) => <th key={h} className={`px-3 py-2 ${h === 'Action' ? 'text-center' : 'text-left'}`}>{h}</th>)}</tr></thead>
        <tbody>{payments.map((p) => {
          const status = String(p.status).toUpperCase();
          return <tr key={p.id} className='border-t'><td className='px-3 py-2'><Link className='text-indigo-600' href={`/apps/procurement/vendors/${vendor.id}/payments/${p.id}`}>{p.payment_no}</Link></td><td className='px-3 py-2'>{formatDate(p.payment_date)}</td><td className='px-3 py-2'>{p.status}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.total_invoice_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.total_wht_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.stamp_duty_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.freight_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.bank_charge_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.net_vendor_payment_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(p.total_cash_out_amount)}</td><td className='px-3 py-2 whitespace-nowrap space-x-1 text-center'><Link href={`/apps/procurement/vendors/${vendor.id}/payments/${p.id}`} className='px-2 py-1 bg-gray-100 rounded'>View</Link><Link href={`/apps/procurement/vendors/${vendor.id}/payments/${p.id}/edit`} className={`px-2 py-1 bg-gray-100 rounded ${status !== 'DRAFT' ? 'pointer-events-none opacity-50' : ''}`}>Edit</Link><button type='button' onClick={() => submitPayment(p.id)} disabled={status !== 'DRAFT'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Submit</button><button type='button' onClick={() => approvePayment(p.id)} disabled={status !== 'SUBMITTED'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Approve</button><button type='button' onClick={() => deletePayment(p.id)} disabled={status !== 'DRAFT'} className='px-2 py-1 bg-red-50 text-red-700 rounded disabled:opacity-50'>Delete</button></td></tr>;
        })}</tbody>
      </table>
    </div>
  </div>;
}

const Card = ({ label, value }) => <div className='p-3 rounded border bg-white'><div className='text-gray-500'>{label}</div><div className='font-semibold'>{value}</div></div>;
