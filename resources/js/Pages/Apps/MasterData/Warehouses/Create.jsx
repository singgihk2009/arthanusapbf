import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm } from '@inertiajs/react';
import { IconBuildingWarehouse, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { data, setData, post, errors } = useForm({ code: '', name: '', address: '', is_active: true });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.warehouses.store'));
    };

    return (
        <>
            <Head title="Tambah Warehouse" />
            <Card title="Tambah Warehouse" icon={<IconBuildingWarehouse size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="Kode" type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <Input label="Alamat" type="text" value={data.address} onChange={(e) => setData('address', e.target.value)} errors={errors.address} className="md:col-span-2" />
                </div>
                <div className="mt-4">
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Aktif
                    </label>
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
