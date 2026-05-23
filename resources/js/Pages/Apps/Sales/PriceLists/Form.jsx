import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';

const emptyLine = { item_id:'', uom_id:'', min_qty:1, price:0, discount_percent:0, tax_included:false, status:'active' };

export default function Form({ priceList, uoms = [] }) {
  const { data, setData, post, put, processing, errors } = useForm({
    code: priceList?.code || '', name: priceList?.name || '', description: priceList?.description || '', effective_from: priceList?.effective_from || '', effective_to: priceList?.effective_to || '', status: priceList?.status || 'active', is_default: !!priceList?.is_default,
    lines: priceList?.lines?.length ? priceList.lines.map(l => ({...l, tax_included:!!l.tax_included})) : [emptyLine],
  });
  const [search, setSearch] = useState(''); const [items, setItems] = useState([]);
  const fetchItems = async (q) => { setSearch(q); const r = await fetch(route('apps.items.search')+`?q=${encodeURIComponent(q)}`); setItems(await r.json()); };
  const submit = (e) => { e.preventDefault(); if (priceList) put(route('apps.price-lists.update', priceList.id)); else post(route('apps.price-lists.store')); };
  const setLine = (i,k,v)=> setData('lines', data.lines.map((x,idx)=> idx===i? {...x,[k]:v}:x));

  return <AppLayout><form onSubmit={submit} className='p-6 space-y-4'>
    <h1 className='text-xl font-semibold'>{priceList ? 'Edit' : 'Create'} Price List</h1>
    {data.is_default && data.status === 'active' && <div className='p-2 bg-yellow-100 text-yellow-800 rounded'>This will replace the current default active price list.</div>}
    <div className='grid grid-cols-3 gap-3'>
      <input className='border p-2' placeholder='Code' value={data.code} onChange={e=>setData('code',e.target.value)} />
      <input className='border p-2' placeholder='Name' value={data.name} onChange={e=>setData('name',e.target.value)} />
      <select className='border p-2' value={data.status} onChange={e=>setData('status',e.target.value)}><option value='active'>active</option><option value='inactive'>inactive</option></select>
      <input type='date' className='border p-2' value={data.effective_from || ''} onChange={e=>setData('effective_from',e.target.value)} />
      <input type='date' className='border p-2' value={data.effective_to || ''} onChange={e=>setData('effective_to',e.target.value)} />
      <label><input type='checkbox' checked={data.is_default} onChange={e=>setData('is_default', e.target.checked)} /> Is Default</label>
    </div>
    <textarea className='border p-2 w-full' placeholder='Description' value={data.description || ''} onChange={e=>setData('description',e.target.value)} />
    <div><input className='border p-2 mb-2' placeholder='Search item...' value={search} onChange={e=>fetchItems(e.target.value)} /><table className='w-full text-sm border'><thead><tr><th>Item</th><th>UOM</th><th>Min Qty</th><th>Price</th><th>Disc%</th><th>Tax Inc</th><th>Status</th><th></th></tr></thead><tbody>
      {data.lines.map((line, i) => <tr key={i} className='border-t'>
        <td><select className='border p-1' value={line.item_id} onChange={e=>setLine(i,'item_id',e.target.value)}><option value=''>Select Item</option>{items.map(it => <option key={it.id} value={it.id}>{it.code} - {it.name}</option>)}</select></td>
        <td><select className='border p-1' value={line.uom_id || ''} onChange={e=>setLine(i,'uom_id',e.target.value || null)}><option value=''>-</option>{uoms.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}</select></td>
        <td><input className='border p-1 w-24' type='number' step='0.0001' value={line.min_qty} onChange={e=>setLine(i,'min_qty',e.target.value)} /></td>
        <td><input className='border p-1 w-28' type='number' step='0.01' value={line.price} onChange={e=>setLine(i,'price',e.target.value)} /></td>
        <td><input className='border p-1 w-20' type='number' step='0.01' value={line.discount_percent} onChange={e=>setLine(i,'discount_percent',e.target.value)} /></td>
        <td><input type='checkbox' checked={!!line.tax_included} onChange={e=>setLine(i,'tax_included',e.target.checked)} /></td>
        <td><select value={line.status} onChange={e=>setLine(i,'status',e.target.value)}><option value='active'>active</option><option value='inactive'>inactive</option></select></td>
        <td><button type='button' onClick={()=>setData('lines', data.lines.filter((_,idx)=>idx!==i))}>Remove</button></td>
      </tr>)}
    </tbody></table>
    {errors.lines && <div className='text-red-600 text-sm'>{errors.lines}</div>}
    </div>
    <button type='button' className='px-3 py-1 border rounded' onClick={()=>setData('lines', [...data.lines, {...emptyLine}])}>Add Line</button>
    <div className='space-x-2'><button disabled={processing} className='px-4 py-2 bg-indigo-600 text-white rounded'>Save</button><Link href={route('apps.price-lists.index')} className='px-4 py-2 border rounded'>Cancel</Link></div>
  </form></AppLayout>;
}
