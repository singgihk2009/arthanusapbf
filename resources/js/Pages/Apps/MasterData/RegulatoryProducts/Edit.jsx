import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconPencilPlus, IconRosetteDiscountCheck } from '@tabler/icons-react';
import React from 'react';

export default function Edit() {
    const { product, sources } = usePage().props;
    const { data, setData, post, errors } = useForm({
        product_type: product.product_type ?? 'DRUG',
        source_id: product.source_id ?? '',
        nie: product.nie ?? '',
        source_code: product.source_code ?? '',
        product_name_source: product.product_name_source ?? '',
        dosage_form: product.dosage_form ?? '',
        strength: product.strength ?? '',
        industry_name: product.industry_name ?? '',
        commodity_type: product.commodity_type ?? '',
        raw_composition_text: product.raw_composition_text ?? '',
        raw_packaging_text: product.raw_packaging_text ?? '',
        license_type: product.license_type ?? '',
        registration_date: product.registration_date ?? '',
        expiry_date: product.expiry_date ?? '',
        brand: product.brand ?? '',
        sub_category: product.sub_category ?? '',
        device_type: product.device_type ?? '',
        product_group: product.product_group ?? '',
        model_type: product.model_type ?? '',
        device_class: product.device_class ?? '',
        risk_class: product.risk_class ?? '',
        registrant_name: product.registrant_name ?? '',
        registrant_address: product.registrant_address ?? '',
        manufacturer_name: product.manufacturer_name ?? '',
        manufacturer_address: product.manufacturer_address ?? '',
        manufacturer_name_2: product.manufacturer_name_2 ?? '',
        _method: 'PUT',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.regulatory-products.update', product.id));
    };

    return (
        <>
            <Head title="Ubah Regulatory Product" />
            <Card title="Ubah Regulatory Product" icon={<IconRosetteDiscountCheck size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
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
                    <Input label="Kode BPOM" type="text" value={data.source_code} onChange={(e) => setData('source_code', e.target.value)} errors={errors.source_code} />
                    {data.product_type === 'DRUG' ? <>
                        <Input label="Nama Produk (Source)" type="text" value={data.product_name_source} onChange={(e) => setData('product_name_source', e.target.value)} errors={errors.product_name_source} />
                        <Input label="Dosage Form" type="text" value={data.dosage_form} onChange={(e) => setData('dosage_form', e.target.value)} errors={errors.dosage_form} />
                        <Input label="Strength" type="text" value={data.strength} onChange={(e) => setData('strength', e.target.value)} errors={errors.strength} />
                        <Input label="Industry Name" type="text" value={data.industry_name} onChange={(e) => setData('industry_name', e.target.value)} errors={errors.industry_name} />
                        <Input label="Commodity Type" type="text" value={data.commodity_type} onChange={(e) => setData('commodity_type', e.target.value)} errors={errors.commodity_type} />
                        <Input label="Raw Composition Text" type="text" value={data.raw_composition_text} onChange={(e) => setData('raw_composition_text', e.target.value)} errors={errors.raw_composition_text} />
                        <Input label="Raw Packaging Text" type="text" value={data.raw_packaging_text} onChange={(e) => setData('raw_packaging_text', e.target.value)} errors={errors.raw_packaging_text} />
                    </> : <>
                        <Input label="MERK" type="text" value={data.brand} onChange={(e) => setData('brand', e.target.value)} errors={errors.brand} />
                        <Input label="AKD/AKL" type="text" value={data.license_type} onChange={(e) => setData('license_type', e.target.value)} errors={errors.license_type} />
                        <Input label="TGL TERBIT" type="date" value={data.registration_date} onChange={(e) => setData('registration_date', e.target.value)} errors={errors.registration_date} />
                        <Input label="TGL EXP" type="date" value={data.expiry_date} onChange={(e) => setData('expiry_date', e.target.value)} errors={errors.expiry_date} />
                        <Input label="SUB KATEGORI" type="text" value={data.sub_category} onChange={(e) => setData('sub_category', e.target.value)} errors={errors.sub_category} />
                        <Input label="JENIS PRODUK" type="text" value={data.device_type} onChange={(e) => setData('device_type', e.target.value)} errors={errors.device_type} />
                        <Input label="KELOMPOK PRODUK" type="text" value={data.product_group} onChange={(e) => setData('product_group', e.target.value)} errors={errors.product_group} />
                        <Input label="TIPE" type="text" value={data.model_type} onChange={(e) => setData('model_type', e.target.value)} errors={errors.model_type} />
                        <Input label="KELAS" type="text" value={data.device_class} onChange={(e) => setData('device_class', e.target.value)} errors={errors.device_class} />
                        <Input label="KELAS RISIKO" type="text" value={data.risk_class} onChange={(e) => setData('risk_class', e.target.value)} errors={errors.risk_class} />
                        <Input label="PENDAFTAR" type="text" value={data.registrant_name} onChange={(e) => setData('registrant_name', e.target.value)} errors={errors.registrant_name} />
                        <Input label="ALAMAT PENDAFTAR" type="text" value={data.registrant_address} onChange={(e) => setData('registrant_address', e.target.value)} errors={errors.registrant_address} />
                        <Input label="PABRIK" type="text" value={data.manufacturer_name} onChange={(e) => setData('manufacturer_name', e.target.value)} errors={errors.manufacturer_name} />
                        <Input label="ALAMAT PABRIK" type="text" value={data.manufacturer_address} onChange={(e) => setData('manufacturer_address', e.target.value)} errors={errors.manufacturer_address} />
                        <Input label="PABRIK2" type="text" value={data.manufacturer_name_2} onChange={(e) => setData('manufacturer_name_2', e.target.value)} errors={errors.manufacturer_name_2} />
                    </>}
                </div>
            </Card>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
