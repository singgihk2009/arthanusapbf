import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';

export default function Form() {
  const { vendor } = usePage().props;
  const isEdit = Boolean(vendor);
  const { data, setData, post, errors } = useForm({
    vendor_code: vendor?.vendor_code ?? '', vendor_name: vendor?.vendor_name ?? '', vendor_type: vendor?.vendor_type ?? '',
    address: vendor?.address ?? '', postal_code: vendor?.postal_code ?? '', village: vendor?.village ?? '', district: vendor?.district ?? '', city: vendor?.city ?? '', province: vendor?.province ?? '',
    phone: vendor?.phone ?? '', fax: vendor?.fax ?? '', email: vendor?.email ?? '', npwp: vendor?.npwp ?? '', is_pkp: !!vendor?.is_pkp, status: vendor?.status ?? 'prospect',
    nib_number: vendor?.nib_number ?? '', company_license_number: vendor?.company_license_number ?? '', cdakb_cpakb_certificate_number: vendor?.cdakb_cpakb_certificate_number ?? '',
    company_director: { name: vendor?.company_director?.name ?? '', address: vendor?.company_director?.address ?? '', phone: vendor?.company_director?.phone ?? '', email: vendor?.company_director?.email ?? '' },
    technical_responsible_person: { name: vendor?.technical_responsible_person?.name ?? '', address: vendor?.technical_responsible_person?.address ?? '', license_number: vendor?.technical_responsible_person?.license_number ?? '', email: vendor?.technical_responsible_person?.email ?? '', phone: vendor?.technical_responsible_person?.phone ?? '' },
    _method: isEdit ? 'PUT' : 'POST',
  });
  const submit = (e)=>{e.preventDefault(); post(isEdit ? route('apps.procurement.vendors.update', vendor.id) : route('apps.procurement.vendors.store'));};
  return <><Head title='Master Vendor'/><Card title='Master Vendor' form={submit} footer={<Button type='submit' label='Simpan' variant='gray'/>}><div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
    <Input label='Kode' value={data.vendor_code} onChange={e=>setData('vendor_code', e.target.value)} errors={errors.vendor_code}/><Input label='Nama' value={data.vendor_name} onChange={e=>setData('vendor_name', e.target.value)} errors={errors.vendor_name}/>
    <Input label='Tipe Vendor' value={data.vendor_type} onChange={e=>setData('vendor_type', e.target.value)} errors={errors.vendor_type}/><Input label='NIB' value={data.nib_number} onChange={e=>setData('nib_number', e.target.value)} errors={errors.nib_number}/>
    <Input label='Izin Perusahaan' value={data.company_license_number} onChange={e=>setData('company_license_number', e.target.value)} errors={errors.company_license_number}/><Input label='CDAKB/CPAKB' value={data.cdakb_cpakb_certificate_number} onChange={e=>setData('cdakb_cpakb_certificate_number', e.target.value)} errors={errors.cdakb_cpakb_certificate_number}/>
    <Input label='Kota' value={data.city} onChange={e=>setData('city', e.target.value)} errors={errors.city}/><Input label='Telepon' value={data.phone} onChange={e=>setData('phone', e.target.value)} errors={errors.phone}/>
  </div></Card></>;
}
Form.layout = (page) => <AppLayout children={page} />;
