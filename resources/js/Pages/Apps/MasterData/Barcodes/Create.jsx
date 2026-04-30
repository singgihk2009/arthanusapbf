import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconFileBarcode, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { items } = usePage().props;
    const { data, setData, post, errors } = useForm({ item_id: '', barcode: '', note: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.barcodes.store'));
    };

    return (
        <>
            <Head title="Tambah Barcode" />
            <Card title="Tambah Barcode" icon={<IconFileBarcode size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className='flex flex-col gap-2 md:col-span-2'>
                        <label className='text-gray-600 text-sm'>Item</label>
                        <select value={data.item_id} onChange={(e) => setData('item_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {items.map((item) => <option value={item.id} key={item.id}>{item.sku} - {item.name}</option>)}
                        </select>
                        {errors.item_id && <small className='text-xs text-red-500'>{errors.item_id}</small>}
                    </div>
                    <Input label="Barcode" type="text" value={data.barcode} onChange={(e) => setData('barcode', e.target.value)} errors={errors.barcode} />
                    <Input label="Catatan" type="text" value={data.note} onChange={(e) => setData('note', e.target.value)} errors={errors.note} />
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
