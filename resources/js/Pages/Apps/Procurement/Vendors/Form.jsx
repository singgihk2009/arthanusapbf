import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import toast from 'react-hot-toast';

export default function Form() {
  const { vendor, partyTypes = [] } = usePage().props;
  const isEdit = Boolean(vendor);
  const initialData = {
    vendor_code: vendor?.vendor_code ?? '',
    vendor_name: vendor?.vendor_name ?? '',
    vendor_type: vendor?.vendor_type ?? '',
    id_kemenkes: vendor?.id_kemenkes ?? '',
    address: vendor?.address ?? '',
    province: vendor?.province ?? '',
    status: vendor?.status ?? 'prospect',
  };

  const { data, setData, post, put, errors, processing, reset } = useForm(initialData);

  const submit = (e) => {
    e.preventDefault();

    const submitAction = isEdit ? put : post;

    submitAction(isEdit ? `/apps/procurement/vendors/${vendor.id}` : '/apps/procurement/vendors', {
      onSuccess: () => {
        toast.success('Data vendor berhasil disimpan.');
      },
      onError: () => {
        toast.error('Gagal menyimpan data vendor. Periksa kembali data yang diisi.');
      },
    });
  };

  const closeForm = () => router.get('/apps/procurement/vendors');

  return (
    <>
      <Head title='Master Vendor' />
      <Card
        title='Master Vendor'
        form={submit}
        footer={(
          <div className='flex flex-wrap items-center gap-2'>
            <Button type='submit' label='Simpan' variant='gray' disabled={processing} />
            <Button type='button' label='Cancel' variant='orange' onClick={() => reset()} disabled={processing} />
            <Button type='button' label='Close' variant='roseBlack' onClick={closeForm} disabled={processing} />
          </div>
        )}
      >
        <div className='grid grid-cols-1 gap-3 md:grid-cols-2'>
          <Input label='Kode' value={data.vendor_code} onChange={e => setData('vendor_code', e.target.value)} errors={errors.vendor_code} readOnly={!isEdit} placeholder={!isEdit ? 'Auto generate saat simpan' : ''} />
          <Input label='Nama' value={data.vendor_name} onChange={e => setData('vendor_name', e.target.value)} errors={errors.vendor_name} />
          <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Type Vendor</label><select value={data.vendor_type} onChange={e => setData('vendor_type', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none focus:ring-0 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800'><option value=''>Pilih Type Vendor</option>{partyTypes.map((type) => <option key={type.code} value={type.code}>{type.name} ({type.prefix}-001)</option>)}</select>{errors.vendor_type && <small className='text-xs text-red-500'>{errors.vendor_type}</small>}</div>
          <Input label='ID Kemenkes' value={data.id_kemenkes} onChange={e => setData('id_kemenkes', e.target.value)} errors={errors.id_kemenkes} />
          <Input label='Provinsi' value={data.province} onChange={e => setData('province', e.target.value)} errors={errors.province} />
          <Input label='Alamat' value={data.address} onChange={e => setData('address', e.target.value)} errors={errors.address} />
        </div>
      </Card>
    </>
  );
}
Form.layout = (page) => <AppLayout children={page} />;
