import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import SmartItemInput from '@/Components/SmartItemInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconClipboardList, IconCopy, IconPackage, IconTrash, IconAdjustmentsHorizontal } from '@tabler/icons-react';
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

  const { data, setData, post, put, processing } = useForm({ warehouse_id: salesOrder?.warehouse_id || warehouses?.[0]?.id || '', document_date: salesOrder?.document_date || new Date().toISOString().slice(0,10), expected_delivery_date: salesOrder?.expected_delivery_date || '', price_list_id: salesOrder?.price_list_id || priceList?.id || '', notes: salesOrder?.notes || '', lines: salesOrder?.lines?.length ? salesOrder.lines.map(l=>({...makeLine(),...l,item_name:l.item?.name||l.item_name||'',item_code:l.item?.sku||l.item_code||'',sku:l.item?.sku||l.sku||'',barcode:l.item?.default_barcode||l.barcode||'',uom_name:l.uom?.name||l.uom_name||''})) : [makeLine()] });
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
    <form onSubmit={save} className='min-h-screen bg-slate-100 pb-28'>
      <div className='mx-auto max-w-[1600px] space-y-5 px-4 py-4 md:px-6'>
        <Card title={null}>
          <div className='flex flex-col gap-3 md:flex-row md:items-center md:justify-between'>
            <div>
              <div className='text-xs text-slate-500'>Dashboard / Sales / Orders / Create</div>
              <h1 className='mt-1 text-2xl font-semibold text-slate-900'>Create Sales Order</h1>
              <p className='text-xs text-slate-500'>SO Number: AUTO-GENERATED</p>
            </div>
            <span className='inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700'>Draft</span>
          </div>
        </Card>

        <Card title='Sales Order Information'>
          <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-3'>
            <select value={data.warehouse_id} onChange={e=>setData('warehouse_id',e.target.value)} className='h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm'><option value=''>Warehouse</option>{warehouses.map(w=><option key={w.id} value={w.id}>{w.name}</option>)}</select>
            <Input type='date' label='SO Date' value={data.document_date} onChange={e=>setData('document_date',e.target.value)}/>
            <Input type='date' label='Expected Delivery' value={data.expected_delivery_date} onChange={e=>setData('expected_delivery_date',e.target.value)}/>
            <Input label='Price List' value={priceList?.name || 'Default Price List'} readOnly />
            <Input label='Salesman' value='-'/>
            <Input label='Payment Term' value='-'/>
            <div className='md:col-span-2 xl:col-span-3'><Input label='Notes' value={data.notes} onChange={e=>setData('notes',e.target.value)} /></div>
          </div>
        </Card>

        <div className='sticky top-2 z-20 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm'>
          <div className='mb-2 flex items-center gap-2 text-sm font-medium text-slate-700'><IconPackage className='h-4 w-4'/> Product Search</div>
          <SmartItemInput inputClassName='h-14 rounded-xl border-slate-300 px-4 text-lg' inputRef={searchRef} autoFocus value={null} onSelect={(item)=>chooseItem(selectedRow, item)} warehouseId={data.warehouse_id} placeholder='Scan barcode / SKU / product name...' />
        </div>

        <div className='grid gap-5 xl:grid-cols-[1fr_340px]'>
          <Card title='Order Items'>
            <div className='overflow-x-auto'>
              <table className='w-full min-w-[1200px] text-sm'>
                <thead className='text-xs uppercase text-slate-500'><tr className='border-b'><th className='p-2 text-left'>Product</th><th className='p-2'>Batch</th><th className='p-2'>Expiry</th><th className='p-2'>Stock</th><th className='p-2'>Price</th><th className='p-2'>Qty</th><th className='p-2'>Disc%</th><th className='p-2'>Tax%</th><th className='p-2'>Line Total</th><th className='p-2'>Action</th></tr></thead>
                <tbody>{data.lines.map((l,i)=>{const active = i===selectedRow; const selectedBatch=(l.batch_options||[]).find(b=>String(b.id)===String(l.batch_id)); return <tr key={i} onClick={()=>setSelectedRow(i)} className={`border-b transition ${active ? 'bg-orange-50/50' : 'hover:bg-slate-50'}`}>
                  <td className='p-2 align-top'><div className='font-medium text-slate-800'>{l.item_name || 'Select product...'}</div><div className='text-xs text-slate-500'>SKU: {l.sku||'-'} • Barcode: {l.barcode||'-'}</div><div className='mt-1 flex gap-1 text-[10px]'><span className='rounded bg-blue-100 px-1.5 py-0.5 text-blue-700'>BPJS</span><span className='rounded bg-violet-100 px-1.5 py-0.5 text-violet-700'>Controlled</span><span className='rounded bg-teal-100 px-1.5 py-0.5 text-teal-700'>FEFO</span><span className='rounded bg-amber-100 px-1.5 py-0.5 text-amber-700'>Near Expired</span></div></td>
                  <td className='p-2'><select value={l.batch_id||''} onChange={e=>chooseBatch(i,e.target.value)} className='h-10 w-full rounded-lg border border-slate-200 px-2 text-xs' disabled={!l.item_id}><option value=''>Pilih batch</option>{(l.batch_options||[]).map(b=><option key={b.id} value={b.id}>{b.batch_no}{b.expired_date?` (EXP ${b.expired_date})`:''}</option>)}</select></td>
                  <td className='p-2 text-xs text-slate-600'>{selectedBatch?.expired_date || '-'}</td>
                  <td className='p-2 text-center'><span className={`rounded-full px-2 py-1 text-xs ${stockTone(l.available_stock)}`}>{l.available_stock ?? 'Unknown'}</span></td>
                  <td className='p-2'><input className='h-10 w-28 rounded-lg border border-slate-200 px-2 text-right' type='number' value={l.unit_price} onChange={e=>patchLine(i,{unit_price:e.target.value})}/></td>
                  <td className='p-2'><input className='h-10 w-20 rounded-lg border border-slate-200 px-2 text-center' type='number' value={l.qty_sold} onChange={e=>{patchLine(i,{qty_sold:e.target.value});resolvePrice(i);}}/></td>
                  <td className='p-2'><input className='h-10 w-20 rounded-lg border border-slate-200 px-2 text-right' type='number' value={l.discount_percent} onChange={e=>patchLine(i,{discount_percent:e.target.value})}/></td>
                  <td className='p-2'><input className='h-10 w-20 rounded-lg border border-slate-200 px-2 text-right' type='number' value={l.tax_percent} onChange={e=>patchLine(i,{tax_percent:e.target.value})}/></td>
                  <td className='p-2 text-right font-semibold'>{money(l.line_total)}</td>
                  <td className='p-2'><div className='flex items-center justify-center gap-1'><button type='button' onClick={()=>duplicateLine(i)} className='rounded p-1 text-slate-500 hover:bg-slate-200'><IconCopy className='h-4 w-4'/></button><button type='button' onClick={()=>removeLine(i)} className='rounded p-1 text-rose-600 hover:bg-rose-100'><IconTrash className='h-4 w-4'/></button><button type='button' className='rounded p-1 text-slate-500 hover:bg-slate-200'><IconAdjustmentsHorizontal className='h-4 w-4'/></button></div></td>
                </tr>;})}</tbody>
              </table>
            </div>
            <div className='mt-3'><Button type='button' label='Add Line' variant='orange' onClick={addLine}/></div>
          </Card>

          <Card title='Order Summary'>
            <div className='space-y-2 text-sm text-slate-600'>
              <div className='flex justify-between'><span>Total item</span><span>{data.lines.length}</span></div>
              <div className='flex justify-between'><span>Total qty</span><span>{totals.qty}</span></div>
              <div className='flex justify-between'><span>Subtotal</span><span>{money(totals.subtotal)}</span></div>
              <div className='flex justify-between'><span>Discount</span><span>{money(totals.discount)}</span></div>
              <div className='flex justify-between'><span>Tax</span><span>{money(totals.tax)}</span></div>
              <div className='mt-4 rounded-xl bg-slate-900 p-4 text-white'><div className='text-xs'>Grand Total</div><div className='text-2xl font-semibold'>{money(totals.grand)}</div></div>
            </div>
          </Card>
        </div>
      </div>

      <div className='fixed bottom-0 left-0 right-0 border-t border-slate-200 bg-white/95 backdrop-blur'>
        <div className='mx-auto flex max-w-[1600px] items-center justify-between gap-3 px-4 py-3 md:px-6'>
          <div className='hidden items-center gap-2 text-xs text-slate-500 md:flex'><IconClipboardList className='h-4 w-4'/> F2 Search • F4 Save • F9 Submit • Del Remove • Ctrl+D Duplicate</div>
          <div className='flex gap-2'>
            <Button id='so-save-draft' type='submit' label='Save Draft' variant='gray' disabled={processing}/>
            <Button id='so-submit' type='submit' label='Submit Order' variant='orange' disabled={processing}/>
            <Link href={route('apps.customers.show',customer.id)} className='rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700'>Cancel</Link>
          </div>
        </div>
      </div>
    </form>
  </>;
}
Page.layout=(p)=><AppLayout children={p}/>;
