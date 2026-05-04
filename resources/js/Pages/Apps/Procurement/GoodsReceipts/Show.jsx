import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

export default function Show({ goodsReceipt }) {
  return <AppLayout><Head title={goodsReceipt.gr_number} /><div className='p-4'><h1>{goodsReceipt.gr_number}</h1><p>Status: {goodsReceipt.status}</p>{goodsReceipt.status==='draft' && <button onClick={()=>router.post(route('apps.procurement.goods-receipts.post', goodsReceipt.id))}>Post</button>} {goodsReceipt.status==='posted' && <span>Locked/Posted</span>}<table>{goodsReceipt.items.map(i=><tr key={i.id}><td>{i.product?.name}</td><td>{i.received_qty}</td><td>{i.po_unit_price}</td><td>{i.inventory_total_cost}</td></tr>)}</table></div></AppLayout>;
}
