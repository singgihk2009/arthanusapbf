import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function Show({ priceList, summary }) {
  return <AppLayout><div className='p-6 space-y-4'>
    <div className='flex justify-between'><h1 className='text-xl font-semibold'>{priceList.code} - {priceList.name}</h1><Link href={route('apps.price-lists.edit', priceList.id)} className='px-3 py-2 border rounded'>Edit</Link></div>
    <div className='grid grid-cols-4 gap-3 text-sm'><div className='border p-3'>Total Lines: {summary.total_lines}</div><div className='border p-3'>Active Lines: {summary.active_lines}</div><div className='border p-3'>Effective: {summary.effective_period}</div><div className='border p-3'>Default: {summary.is_default ? 'Yes' : 'No'}</div></div>
    <table className='w-full border text-sm'><thead><tr><th>Item Code</th><th>Item Name</th><th>UOM</th><th>Min Qty</th><th>Price</th><th>Discount %</th><th>Tax Included</th><th>Status</th></tr></thead><tbody>{priceList.lines.map(l => <tr className='border-t' key={l.id}><td>{l.item?.sku}</td><td>{l.item?.name}</td><td>{l.uom?.name || l.item?.base_uom?.name || '-'}</td><td>{l.min_qty}</td><td>{l.price}</td><td>{l.discount_percent}</td><td>{l.tax_included ? 'Yes' : 'No'}</td><td>{l.status}</td></tr>)}</tbody></table>
  </div></AppLayout>;
}
