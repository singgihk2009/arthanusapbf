import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCategory, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Edit() {
    const { category, parents } = usePage().props;
    const { data, setData, post, errors } = useForm({ name: category.name, parent_id: category.parent_id ?? '', _method: 'PUT' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.categories.update', category.id));
    };

    return (
        <>
            <Head title="Ubah Category" />
            <Card title="Ubah Category" icon={<IconCategory size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Parent Category</label>
                        <select value={data.parent_id ?? ''} onChange={(e) => setData('parent_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {parents.map((parent) => <option value={parent.id} key={parent.id}>{parent.name}</option>)}
                        </select>
                        {errors.parent_id && <small className='text-xs text-red-500'>{errors.parent_id}</small>}
                    </div>
                </div>
            </Card>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
