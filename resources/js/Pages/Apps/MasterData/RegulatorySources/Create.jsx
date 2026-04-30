import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm } from '@inertiajs/react';
import { IconPencilPlus, IconRosetteDiscountCheck } from '@tabler/icons-react';
import React from 'react';

export default function Create() {
    const { data, setData, post, errors } = useForm({ source_name: '' });
    const submit = (e) => { e.preventDefault(); post(route('apps.master-data.regulatory-sources.store')); };

    return (<><Head title="Tambah Regulatory Source" /><Card title="Tambah Regulatory Source" icon={<IconRosetteDiscountCheck size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}><div className="grid grid-cols-1 md:grid-cols-2 gap-4"><Input label="Nama Source" type="text" value={data.source_name} onChange={(e) => setData('source_name', e.target.value)} errors={errors.source_name} /></div></Card></>);
}
Create.layout = (page) => <AppLayout children={page} />;
