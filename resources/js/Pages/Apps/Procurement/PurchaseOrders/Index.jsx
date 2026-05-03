import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function Index({ purchaseOrders, filters = {}, statuses = [] }) {
  const onFilter = (key, val) => router.get('/apps/procurement/purchase-orders', { ...filters, [key]: val }, { preserveState: true });
  return <AppLayout><div className='p-6 space-y-4'>
    <div className='flex justify-between'><h1 className='text-xl font-semibold'>Purchase Orders</h1><Link href='/apps/procurement/purchase-orders/create' className='px-3 py-2 bg-indigo-600 text-white rounded'>Create PO</Link></div>
    <div className='flex gap-2'><input className='border rounded px-3 py-2' defaultValue={filters.search || ''} placeholder='Cari vendor/nomor' onBlur={(e)=>onFilter('search',e.target.value)} />
      <select className='border rounded px-3 py-2' value={filters.status || ''} onChange={(e)=>onFilter('status',e.target.value)}><option value=''>Semua status</option>{statuses.map(s=><option key={s} value={s}>{s}</option>)}</select></div>
    <table className='min-w-full border text-sm'><thead><tr className='bg-gray-50'><th>PO Number</th><th>Vendor</th><th>PO Date</th><th>Expected Date</th><th>Grand Total</th><th>Status</th><th>Action</th></tr></thead><tbody>{purchaseOrders.data.map(po=><tr key={po.id} className='border-t'><td>{po.po_number}</td><td>{po.vendor?.name||'-'}</td><td>{po.po_date}</td><td>{po.expected_delivery_date||'-'}</td><td>{po.grand_total}</td><td>{po.status}</td><td><Link className='text-indigo-600' href={`/apps/procurement/purchase-orders/${po.id}`}>Detail</Link></td></tr>)}</tbody></table>
  </div></AppLayout>;
}
