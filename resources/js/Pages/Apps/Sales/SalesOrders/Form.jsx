import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import SmartItemInput from '@/Components/SmartItemInput';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { useMemo } from 'react';

const makeLine = () => ({ item_id:'', item_name:'', item_code:'', sku:'', barcode:'', batch_id:'', uom_id:'', uom_name:'', available_stock:null, cogs:0, qty_sold:1, unit_price:0, discount_percent:0, tax_percent:11, discount_amount:0, tax_amount:0, line_total:0, notes:'', price_list_id:null, price_list_line_id:null, batch_options:[] });
const money=(n)=>Number(n||0).toLocaleString('id-ID',{style:'currency',currency:'IDR'});

export default function Page({ customer, salesOrder, warehouses = [], priceList }) {
  const isEdit = Boolean(salesOrder?.id);
  const { data, setData, post, put, processing } = useForm({ warehouse_id: salesOrder?.warehouse_id || warehouses?.[0]?.id || '', document_date: salesOrder?.document_date || new Date().toISOString().slice(0,10), expected_delivery_date: salesOrder?.expected_delivery_date || '', price_list_id: salesOrder?.price_list_id || priceList?.id || '', notes: salesOrder?.notes || '', lines: salesOrder?.lines?.length ? salesOrder.lines.map(l=>({...makeLine(),...l,item_name:l.item?.name||l.item_name||'',item_code:l.item?.sku||l.item_code||'',sku:l.item?.sku||l.sku||'',barcode:l.item?.default_barcode||l.barcode||'',uom_name:l.uom?.name||l.uom_name||''})) : [makeLine()] });
  const totals=useMemo(()=>data.lines.reduce((a,l)=>{const gross=+l.qty_sold*(+l.unit_price);const disc=gross*(+l.discount_percent)/100;const tax=(gross-disc)*(+l.tax_percent)/100;a.subtotal+=gross;a.discount+=disc;a.tax+=tax;a.grand+=gross-disc+tax;return a;},{subtotal:0,discount:0,tax:0,grand:0}),[data.lines]);
  const patchLine=(i,patch)=>{const ls=[...data.lines];ls[i]={...ls[i],...patch};const l=ls[i];const gross=+l.qty_sold*(+l.unit_price);const disc=gross*(+l.discount_percent)/100;const tax=(gross-disc)*(+l.tax_percent)/100;ls[i].discount_amount=disc;ls[i].tax_amount=tax;ls[i].line_total=gross-disc+tax;setData('lines',ls);};

  const chooseItem=async(i,item)=>{patchLine(i,{item_id:item.id,item_name:item.name||'',item_code:item.code||item.sku||'',sku:item.sku||'',barcode:item.barcode||'',uom_id:item.uom_id||'',uom_name:item.uom_name||'',available_stock:item.available_stock,cogs:item.cogs||0,batch_id:'',qty_sold:data.lines[i]?.qty_sold || 1}); const b=await axios.get(route('apps.sales-orders.batches'),{params:{item_id:item.id,warehouse_id:data.warehouse_id||null}}); patchLine(i,{batch_options:b.data||[]}); await resolvePrice(i);};
  const chooseBatch=(i,batchId)=>{const b=(data.lines[i].batch_options||[]).find(x=>String(x.id)===String(batchId)); patchLine(i,{batch_id:batchId,available_stock:b?.available_stock ?? data.lines[i].available_stock,cogs:b?.cogs ?? data.lines[i].cogs});};
  const resolvePrice=async(i)=>{const l=data.lines[i]; if(!l.item_id) return; const r=await axios.get(route('apps.price-lists.resolve-price'),{params:{item_id:l.item_id,qty:l.qty_sold,uom_id:l.uom_id,date:data.document_date,price_list_id:data.price_list_id}}); patchLine(i,{unit_price:r.data.unit_price||0,discount_percent:r.data.discount_percent||0,price_list_id:r.data.price_list_id||null,price_list_line_id:r.data.price_list_line_id||null});};
  const save=(e)=>{e.preventDefault(); isEdit ? put(route('apps.sales-orders.update',salesOrder.id)) : post(route('apps.customers.sales-orders.store',customer.id));};

  return <><Head title='Sales Order Form'/><Card title={`${isEdit?'Edit':'Create'} Sales Order`} form={save} footer={<div className='flex gap-2'><Button type='submit' label='Save Draft' variant='gray' disabled={processing}/><Link href={route('apps.customers.show',customer.id)} className='px-3 py-2 border rounded text-sm'>Cancel</Link></div>}><div className='space-y-4'>
    <div className='grid md:grid-cols-2 gap-3'><select value={data.warehouse_id} onChange={e=>setData('warehouse_id',e.target.value)} className='border rounded p-2'><option value=''>Warehouse</option>{warehouses.map(w=><option key={w.id} value={w.id}>{w.name}</option>)}</select><Input type='date' label='Document Date' value={data.document_date} onChange={e=>setData('document_date',e.target.value)}/></div>
    <table className='w-full text-xs border'><thead><tr><th>Item</th><th>UoM</th><th>Batch</th><th>Avail Qty</th><th>Price</th><th>COGS</th><th>Qty</th><th>Total</th></tr></thead><tbody>{data.lines.map((l,i)=><tr key={i}><td className='border p-1 space-y-1'><SmartItemInput value={l.item_id ? { id: l.item_id, name: l.item_name } : null} onSelect={(item)=>chooseItem(i,item)} warehouseId={data.warehouse_id} placeholder='Scan barcode / type SKU / type product name...'/>{l.item_id ? <div className='text-[10px] rounded border p-1 bg-gray-50'><div className='font-medium'>{l.item_name||'-'}</div><div>SKU/Code: {l.sku || l.item_code || '-'}</div><div>Barcode: {l.barcode || '-'}</div></div> : null}</td><td>{l.uom_name||'-'}</td><td><select value={l.batch_id||''} onChange={e=>chooseBatch(i,e.target.value)} className='border p-1 w-full' disabled={!l.item_id}><option value=''>Pilih batch</option>{(l.batch_options||[]).map(b=><option key={b.id} value={b.id}>{b.batch_no}{b.expired_date?` (EXP ${b.expired_date})`:''}</option>)}</select></td><td>{l.available_stock??'Unknown'}</td><td><input type='number' value={l.unit_price} onChange={e=>patchLine(i,{unit_price:e.target.value})}/></td><td>{money(l.cogs)}</td><td><input type='number' value={l.qty_sold} onChange={e=>{patchLine(i,{qty_sold:e.target.value});resolvePrice(i);}}/></td><td>{money(l.line_total)}</td></tr>)}</tbody></table>
    <Button type='button' label='Add Line' variant='orange' onClick={()=>setData('lines',[...data.lines,makeLine()])}/>
    <div className='text-sm'>Grand Total: <b>{money(totals.grand)}</b></div>
  </div></Card></>;
}
Page.layout=(p)=><AppLayout children={p}/>;
