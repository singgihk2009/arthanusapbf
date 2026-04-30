import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconListCheck, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { items, warehouses } = usePage().props;
    const { data, setData, post, errors } = useForm({ warehouse_id: '', item_id: '', min_stock_base: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.min-stocks.store'));
    };

    return (
        <>
            <Head title="Tambah Min Stock" />
            <Card title="Tambah Min Stock" icon={<IconListCheck size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Gudang</label>
                        <select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                        </select>
                        {errors.warehouse_id && <small className='text-xs text-red-500'>{errors.warehouse_id}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Item</label>
                        <select value={data.item_id} onChange={(e) => setData('item_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {items.map((item) => <option value={item.id} key={item.id}>{item.sku} - {item.name}</option>)}
                        </select>
                        {errors.item_id && <small className='text-xs text-red-500'>{errors.item_id}</small>}
                    </div>
                    <Input label="Min Stock (Base UOM)" type="number" step="0.000001" value={data.min_stock_base} onChange={(e) => setData('min_stock_base', e.target.value)} errors={errors.min_stock_base} />
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
