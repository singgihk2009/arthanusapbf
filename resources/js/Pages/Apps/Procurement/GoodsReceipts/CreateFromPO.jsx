import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import { Head, router, useForm } from '@inertiajs/react';

const toDateInputValue = (value) => {
    if (!value) return '';
    const str = String(value);
    return str.length >= 10 ? str.slice(0, 10) : str;
};

export default function CreateFromPO({ purchaseOrder, items, warehouses = [] }) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        purchase_order_id: purchaseOrder.id,
        received_date: toDateInputValue(new Date().toISOString()),
        warehouse_id: purchaseOrder.warehouse_id ? String(purchaseOrder.warehouse_id) : '',
        notes: '',
        items: items.map((item) => ({ ...item, received_qty: item.suggested_received_qty })),
    });

    const setItemQty = (index, value) => {
        const rows = [...data.items];
        const remainingQty = Number(rows[index].remaining_qty) || 0;
        const parsed = Number(value);

        if (Number.isNaN(parsed)) {
            rows[index].received_qty = '';
        } else {
            rows[index].received_qty = Math.max(0, Math.min(parsed, remainingQty));
        }

        setData('items', rows);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.procurement.goods-receipts.store'));
    };

    const handleBack = () => {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        router.get(route('apps.procurement.goods-receipts.index'));
    };

    const totalQty = data.items.reduce((sum, item) => sum + (Number(item.received_qty) || 0), 0);
    const totalValue = data.items.reduce((sum, item) => sum + ((Number(item.received_qty) || 0) * (Number(item.po_unit_price) || 0)), 0);

    return (
        <>
            <Head title='Create Goods Receipt' />
            <Card
                title={`Create Goods Receipt from ${purchaseOrder.po_number}`}
                form={submit}
                footer={(
                    <div className='flex items-center gap-2'>
                        <Button type='submit' label='Save Draft' disabled={processing} variant='gray' />
                        <button type='button' onClick={handleBack} className='rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50'>Back</button>
                        {isDirty && <span className='text-xs text-amber-600'>Data belum disimpan.</span>}
                    </div>
                )}
            >
                <div className='grid grid-cols-1 gap-4 md:grid-cols-2'>
                    <div className='flex flex-col gap-2'>
                        <label className='text-sm text-gray-600'>PO Number</label>
                        <input value={purchaseOrder.po_number} disabled className='w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700' />
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-sm text-gray-600'>Received Date</label>
                        <input type='date' value={data.received_date} onChange={(e) => setData('received_date', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' />
                        {errors.received_date && <small className='text-xs text-red-500'>{errors.received_date}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-sm text-gray-600'>Warehouse</label>
                        <select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'>
                            <option value=''>Pilih Warehouse</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>
                            ))}
                        </select>
                        {errors.warehouse_id && <small className='text-xs text-red-500'>{errors.warehouse_id}</small>}
                    </div>
                    <div className='flex flex-col gap-2 md:col-span-2'>
                        <label className='text-sm text-gray-600'>Notes</label>
                        <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' />
                        {errors.notes && <small className='text-xs text-red-500'>{errors.notes}</small>}
                    </div>
                </div>

                <div className='mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800'>
                    <table className='min-w-full text-sm'>
                        <thead className='bg-gray-50 text-gray-700 dark:bg-gray-900 dark:text-gray-300'>
                            <tr>
                                <th className='px-4 py-3 text-left font-semibold'>Product</th>
                                <th className='px-4 py-3 text-right font-semibold'>Remaining Qty</th>
                                <th className='px-4 py-3 text-right font-semibold'>Received Qty</th>
                                <th className='px-4 py-3 text-right font-semibold'>Unit Price</th>
                                <th className='px-4 py-3 text-right font-semibold'>Line Total</th>
                            </tr>
                        </thead>
                        <tbody className='divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-950'>
                            {data.items.map((item, index) => (
                                <tr key={item.purchase_order_item_id}>
                                    <td className='px-4 py-3 text-gray-700 dark:text-gray-300'>{item.product_name}</td>
                                    <td className='px-4 py-3 text-right text-gray-700 dark:text-gray-300'>{Number(item.remaining_qty || 0).toLocaleString('id-ID')}</td>
                                    <td className='px-4 py-3'>
                                        <input
                                            type='number'
                                            min='0'
                                            max={item.remaining_qty}
                                            value={item.received_qty}
                                            onChange={(e) => setItemQty(index, e.target.value)}
                                            className='w-32 rounded-md border border-gray-200 px-2 py-1 text-right text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'
                                        />
                                        {errors[`items.${index}.received_qty`] && (
                                            <small className='mt-1 block text-xs text-red-500'>{errors[`items.${index}.received_qty`]}</small>
                                        )}
                                    </td>
                                    <td className='px-4 py-3 text-right text-gray-700 dark:text-gray-300'>{Number(item.po_unit_price || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className='px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300'>{((Number(item.received_qty) || 0) * (Number(item.po_unit_price) || 0)).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {errors.items && <small className='mt-2 block text-xs text-red-500'>{errors.items}</small>}

                <div className='mt-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300'>
                    Total Qty: {totalQty.toLocaleString('id-ID')} | Total Value: {totalValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </div>
            </Card>
        </>
    );
}

CreateFromPO.layout = (page) => <AppLayout children={page} />;
