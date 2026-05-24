import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import SmartItemInput from '@/Components/SmartItemInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconCopy, IconTrash, IconAdjustmentsHorizontal } from '@tabler/icons-react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';

const makeLine = () => ({ item_id:'', item_name:'', item_code:'', sku:'', barcode:'', batch_id:'', uom_id:'', uom_name:'', available_stock:null, cogs:0, qty_sold:1, unit_price:0, discount_percent:0, tax_percent:11, discount_amount:0, tax_amount:0, line_total:0, notes:'', price_list_id:null, price_list_line_id:null, batch_options:[] });
const money=(n)=>Number(n||0).toLocaleString('id-ID',{style:'currency',currency:'IDR'});

const stockTone = (stock) => {
  if (stock === null || stock === undefined) return 'bg-slate-100 text-slate-600';
  if (Number(stock) <= 0) return 'bg-rose-100 text-rose-700';
  if (Number(stock) < 20) return 'bg-amber-100 text-amber-700';
  return 'bg-emerald-100 text-emerald-700';
};

export default function Page({ customer, salesOrder, warehouses = [], priceList }) {
  const isEdit = Boolean(salesOrder?.id);
  const searchRef = useRef(null);
  const [selectedRow, setSelectedRow] = useState(0);

  const { data, setData, post, put, processing, isDirty } = useForm({ warehouse_id: salesOrder?.warehouse_id || warehouses?.[0]?.id || '', document_date: salesOrder?.document_date || new Date().toISOString().slice(0,10), expected_delivery_date: salesOrder?.expected_delivery_date || '', price_list_id: salesOrder?.price_list_id || priceList?.id || '', notes: salesOrder?.notes || '', lines: salesOrder?.lines?.length ? salesOrder.lines.map(l=>({...makeLine(),...l,item_name:l.item?.name||l.item_name||'',item_code:l.item?.sku||l.item_code||'',sku:l.item?.sku||l.sku||'',barcode:l.item?.default_barcode||l.barcode||'',uom_name:l.uom?.name||l.uom_name||''})) : [makeLine()] });
  const totals=useMemo(()=>data.lines.reduce((a,l)=>{const gross=+l.qty_sold*(+l.unit_price);const disc=gross*(+l.discount_percent)/100;const tax=(gross-disc)*(+l.tax_percent)/100;a.subtotal+=gross;a.discount+=disc;a.tax+=tax;a.grand+=gross-disc+tax;a.qty += Number(l.qty_sold || 0);return a;},{subtotal:0,discount:0,tax:0,grand:0,qty:0}),[data.lines]);

  const patchLine=(i,patch)=>{setData((prev)=>{const ls=[...(prev.lines||[])];ls[i]={...ls[i],...patch};const l=ls[i]||{};const gross=+l.qty_sold*(+l.unit_price);const disc=gross*(+l.discount_percent)/100;const tax=(gross-disc)*(+l.tax_percent)/100;ls[i]={...ls[i],discount_amount:disc,tax_amount:tax,line_total:gross-disc+tax};return {...prev,lines:ls};});};

  const chooseItem=async(i,item)=>{patchLine(i,{item_id:item.id,item_name:item.name||'',item_code:item.code||item.sku||'',sku:item.sku||'',barcode:item.barcode||'',uom_id:item.uom_id||'',uom_name:item.uom_name||'',available_stock:item.available_stock,cogs:item.cogs||0,batch_id:'',qty_sold:data.lines[i]?.qty_sold || 1}); const b=await axios.get(route('apps.sales-orders.batches'),{params:{item_id:item.id,warehouse_id:data.warehouse_id||null}}); patchLine(i,{batch_options:b.data||[]}); await resolvePrice(i); searchRef.current?.focus();};
  const chooseBatch=(i,batchId)=>{const b=(data.lines[i].batch_options||[]).find(x=>String(x.id)===String(batchId)); patchLine(i,{batch_id:batchId,available_stock:b?.available_stock ?? data.lines[i].available_stock,cogs:b?.cogs ?? data.lines[i].cogs});};
  const resolvePrice=async(i)=>{const l=data.lines[i]; if(!l.item_id) return; const r=await axios.get(route('apps.price-lists.resolve-price'),{params:{item_id:l.item_id,qty:l.qty_sold,uom_id:l.uom_id,date:data.document_date,price_list_id:data.price_list_id}}); patchLine(i,{unit_price:r.data.unit_price||0,discount_percent:r.data.discount_percent||0,price_list_id:r.data.price_list_id||null,price_list_line_id:r.data.price_list_line_id||null});};
  const save=(e)=>{e.preventDefault(); isEdit ? put(route('apps.sales-orders.update',salesOrder.id)) : post(route('apps.customers.sales-orders.store',customer.id));};

  const addLine = () => setData('lines',[...data.lines,makeLine()]);
  const removeLine = (index) => setData('lines', data.lines.filter((_,i)=>i!==index));
  const duplicateLine = (index) => setData('lines', [...data.lines.slice(0,index+1), {...data.lines[index]}, ...data.lines.slice(index+1)]);

  useEffect(() => {
    const listener = (e) => {
      if (e.key === 'F2') { e.preventDefault(); searchRef.current?.focus(); }
      if (e.key === 'F4') { e.preventDefault(); document.getElementById('so-save-draft')?.click(); }
      if (e.key === 'F9') { e.preventDefault(); document.getElementById('so-submit')?.click(); }
      if (e.key === 'Delete' && data.lines.length > 1) { e.preventDefault(); removeLine(selectedRow); }
      if (e.key.toLowerCase() === 'd' && e.ctrlKey) { e.preventDefault(); duplicateLine(selectedRow); }
    };
    window.addEventListener('keydown', listener);
    return () => window.removeEventListener('keydown', listener);
  }, [selectedRow, data.lines]);

  return <>
    <Head title='Create Sales Order'/>
    <Card title={isEdit ? 'Edit Sales Order' : 'Create Sales Order'} form={save} footer={<div className='flex items-center gap-2'><Button id='so-save-draft' type='submit' label='Save Draft' disabled={processing} variant='gray'/><Button id='so-submit' type='submit' label='Submit Order' disabled={processing} variant='orange'/><Link href={route('apps.customers.show',customer.id)} className='rounded-lg border border-rose-300 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50'>Cancel</Link>{isDirty && <span className='text-xs text-amber-600'>Data belum disimpan.</span>}</div>}>
      <div className='grid grid-cols-1 gap-4 md:grid-cols-3'>
        <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Warehouse</label><select value={data.warehouse_id} onChange={e=>setData('warehouse_id',e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700'><option value=''>-</option>{warehouses.map(w=><option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
        <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>SO Date</label><input type='date' value={data.document_date} onChange={e=>setData('document_date',e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' /></div>
        <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Expected Delivery</label><input type='date' value={data.expected_delivery_date} onChange={e=>setData('expected_delivery_date',e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' /></div>
        <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Price List</label><input value={priceList?.name || 'Default Price List'} readOnly className='w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700' /></div>
        <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Salesman</label><input value='-' readOnly className='w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700' /></div>
        <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Payment Term</label><input value='-' readOnly className='w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700' /></div>
        <div className='flex flex-col gap-2 md:col-span-3'><label className='text-sm text-gray-600'>Notes</label><textarea value={data.notes} onChange={e=>setData('notes',e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' /></div>
      </div>

      <div className='mt-4 rounded border border-gray-200 p-3'>
        <label className='mb-2 block text-sm text-gray-600'>Product Search</label>
        <SmartItemInput inputClassName='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' inputRef={searchRef} autoFocus value={null} onSelect={(item)=>chooseItem(selectedRow, item)} warehouseId={data.warehouse_id} placeholder='Scan barcode / SKU / product name...' />
      </div>

      <div className='mt-4 overflow-x-auto'>
        <table className='min-w-[1200px] w-full border border-gray-200 text-sm'>
          <thead><tr className='bg-gray-50'><th className='border px-2 py-2 text-left'>Product</th><th className='border px-2 py-2'>Batch</th><th className='border px-2 py-2'>Expiry</th><th className='border px-2 py-2'>Stock</th><th className='border px-2 py-2'>Price</th><th className='border px-2 py-2'>Qty</th><th className='border px-2 py-2'>Disc%</th><th className='border px-2 py-2'>Tax%</th><th className='border px-2 py-2'>Line Total</th><th className='border px-2 py-2'>Action</th></tr></thead>
          <tbody>{data.lines.map((l,i)=>{const active = i===selectedRow; const selectedBatch=(l.batch_options||[]).find(b=>String(b.id)===String(l.batch_id)); return <tr key={i} onClick={()=>setSelectedRow(i)} className={`${active ? 'bg-amber-50' : ''}`}>
            <td className='border px-2 py-2 align-top'><div className='font-medium text-gray-800'>{l.item_name || 'Select product...'}</div><div className='text-xs text-gray-500'>SKU: {l.sku||'-'} • Barcode: {l.barcode||'-'}</div></td>
            <td className='border px-2 py-2'><select value={l.batch_id||''} onChange={e=>chooseBatch(i,e.target.value)} className='w-full rounded border border-gray-200 px-2 py-1 text-xs' disabled={!l.item_id}><option value=''>Pilih batch</option>{(l.batch_options||[]).map(b=><option key={b.id} value={b.id}>{b.batch_no}{b.expired_date?` (EXP ${b.expired_date})`:''}</option>)}</select></td>
            <td className='border px-2 py-2 text-xs text-gray-600'>{selectedBatch?.expired_date || '-'}</td>
            <td className='border px-2 py-2 text-center'><span className={`rounded-full px-2 py-1 text-xs ${stockTone(l.available_stock)}`}>{l.available_stock ?? 'Unknown'}</span></td>
            <td className='border px-2 py-2'><input className='w-28 rounded border border-gray-200 px-2 py-1 text-right' type='number' value={l.unit_price} onChange={e=>patchLine(i,{unit_price:e.target.value})}/></td>
            <td className='border px-2 py-2'><input className='w-20 rounded border border-gray-200 px-2 py-1 text-center' type='number' value={l.qty_sold} onChange={e=>{patchLine(i,{qty_sold:e.target.value});resolvePrice(i);}}/></td>
            <td className='border px-2 py-2'><input className='w-20 rounded border border-gray-200 px-2 py-1 text-right' type='number' value={l.discount_percent} onChange={e=>patchLine(i,{discount_percent:e.target.value})}/></td>
            <td className='border px-2 py-2'><input className='w-20 rounded border border-gray-200 px-2 py-1 text-right' type='number' value={l.tax_percent} onChange={e=>patchLine(i,{tax_percent:e.target.value})}/></td>
            <td className='border px-2 py-2 text-right font-semibold'>{money(l.line_total)}</td>
            <td className='border px-2 py-2'><div className='flex items-center justify-center gap-1'><button type='button' onClick={()=>duplicateLine(i)} className='rounded p-1 text-gray-500 hover:bg-gray-200'><IconCopy className='h-4 w-4'/></button><button type='button' onClick={()=>removeLine(i)} className='rounded p-1 text-rose-600 hover:bg-rose-100'><IconTrash className='h-4 w-4'/></button><button type='button' className='rounded p-1 text-gray-500 hover:bg-gray-200'><IconAdjustmentsHorizontal className='h-4 w-4'/></button></div></td>
          </tr>;})}</tbody>
        </table>
      </div>
      <div className='mt-3'><Button type='button' label='Add Line' variant='gray' onClick={addLine}/></div>
      <div className='mt-3 text-right text-sm font-medium'>Subtotal: {money(totals.subtotal)} | Discount: {money(totals.discount)} | Tax: {money(totals.tax)} | Grand Total: {money(totals.grand)} | Total Qty: {totals.qty}</div>
      <div className='mt-2 text-xs text-gray-500'>Shortcut: F2 Search • F4 Save • F9 Submit • Del Remove • Ctrl+D Duplicate</div>
    </Card>
  </>;
}
Page.layout=(p)=><AppLayout children={p}/>;
