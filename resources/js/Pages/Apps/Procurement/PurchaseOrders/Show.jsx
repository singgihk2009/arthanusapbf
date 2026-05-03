import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function Show({ purchaseOrder }) {
  const canCancel = purchaseOrder.items.every(i => +i.qty_received === 0);
  const statusClass = {draft:'bg-gray-100',approved:'bg-blue-100',partially_received:'bg-amber-100',fully_received:'bg-green-100',closed:'bg-purple-100',cancelled:'bg-red-100'}[purchaseOrder.status] || 'bg-gray-100';
  return <AppLayout><div className='p-6 space-y-4'><h1 className='text-xl font-semibold'>PO {purchaseOrder.po_number}</h1>
    <div className='flex gap-3'><span className={`px-2 py-1 rounded ${statusClass}`}>{purchaseOrder.status}</span>{purchaseOrder.status==='draft'&&<button onClick={()=>router.post(`/apps/procurement/purchase-orders/${purchaseOrder.id}/approve`)} className='px-3 py-1 bg-blue-600 text-white rounded'>Approve</button>}{canCancel&&purchaseOrder.status!=='cancelled'&&<button onClick={()=>router.post(`/apps/procurement/purchase-orders/${purchaseOrder.id}/cancel`)} className='px-3 py-1 bg-red-600 text-white rounded'>Cancel</button>}</div>
    <table className='min-w-full border'><thead><tr><th>Product</th><th>Qty Ordered</th><th>Qty Received</th><th>Remaining</th><th>Line Total</th></tr></thead><tbody>{purchaseOrder.items.map(i=><tr key={i.id} className='border-t'><td>{i.product?.name || i.product_name || '-'}</td><td>{i.qty_ordered}</td><td>{i.qty_received}</td><td>{(+i.qty_ordered)-(+i.qty_received)}</td><td>{i.line_total}</td></tr>)}</tbody></table>
  </div></AppLayout>;
}
