import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconPencilPlus, IconRosetteDiscountCheck } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { sources } = usePage().props;
    const { data, setData, post, errors } = useForm({ source_id: '', nie: '', product_name_source: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.regulatory-products.store'));
    };

    return (
        <>
            <Head title="Tambah Regulatory Product" />
            <Card title="Tambah Regulatory Product" icon={<IconRosetteDiscountCheck size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Regulatory Source</label>
                        <select value={data.source_id} onChange={(e) => setData('source_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>Pilih source</option>
                            {sources.map((source) => <option value={source.id} key={source.id}>{source.source_name}</option>)}
                        </select>
                        {errors.source_id && <small className='text-xs text-red-500'>{errors.source_id}</small>}
                    </div>
                    <Input label="NIE" type="text" value={data.nie} onChange={(e) => setData('nie', e.target.value)} errors={errors.nie} />
                    <Input label="Nama Produk (Source)" type="text" value={data.product_name_source} onChange={(e) => setData('product_name_source', e.target.value)} errors={errors.product_name_source} />
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
