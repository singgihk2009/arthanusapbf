import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconPencilPlus, IconUsers } from '@tabler/icons-react';
import React from 'react';

export default function Form() {
    const { vendor } = usePage().props;
    const isEdit = Boolean(vendor);

    const { data, setData, post, errors } = useForm({
        vendor_code: vendor?.vendor_code ?? '',
        name: vendor?.name ?? '',
        email: vendor?.email ?? '',
        phone: vendor?.phone ?? '',
        tax_id: vendor?.tax_id ?? '',
        address: vendor?.address ?? '',
        currency_code: vendor?.currency_code ?? 'IDR',
        status: vendor?.status ?? 'ACTIVE',
        _method: isEdit ? 'PUT' : 'POST',
    });

    const submit = (e) => {
        e.preventDefault();
        post(isEdit ? route('apps.procurement.vendors.update', vendor.id) : route('apps.procurement.vendors.store'));
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Vendor' : 'Tambah Vendor'} />
            <Card title={isEdit ? 'Ubah Vendor' : 'Tambah Vendor'} icon={<IconUsers size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="Kode Vendor" type="text" value={data.vendor_code} onChange={(e) => setData('vendor_code', e.target.value)} errors={errors.vendor_code} />
                    <Input label="Nama Vendor" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <Input label="Email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} errors={errors.email} />
                    <Input label="Telepon" type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} errors={errors.phone} />
                    <Input label="NPWP/Tax ID" type="text" value={data.tax_id} onChange={(e) => setData('tax_id', e.target.value)} errors={errors.tax_id} />
                    <Input label="Currency" type="text" value={data.currency_code} onChange={(e) => setData('currency_code', e.target.value.toUpperCase())} errors={errors.currency_code} />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Status</label>
                        <select value={data.status} onChange={(e) => setData('status', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value='ACTIVE'>ACTIVE</option>
                            <option value='INACTIVE'>INACTIVE</option>
                        </select>
                        {errors.status && <small className='text-xs text-red-500'>{errors.status}</small>}
                    </div>
                    <div className='md:col-span-2 flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Alamat</label>
                        <textarea value={data.address} onChange={(e) => setData('address', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800' rows={4} />
                        {errors.address && <small className='text-xs text-red-500'>{errors.address}</small>}
                    </div>
                </div>
            </Card>
        </>
    );
}

Form.layout = (page) => <AppLayout children={page} />;
