import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

export default function Show({ goodsReceipt }) {
  return <AppLayout><Head title={goodsReceipt.gr_number} /><div className='p-4'><h1>{goodsReceipt.gr_number}</h1><p>Status: {goodsReceipt.status}</p><p>Status Tagihan: <span className='rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs text-indigo-700'>{goodsReceipt.invoice_status || 'Belum Ditagih'}</span></p><p className='text-sm text-gray-600'>Qty Received: {goodsReceipt.qty_received ?? 0} | Qty Invoiced: {goodsReceipt.qty_already_invoiced ?? 0} | Qty Available: {goodsReceipt.qty_available_to_invoice ?? 0}</p>{goodsReceipt.status==='draft' && <button onClick={()=>router.post(route('apps.procurement.goods-receipts.post', goodsReceipt.id))}>Post</button>} {goodsReceipt.status==='posted' && <span>Locked/Posted</span>}<table>{goodsReceipt.items.map(i=><tr key={i.id}><td>{i.product?.name}</td><td>{i.received_qty}</td><td>{i.po_unit_price}</td><td>{i.inventory_total_cost}</td></tr>)}</table></div></AppLayout>;
}
