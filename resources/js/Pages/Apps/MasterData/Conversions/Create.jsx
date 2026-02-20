import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconExchange, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { items, uoms } = usePage().props;
    const { data, setData, post, errors } = useForm({ item_id: '', from_uom_id: '', to_uom_id: '', factor: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.conversions.store'));
    };

    return (
        <>
            <Head title="Tambah Konversi UOM" />
            <Card title="Tambah Konversi UOM" icon={<IconExchange size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className='flex flex-col gap-2 md:col-span-2'>
                        <label className='text-gray-600 text-sm'>Item</label>
                        <select value={data.item_id} onChange={(e) => setData('item_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {items.map((item) => <option value={item.id} key={item.id}>{item.sku} - {item.name}</option>)}
                        </select>
                        {errors.item_id && <small className='text-xs text-red-500'>{errors.item_id}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Dari UOM</label>
                        <select value={data.from_uom_id} onChange={(e) => setData('from_uom_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {uoms.map((uom) => <option value={uom.id} key={uom.id}>{uom.code} - {uom.name}</option>)}
                        </select>
                        {errors.from_uom_id && <small className='text-xs text-red-500'>{errors.from_uom_id}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Ke UOM</label>
                        <select value={data.to_uom_id} onChange={(e) => setData('to_uom_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {uoms.map((uom) => <option value={uom.id} key={uom.id}>{uom.code} - {uom.name}</option>)}
                        </select>
                        {errors.to_uom_id && <small className='text-xs text-red-500'>{errors.to_uom_id}</small>}
                    </div>
                    <Input label="Faktor" type="number" step="0.000001" value={data.factor} onChange={(e) => setData('factor', e.target.value)} errors={errors.factor} />
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
