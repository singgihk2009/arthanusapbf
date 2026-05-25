import React, { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import SmartItemInput from '@/Components/SmartItemInput';
import { Head, Link, useForm } from '@inertiajs/react';

const makeEmptyLine = () => ({ item_id: '', uom_id: '', min_qty: 1, price: 0, discount_percent: 0, tax_included: false, status: 'active' });

const uniqById = (list = []) => {
  const seen = new Set();
  return list.filter((item) => {
    const id = String(item?.id ?? '');
    if (!id || seen.has(id)) return false;
    seen.add(id);
    return true;
  });
};

export default function Form({ priceList, uoms = [] }) {
  const isEdit = Boolean(priceList?.id);

  const { data, setData, post, put, processing, errors } = useForm({
    code: priceList?.code || '',
    name: priceList?.name || '',
    description: priceList?.description || '',
    effective_from: priceList?.effective_from || '',
    effective_to: priceList?.effective_to || '',
    status: priceList?.status || 'active',
    is_default: !!priceList?.is_default,
    lines: priceList?.lines?.length
      ? priceList.lines.map((line) => ({ ...line, tax_included: !!line.tax_included }))
      : [makeEmptyLine()],
  });

  const [search, setSearch] = useState('');
  const [items, setItems] = useState([]);
  const [searchResetKey, setSearchResetKey] = useState(0);
  const [lineItemCache, setLineItemCache] = useState(() =>
    (priceList?.lines || []).map((line) => line.item).filter(Boolean),
  );

  const fetchItems = async (query) => {
    setSearch(query);
    const response = await fetch(`${route('apps.items.search')}?q=${encodeURIComponent(query)}`);
    const json = await response.json();
    setItems(Array.isArray(json) ? json : []);
  };

  useEffect(() => {
    fetchItems('');
  }, []);

  const itemOptions = useMemo(() => uniqById([...(items || []), ...(lineItemCache || [])]), [items, lineItemCache]);

  const submit = (event) => {
    event.preventDefault();
    if (isEdit) {
      put(route('apps.price-lists.update', priceList.id));
      return;
    }

    post(route('apps.price-lists.store'));
  };

  const setLine = (index, key, value) => {
    setData('lines', data.lines.map((line, currentIndex) => (currentIndex === index ? { ...line, [key]: value } : line)));
  };

  const onSelectRowItem = (index, item) => {
    if (!item?.id) return;
    setData('lines', data.lines.map((line, currentIndex) => (currentIndex === index ? { ...line, item_id: String(item.id), item_name: item.name || item.label || '' } : line)));
    setLineItemCache((prev) => uniqById([item, ...prev]));
  };

  const addScannedItem = (item) => {
    if (!item?.id) return;

    setLineItemCache((prev) => uniqById([item, ...prev]));

    const firstEmptyLineIndex = data.lines.findIndex((line) => !String(line.item_id || '').trim());
    if (firstEmptyLineIndex >= 0) {
      setData('lines', data.lines.map((line, index) => (index === firstEmptyLineIndex ? { ...line, item_id: String(item.id), item_name: item.name || item.label || '' } : line)));
    } else {
      setData('lines', [...data.lines, { ...makeEmptyLine(), item_id: String(item.id), item_name: item.name || item.label || '' }]);
    }

    setSearchResetKey((prev) => prev + 1);
  };

  return (
    <>
      <Head title={isEdit ? 'Edit Price List' : 'Create Price List'} />
      <Card
        title={isEdit ? 'Edit Price List' : 'Create Price List'}
        form={submit}
        footer={(
          <div className='flex items-center gap-2'>
            <Button type='submit' label='Save' disabled={processing} variant='orange' />
            <Link href={route('apps.price-lists.index')} className='rounded-lg border border-rose-300 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50'>
              Cancel
            </Link>
          </div>
        )}
      >
        <div className='space-y-4'>
          {data.is_default && data.status === 'active' && (
            <div className='rounded-md bg-yellow-100 p-2 text-sm text-yellow-800'>
              This will replace the current default active price list.
            </div>
          )}

          <div className='grid grid-cols-1 gap-4 md:grid-cols-3'>
            <div className='flex flex-col gap-2'>
              <label className='text-sm text-gray-600'>Code</label>
              <input className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' placeholder='Code' value={data.code} onChange={(e) => setData('code', e.target.value)} />
            </div>
            <div className='flex flex-col gap-2'>
              <label className='text-sm text-gray-600'>Name</label>
              <input className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' placeholder='Name' value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </div>
            <div className='flex flex-col gap-2'>
              <label className='text-sm text-gray-600'>Status</label>
              <select className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' value={data.status} onChange={(e) => setData('status', e.target.value)}><option value='active'>active</option><option value='inactive'>inactive</option></select>
            </div>
            <div className='flex flex-col gap-2'>
              <label className='text-sm text-gray-600'>Effective From</label>
              <input type='date' className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' value={data.effective_from || ''} onChange={(e) => setData('effective_from', e.target.value)} />
            </div>
            <div className='flex flex-col gap-2'>
              <label className='text-sm text-gray-600'>Effective To</label>
              <input type='date' className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' value={data.effective_to || ''} onChange={(e) => setData('effective_to', e.target.value)} />
            </div>
            <div className='flex items-end'>
              <label className='inline-flex items-center gap-2 text-sm text-gray-700'><input type='checkbox' checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} /> Is Default</label>
            </div>
          </div>

          <div className='flex flex-col gap-2'>
            <label className='text-sm text-gray-600'>Description</label>
            <textarea className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' placeholder='Description' value={data.description || ''} onChange={(e) => setData('description', e.target.value)} />
          </div>

          <div className='rounded border border-gray-200 p-3'>
            <label className='mb-2 block text-sm text-gray-600'>Add Item (Kasir Mode)</label>
            <SmartItemInput
              key={searchResetKey}
              value={{ id: searchResetKey, name: '' }}
              onSelect={addScannedItem}
              placeholder='Scan barcode / SKU / product name...'
              inputClassName='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700'
            />
            <div className='mt-2 text-xs text-gray-500'>Tekan Enter saat scan barcode untuk tambah item otomatis seperti Sales Order.</div>
          </div>

          <div className='rounded border border-gray-200 p-3'>
            <label className='mb-2 block text-sm text-gray-600'>Search Item (Manual List)</label>
            <input className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700' placeholder='Search item...' value={search} onChange={(e) => fetchItems(e.target.value)} />
            {search.trim().length > 0 && (
              <div className='mt-2 max-h-52 overflow-auto rounded-md border border-gray-200'>
                {items.length === 0 ? (
                  <div className='px-3 py-2 text-xs text-gray-500'>Item tidak ditemukan. Coba kata kunci lain atau scan barcode pada Kasir Mode.</div>
                ) : (
                  items.map((item) => (
                    <button
                      key={item.id}
                      type='button'
                      className='flex w-full items-center justify-between border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-orange-50 last:border-b-0'
                      onClick={() => addScannedItem(item)}
                    >
                      <span className='text-gray-700'>{item.name}</span>
                      <span className='text-xs text-gray-500'>{item.sku || item.barcode || '-'}</span>
                    </button>
                  ))
                )}
              </div>
            )}
          </div>

          <div className='overflow-x-auto'>
            <table className='w-full min-w-[900px] border border-gray-200 text-sm'>
              <thead>
                <tr className='bg-gray-50'>
                  <th className='border px-2 py-2 text-left'>Item</th><th className='border px-2 py-2 text-left'>UOM</th><th className='border px-2 py-2'>Min Qty</th><th className='border px-2 py-2'>Price</th><th className='border px-2 py-2'>Disc%</th><th className='border px-2 py-2'>Tax Inc</th><th className='border px-2 py-2'>Status</th><th className='border px-2 py-2'>Action</th>
                </tr>
              </thead>
              <tbody>
                {data.lines.map((line, index) => (
                  <tr key={index} className='border-t'>
                    <td className='border px-2 py-2'>
                      <SmartItemInput
                        value={itemOptions.find((item) => String(item.id) === String(line.item_id)) || { id: line.item_id || `line-${index}`, name: line.item_name || '' }}
                        onSelect={(item) => onSelectRowItem(index, item)}
                        placeholder='Scan barcode / SKU / product name...'
                        inputClassName='w-full rounded border border-gray-200 px-2 py-1 text-sm'
                      />
                    </td>
                    <td className='border px-2 py-2'><select className='w-full rounded border border-gray-200 px-2 py-1' value={line.uom_id || ''} onChange={(e) => setLine(index, 'uom_id', e.target.value || null)}><option value=''>-</option>{uoms.map((uom) => <option key={uom.id} value={uom.id}>{uom.name}</option>)}</select></td>
                    <td className='border px-2 py-2'><input className='w-24 rounded border border-gray-200 px-2 py-1 text-right' type='number' step='0.0001' value={line.min_qty} onChange={(e) => setLine(index, 'min_qty', e.target.value)} /></td>
                    <td className='border px-2 py-2'><input className='w-28 rounded border border-gray-200 px-2 py-1 text-right' type='number' step='0.01' value={line.price} onChange={(e) => setLine(index, 'price', e.target.value)} /></td>
                    <td className='border px-2 py-2'><input className='w-20 rounded border border-gray-200 px-2 py-1 text-right' type='number' step='0.01' value={line.discount_percent} onChange={(e) => setLine(index, 'discount_percent', e.target.value)} /></td>
                    <td className='border px-2 py-2 text-center'><input type='checkbox' checked={!!line.tax_included} onChange={(e) => setLine(index, 'tax_included', e.target.checked)} /></td>
                    <td className='border px-2 py-2'><select className='w-full rounded border border-gray-200 px-2 py-1' value={line.status} onChange={(e) => setLine(index, 'status', e.target.value)}><option value='active'>active</option><option value='inactive'>inactive</option></select></td>
                    <td className='border px-2 py-2 text-center'><button type='button' className='rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50' onClick={() => setData('lines', data.lines.filter((_, currentIndex) => currentIndex !== index))}>Remove</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
            {errors.lines && <div className='mt-2 text-sm text-red-600'>{errors.lines}</div>}
          </div>

          <div><Button type='button' label='Add Line' variant='gray' onClick={() => setData('lines', [...data.lines, makeEmptyLine()])} /></div>
        </div>
      </Card>
    </>
  );
}

Form.layout = (page) => <AppLayout children={page} />;
