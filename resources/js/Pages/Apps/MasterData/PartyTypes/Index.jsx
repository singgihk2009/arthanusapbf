import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, router, useForm } from '@inertiajs/react';

const emptyForm = { id: null, category: 'vendor', code: '', name: '', prefix: '', sort_order: 0, is_active: true };

export default function Index({ partyTypes, filters = {} }) {
  const { data, setData, processing, errors, reset } = useForm(emptyForm);
  const rows = partyTypes?.data ?? [];
  const isEdit = Boolean(data.id);

  const submit = (e) => {
    e.preventDefault();
    const payload = {
      ...data,
      code: String(data.code).toUpperCase(),
      prefix: String(data.prefix).toUpperCase(),
      sort_order: Number(data.sort_order || 0),
      is_active: Boolean(data.is_active),
    };

    if (isEdit) {
      router.put(route('apps.master-data.party-types.update', data.id), payload, { onSuccess: () => reset() });
      return;
    }

    router.post(route('apps.master-data.party-types.store'), payload, { onSuccess: () => reset() });
  };

  const edit = (row) => {
    setData({
      id: row.id,
      category: row.category,
      code: row.code,
      name: row.name,
      prefix: row.prefix,
      sort_order: row.sort_order ?? 0,
      is_active: Boolean(row.is_active),
    });
  };

  const destroy = (row) => {
    if (confirm(`Hapus type ${row.name}?`)) {
      router.delete(route('apps.master-data.party-types.destroy', row.id));
    }
  };

  const filterCategory = (category) => {
    router.get(route('apps.master-data.party-types.index'), category ? { category } : {}, { preserveState: true });
  };

  return (
    <>
      <Head title='Master Vendor & Customer Type' />
      <Card title='Master Vendor & Customer Type' form={submit} footer={(
        <div className='flex gap-2'>
          <Button type='submit' label={isEdit ? 'Update' : 'Tambah'} variant='gray' disabled={processing} />
          <Button type='button' label='Reset' variant='orange' onClick={() => reset()} disabled={processing} />
        </div>
      )}>
        <div className='grid grid-cols-1 gap-3 md:grid-cols-6'>
          <div className='flex flex-col gap-2'>
            <label className='text-sm text-gray-600'>Category</label>
            <select value={data.category} onChange={(e) => setData('category', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700'>
              <option value='vendor'>Vendor</option>
              <option value='customer'>Customer</option>
            </select>
            {errors.category && <small className='text-xs text-red-500'>{errors.category}</small>}
          </div>
          <Input label='Code' value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} errors={errors.code} />
          <Input label='Name' value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
          <Input label='Prefix' value={data.prefix} onChange={(e) => setData('prefix', e.target.value.toUpperCase())} errors={errors.prefix} />
          <Input label='Sort Order' type='number' value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} errors={errors.sort_order} />
          <label className='mt-7 flex items-center gap-2 text-sm text-gray-600'>
            <input type='checkbox' checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Active
          </label>
        </div>
      </Card>

      <Card title='Daftar Type'>
        <div className='mb-3 flex gap-2'>
          <Button type='button' label='Semua' variant={!filters.category ? 'gray' : 'orange'} onClick={() => filterCategory('')} />
          <Button type='button' label='Vendor' variant={filters.category === 'vendor' ? 'gray' : 'orange'} onClick={() => filterCategory('vendor')} />
          <Button type='button' label='Customer' variant={filters.category === 'customer' ? 'gray' : 'orange'} onClick={() => filterCategory('customer')} />
        </div>
        <div className='overflow-x-auto'>
          <table className='min-w-full text-sm'>
            <thead className='bg-gray-50'><tr>{['Category', 'Code', 'Name', 'Prefix', 'Active', 'Action'].map((h) => <th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr></thead>
            <tbody>
              {rows.map((row) => <tr key={row.id} className='border-t'>
                <td className='px-3 py-2 capitalize'>{row.category}</td>
                <td className='px-3 py-2'>{row.code}</td>
                <td className='px-3 py-2'>{row.name}</td>
                <td className='px-3 py-2'>{row.prefix}</td>
                <td className='px-3 py-2'>{row.is_active ? 'Yes' : 'No'}</td>
                <td className='px-3 py-2'><button type='button' className='mr-2 text-indigo-600' onClick={() => edit(row)}>Edit</button><button type='button' className='text-red-600' onClick={() => destroy(row)}>Delete</button></td>
              </tr>)}
              {rows.length === 0 && <tr><td colSpan='6' className='px-3 py-4 text-center text-gray-500'>Belum ada data.</td></tr>}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  );
}

Index.layout = (page) => <AppLayout children={page} />;
