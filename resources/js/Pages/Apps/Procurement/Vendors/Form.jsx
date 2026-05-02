import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import toast from 'react-hot-toast';

export default function Form() {
  const { vendor } = usePage().props;
  const isEdit = Boolean(vendor);
  const initialData = {
    vendor_code: vendor?.vendor_code ?? '',
    vendor_name: vendor?.vendor_name ?? '',
    vendor_type: vendor?.vendor_type ?? '',
    address: vendor?.address ?? '',
    province: vendor?.province ?? '',
    status: vendor?.status ?? 'prospect',
    _method: isEdit ? 'PUT' : 'POST',
  };

  const { data, setData, post, errors, processing, reset } = useForm(initialData);

  const submit = (e) => {
    e.preventDefault();

    post(isEdit ? `/apps/procurement/vendors/${vendor.id}` : '/apps/procurement/vendors', {
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
          <Input label='Kode' value={data.vendor_code} onChange={e => setData('vendor_code', e.target.value)} errors={errors.vendor_code} />
          <Input label='Nama' value={data.vendor_name} onChange={e => setData('vendor_name', e.target.value)} errors={errors.vendor_name} />
          <Input label='Type Vendor' value={data.vendor_type} onChange={e => setData('vendor_type', e.target.value)} errors={errors.vendor_type} />
          <Input label='Provinsi' value={data.province} onChange={e => setData('province', e.target.value)} errors={errors.province} />
          <Input label='Alamat' value={data.address} onChange={e => setData('address', e.target.value)} errors={errors.address} />
        </div>
      </Card>
    </>
  );
}
Form.layout = (page) => <AppLayout children={page} />;
