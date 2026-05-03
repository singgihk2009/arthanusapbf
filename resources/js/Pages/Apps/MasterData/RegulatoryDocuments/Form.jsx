import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Form({ title, action, method = 'post', document = null, categories = [] }) {
  const { data, setData, post, put, processing, errors } = useForm({
    code: document?.code ?? '',
    name: document?.name ?? '',
    category: document?.category ?? '',
    description: document?.description ?? '',
    is_required: Boolean(document?.is_required ?? false),
    is_critical: Boolean(document?.is_critical ?? false),
    blocks_transaction: Boolean(document?.blocks_transaction ?? false),
    requires_expiry_date: Boolean(document?.requires_expiry_date ?? true),
    default_validity_days: document?.default_validity_days ?? '',
    applicable_vendor_type: document?.applicable_vendor_type ?? '',
    is_active: Boolean(document?.is_active ?? true),
    sort_order: document?.sort_order ?? 0,
  });

  const submit = (e) => {
    e.preventDefault();
    if (method === 'put') return put(action);
    post(action);
  };

  return <>
    <Head title={title} />
    <div className='mx-auto max-w-3xl rounded bg-white p-6 shadow'>
      <h1 className='mb-4 text-xl font-semibold'>{title}</h1>
      <form onSubmit={submit} className='grid gap-3'>
        <input className='rounded border px-3 py-2' placeholder='Code' value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} />
        {errors.code && <p className='text-xs text-red-600'>{errors.code}</p>}
        <input className='rounded border px-3 py-2' placeholder='Name' value={data.name} onChange={e => setData('name', e.target.value)} />
        <select className='rounded border px-3 py-2' value={data.category || ''} onChange={e => setData('category', e.target.value || null)}>
          <option value=''>- Category -</option>
          {categories.map((item) => <option key={item} value={item}>{item}</option>)}
        </select>
        <textarea className='rounded border px-3 py-2' placeholder='Description' value={data.description || ''} onChange={e => setData('description', e.target.value)} />
        <input className='rounded border px-3 py-2' placeholder='Default Validity Days' type='number' min='1' value={data.default_validity_days ?? ''} onChange={e => setData('default_validity_days', e.target.value)} />
        <input className='rounded border px-3 py-2' placeholder='Applicable Vendor Type' value={data.applicable_vendor_type || ''} onChange={e => setData('applicable_vendor_type', e.target.value)} />
        <input className='rounded border px-3 py-2' placeholder='Sort Order' type='number' value={data.sort_order} onChange={e => setData('sort_order', Number(e.target.value || 0))} />
        <div className='grid grid-cols-2 gap-2'>
          {[
            ['is_required', 'Is Required'],
            ['is_critical', 'Is Critical'],
            ['blocks_transaction', 'Blocks Transaction'],
            ['requires_expiry_date', 'Requires Expiry Date'],
            ['is_active', 'Is Active'],
          ].map(([key, label]) => (
            <label key={key} className='flex items-center gap-2 text-sm'><input type='checkbox' checked={Boolean(data[key])} onChange={e => setData(key, e.target.checked)} /> {label}</label>
          ))}
        </div>
        <button disabled={processing} className='rounded bg-blue-600 px-4 py-2 text-white'>Simpan</button>
      </form>
    </div>
  </>;
}

Form.layout = (page) => <AppLayout children={page} />;
