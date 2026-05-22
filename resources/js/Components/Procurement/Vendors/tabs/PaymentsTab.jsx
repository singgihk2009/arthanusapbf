import { Link } from '@inertiajs/react';

export default function PaymentsTab({ data, vendor }) {
  const payments = data?.payments?.data || [];
  return <div className='space-y-4'>
    <div className='flex justify-between'><h3 className='font-semibold'>Vendor Payments</h3><Link className='px-3 py-1 bg-indigo-600 text-white rounded' href={`/apps/procurement/vendors/${vendor.id}/payments/create`}>Create Payment</Link></div>
    <div className='grid grid-cols-2 md:grid-cols-4 gap-3 text-sm'>
      <Card label='Outstanding' value={data?.summary?.total_outstanding_invoice || 0} />
      <Card label='Total Paid' value={data?.summary?.total_paid || 0} />
      <Card label='Draft/Submitted' value={data?.summary?.total_payment_draft_submitted || 0} />
      <Card label='Last Payment' value={data?.summary?.last_payment_date || '-'} />
    </div>
    <table className='w-full text-sm border'><thead><tr className='bg-gray-50'><th>No</th><th>Date</th><th>Status</th><th>Total Invoice</th><th>WHT</th><th>Meterai</th><th>Freight</th><th>Bank Charge</th><th>Net</th><th>Cash Out</th></tr></thead><tbody>{payments.map(p=><tr key={p.id} className='border-t'><td><Link className='text-indigo-600' href={`/apps/procurement/vendors/${vendor.id}/payments/${p.id}`}>{p.payment_no}</Link></td><td>{p.payment_date}</td><td>{p.status}</td><td>{p.total_invoice_amount}</td><td>{p.total_wht_amount}</td><td>{p.stamp_duty_amount}</td><td>{p.freight_amount}</td><td>{p.bank_charge_amount}</td><td>{p.net_vendor_payment_amount}</td><td>{p.total_cash_out_amount}</td></tr>)}</tbody></table>
  </div>;
}
const Card=({label,value})=><div className='p-3 rounded border bg-white'><div className='text-gray-500'>{label}</div><div className='font-semibold'>{value}</div></div>;
