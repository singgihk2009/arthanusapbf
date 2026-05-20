import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';

export default function Page({ vendor, receivingLines, internalInvoiceNoPreview }) {
  const { data, setData, post, processing } = useForm({ vendor_invoice_no:'', invoice_date:'', due_date:'', currency_code:'IDR', exchange_rate:1, notes:'', discount_amount:0, freight_amount:0, tax_rate:11, wht_tax_type:'', wht_tax_rate:0, wht_tax_base_amount:0, lines:[] });
  const selected = useMemo(()=> data.lines, [data.lines]);
  const subtotal = selected.reduce((a,b)=>a+((+b.qty_invoiced||0)*(+b.unit_price||0)),0);
  const taxBase=Math.max(0, subtotal-(+data.discount_amount||0)); const taxAmount=taxBase*(+data.tax_rate||0)/100; const grand=taxBase+taxAmount+(+data.freight_amount||0); const whtBase=(+data.wht_tax_base_amount||taxBase); const wht=whtBase*(+data.wht_tax_rate||0)/100; const net=grand-wht;

  const toggle = (line, checked) => {
    if (checked) setData('lines',[...data.lines,{source_line_type:line.source_line_type,source_line_id:line.source_line_id,qty_invoiced:line.qty_available_to_invoice,unit_price:line.unit_price}]);
    else setData('lines',data.lines.filter(x=>!(x.source_line_type===line.source_line_type && x.source_line_id===line.source_line_id)));
  };

  return <AppLayout><div className='p-6 space-y-6'>
    <h1 className='text-xl font-semibold'>Create Vendor Invoice</h1>
    <div className='grid grid-cols-2 gap-3 bg-white p-4 border rounded'>
      <input disabled value={vendor.vendor_name||vendor.name} className='border p-2 rounded'/><input disabled value={internalInvoiceNoPreview} className='border p-2 rounded'/>
      <input placeholder='Vendor invoice no' value={data.vendor_invoice_no} onChange={e=>setData('vendor_invoice_no',e.target.value)} className='border p-2 rounded'/><input type='date' value={data.invoice_date} onChange={e=>setData('invoice_date',e.target.value)} className='border p-2 rounded'/>
    </div>
    <div className='bg-white p-4 border rounded overflow-auto'>
      <table className='min-w-full text-sm'><thead><tr><th></th><th>Receiving</th><th>PO</th><th>Item</th><th>Qty Rec</th><th>Qty Inv</th><th>Qty Avail</th><th>Qty To Invoice</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>{receivingLines.map(l=>{const s=data.lines.find(x=>x.source_line_type===l.source_line_type && x.source_line_id===l.source_line_id); return <tr key={`${l.source_line_type}-${l.source_line_id}`} className='border-t'><td><input type='checkbox' checked={!!s} onChange={e=>toggle(l,e.target.checked)}/></td><td>{l.receiving_no}</td><td>{l.po_no}</td><td>{l.item_name}</td><td>{l.qty_received}</td><td>{l.qty_already_invoiced}</td><td>{l.qty_available_to_invoice}</td><td><input disabled={!s} type='number' value={s?.qty_invoiced??''} onChange={e=>setData('lines',data.lines.map(x=>x.source_line_type===l.source_line_type && x.source_line_id===l.source_line_id?{...x,qty_invoiced:e.target.value}:x))} className='border p-1 w-24'/></td><td><input disabled={!s} type='number' value={s?.unit_price??''} onChange={e=>setData('lines',data.lines.map(x=>x.source_line_type===l.source_line_type && x.source_line_id===l.source_line_id?{...x,unit_price:e.target.value}:x))} className='border p-1 w-28'/></td><td>{(((+s?.qty_invoiced||0)*(+s?.unit_price||0))).toFixed(2)}</td></tr>})}</tbody></table>
    </div>
    <div className='bg-white p-4 border rounded'>Subtotal: {subtotal.toFixed(2)} | Tax: {taxAmount.toFixed(2)} | Grand: {grand.toFixed(2)} | WHT: {wht.toFixed(2)} | Net: {net.toFixed(2)}</div>
    <button disabled={processing} onClick={()=>post(`/apps/procurement/vendors/${vendor.id}/invoices`)} className='px-4 py-2 bg-indigo-600 text-white rounded'>Submit</button>
  </div></AppLayout>;
}
