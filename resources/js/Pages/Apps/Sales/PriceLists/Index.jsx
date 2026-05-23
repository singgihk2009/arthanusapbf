import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function Index({ priceLists, filters = {} }) {
  const onFilter = (e) => {
    e.preventDefault();
    const f = Object.fromEntries(new FormData(e.currentTarget).entries());
    router.get(route('apps.price-lists.index'), f, { preserveState: true });
  };

  return <AppLayout><div className='p-6 space-y-4'>
    <div className='flex justify-between'><h1 className='text-xl font-semibold'>Price Lists</h1><Link href={route('apps.price-lists.create')} className='px-3 py-2 bg-indigo-600 text-white rounded'>Add Price List</Link></div>
    <form onSubmit={onFilter} className='flex gap-2'>
      <input name='search' defaultValue={filters.search || ''} placeholder='Search code/name/description' className='border rounded px-3 py-2 w-80' />
      <select name='status' defaultValue={filters.status || ''} className='border rounded px-2'><option value=''>All Status</option><option value='active'>Active</option><option value='inactive'>Inactive</option></select>
      <select name='is_default' defaultValue={filters.is_default || ''} className='border rounded px-2'><option value=''>All Default</option><option value='1'>Default</option><option value='0'>Non Default</option></select>
      <button className='px-3 py-2 border rounded'>Filter</button>
    </form>
    <table className='w-full text-sm border'><thead><tr className='bg-gray-50'><th>Code</th><th>Name</th><th>Effective From</th><th>Effective To</th><th>Default</th><th>Status</th><th>Total Lines</th><th>Actions</th></tr></thead>
      <tbody>{priceLists.data.map(p => <tr key={p.id} className='border-t'><td>{p.code}</td><td>{p.name}</td><td>{p.effective_from || '-'}</td><td>{p.effective_to || '-'}</td><td>{p.is_default ? 'Yes' : 'No'}</td><td>{p.status}</td><td>{p.lines_count}</td><td className='space-x-2'><Link href={route('apps.price-lists.show', p.id)}>View</Link><Link href={route('apps.price-lists.edit', p.id)}>Edit</Link></td></tr>)}</tbody></table>
  </div></AppLayout>;
}
