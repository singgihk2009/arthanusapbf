import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconPencilPlus, IconRulerMeasure } from '@tabler/icons-react';
import React from 'react';

export default function Edit() {
    const { uom } = usePage().props;
    const { data, setData, post, errors } = useForm({ code: uom.code, name: uom.name, _method: 'PUT' });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.uoms.update', uom.id));
    };

    return (
        <>
            <Head title="Ubah UOM" />
            <Card title="Ubah UOM" icon={<IconRulerMeasure size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="Kode" type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                </div>
            </Card>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
