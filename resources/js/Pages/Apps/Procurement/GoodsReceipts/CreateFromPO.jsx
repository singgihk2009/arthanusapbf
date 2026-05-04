import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

export default function CreateFromPO({ purchaseOrder, items }) {
  const { data, setData, post } = useForm({ purchase_order_id: purchaseOrder.id, received_date: '', notes: '', items: items.map(i=>({ ...i, received_qty: i.suggested_received_qty })) });
  return <AppLayout><Head title='Create GR' /><div className='p-4'><h1>Create GR from {purchaseOrder.po_number}</h1><input type='date' value={data.received_date} onChange={e=>setData('received_date', e.target.value)} className='border'/><table>{data.items.map((i,idx)=><tr key={i.purchase_order_item_id}><td>{i.product_name}</td><td>{i.remaining_qty}</td><td><input type='number' value={i.received_qty} onChange={e=>{const v=Math.min(Number(e.target.value), Number(i.remaining_qty)); const rows=[...data.items]; rows[idx].received_qty=v; setData('items',rows);}} /></td><td>{i.po_unit_price}</td><td>{Number(i.received_qty||0)*Number(i.po_unit_price||0)}</td></tr>)}</table><button onClick={()=>post(route('apps.procurement.goods-receipts.store'))}>Save Draft</button></div></AppLayout>;
}
