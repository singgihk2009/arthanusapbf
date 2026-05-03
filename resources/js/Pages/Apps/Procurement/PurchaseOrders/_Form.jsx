import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

const emptyItem = { product_id: '', product_name: '', uom_id: '', qty_ordered: 1, unit_price: 0, discount_amount: 0, tax_amount: 0, line_total: 0, notes: '' };

export default function Form({ purchaseOrder = null, vendors = [], products = [], uoms = [] }) {
    const isEdit = !!purchaseOrder;
    const { data, setData, post, put, processing, errors } = useForm({
        vendor_id: purchaseOrder?.vendor_id || '',
        po_date: purchaseOrder?.po_date || '',
        expected_delivery_date: purchaseOrder?.expected_delivery_date || '',
        notes: purchaseOrder?.notes || '',
        items: purchaseOrder?.items?.length ? purchaseOrder.items : [emptyItem],
    });

    const productUomMap = useMemo(() => Object.fromEntries(products.map((p) => [String(p.id), p.base_uom_id ? String(p.base_uom_id) : ''])), [products]);

    const setItem = (index, key, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [key]: value };

        if (key === 'product_id') {
            const defaultUom = productUomMap[String(value)] || '';
            if (defaultUom) items[index].uom_id = defaultUom;
        }
        const base = (+items[index].qty_ordered || 0) * (+items[index].unit_price || 0);
        items[index].line_total = base - (+items[index].discount_amount || 0) + (+items[index].tax_amount || 0);
        setData('items', items);
    };

    const totals = data.items.reduce((a, i) => ({ subtotal: a.subtotal + ((+i.qty_ordered || 0) * (+i.unit_price || 0)), discount: a.discount + (+i.discount_amount || 0), tax: a.tax + (+i.tax_amount || 0), grand: a.grand + (+i.line_total || 0) }), { subtotal: 0, discount: 0, tax: 0, grand: 0 });
    const submit = (e) => { e.preventDefault(); isEdit ? put(route('apps.procurement.purchase-orders.update', purchaseOrder.id)) : post(route('apps.procurement.purchase-orders.store')); };

    return <>
        <Head title={isEdit ? 'Edit Purchase Order' : 'Create Purchase Order'} />
        <Card title={isEdit ? 'Edit Purchase Order' : 'Create Purchase Order'} form={submit} footer={<Button type='submit' label='Save Draft' disabled={processing} variant='gray' />}>
            <div className='grid grid-cols-1 gap-4 md:grid-cols-2'>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Vendor</label><select value={data.vendor_id} onChange={(e) => setData('vendor_id', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'><option value=''>-</option>{vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}</select>{errors.vendor_id && <small className='text-xs text-red-500'>{errors.vendor_id}</small>}</div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>PO Date</label><input type='date' value={data.po_date} onChange={(e) => setData('po_date', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' />{errors.po_date && <small className='text-xs text-red-500'>{errors.po_date}</small>}</div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Expected Delivery Date</label><input type='date' value={data.expected_delivery_date} onChange={(e) => setData('expected_delivery_date', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' /></div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Notes</label><textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' /></div>
            </div>
            <div className='mt-4 space-y-2'>
                <div className='hidden md:grid md:grid-cols-9 md:gap-2 px-2 text-xs font-semibold text-gray-600'>
                    <div>Produk Master</div><div>Nama Produk</div><div>UoM</div><div>Qty</div><div>Harga</div><div>Diskon</div><div>Pajak</div><div>Total</div><div>Aksi</div>
                </div>
                {data.items.map((it, i) => <div key={i} className='grid grid-cols-1 gap-2 rounded border border-gray-200 p-2 md:grid-cols-9 dark:border-gray-800'>
                    <select value={it.product_id || ''} onChange={(e) => setItem(i, 'product_id', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900'><option value=''>Product</option>{products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>
                    <input value={it.product_name || ''} onChange={(e) => setItem(i, 'product_name', e.target.value)} placeholder='Nama produk' className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <select value={it.uom_id || ''} onChange={(e) => setItem(i, 'uom_id', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900'><option value=''>UOM</option>{uoms.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select>
                    <input type='number' value={it.qty_ordered} onChange={(e) => setItem(i, 'qty_ordered', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <input type='number' value={it.unit_price} onChange={(e) => setItem(i, 'unit_price', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <input type='number' value={it.discount_amount} onChange={(e) => setItem(i, 'discount_amount', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <input type='number' value={it.tax_amount} onChange={(e) => setItem(i, 'tax_amount', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <div className='flex items-center text-sm'>{Number(it.line_total || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <button type='button' onClick={() => setData('items', data.items.filter((_, idx) => idx !== i))} className='rounded border border-rose-400 px-2 py-1 text-xs text-rose-600'>Remove</button>
                </div>)}
                <button type='button' onClick={() => setData('items', [...data.items, emptyItem])} className='rounded border border-gray-300 px-3 py-1 text-sm'>+ Add Item</button>
            </div>
            <div className='mt-3 text-right text-sm font-medium'>Subtotal: {totals.subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | Discount: {totals.discount.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | Tax: {totals.tax.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | Grand Total: {totals.grand.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        </Card>
    </>;
}

Form.layout = (page) => <AppLayout children={page} />;
