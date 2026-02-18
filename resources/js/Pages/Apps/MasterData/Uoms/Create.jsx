import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm } from '@inertiajs/react';
import { IconPencilPlus, IconRulerMeasure } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { data, setData, post, errors } = useForm({ code: '', name: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.uoms.store'));
    };

    return (
        <>
            <Head title="Tambah UOM" />
            <Card title="Tambah UOM" icon={<IconRulerMeasure size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="Kode" type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
