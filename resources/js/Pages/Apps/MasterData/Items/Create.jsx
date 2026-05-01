import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconBox, IconPencilPlus } from '@tabler/icons-react';
import React, { useState } from 'react';
import RegulatoryProductSearch from '@/Components/RegulatoryProductSearch';

export default function Create() {
    const { categories, uoms, warehouses, primaryRegulatoryProduct } = usePage().props;
    const { data, setData, post, errors, processing } = useForm({
        sku: '', name: '', nie: '', category_id: '', base_uom_id: '', default_barcode: '', warehouse_id: '', min_stock_base: '', track_expired: false, is_active: true,
        pictures: [],
        default_new_picture_index: '',
        regulatory_product_id: '',
        manufacturer_name: '',
        composition_text: '',
        packing_text: '',
        regulatory_class: '',
        dosage_form: '',
        strength: '',
    });

    const [selectedRegulatory, setSelectedRegulatory] = useState(primaryRegulatoryProduct ?? null);

    const handleSelectRegulatory = (product) => {
        setSelectedRegulatory(product);
        setData('regulatory_product_id', product.id);
        const combinedName = [product.product_name_source, product.raw_packaging_text].filter(Boolean).join(' - ');
        const mapping = { name: combinedName, nie: product.nie ?? product.source_code, manufacturer_name: product.industry_name, composition_text: product.raw_composition_text, packing_text: product.raw_packaging_text, regulatory_class: product.commodity_type, dosage_form: product.dosage_form, strength: product.strength };
        Object.entries(mapping).forEach(([key, value]) => {
            if (!data[key] && value) setData(key, value);
        });
    };

    const clearRegulatory = () => { setSelectedRegulatory(null); setData('regulatory_product_id', ''); };

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.items.store'));
    };

    return (
        <>
            <Head title="Tambah Item" />
            <Card title="Tambah Item" icon={<IconBox size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" disabled={processing} icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="md:col-span-2">
                    <h3 className="mb-2 text-sm font-semibold">Regulatory Reference</h3>
                    <RegulatoryProductSearch selectedProduct={selectedRegulatory} onSelect={handleSelectRegulatory} onClear={clearRegulatory} />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="SKU" type="text" value={data.sku} onChange={(e) => setData('sku', e.target.value)} errors={errors.sku} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <Input label="NIE" type="text" value={data.nie} onChange={(e) => setData('nie', e.target.value)} errors={errors.nie} />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Category</label>
                        <select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {categories.map((category) => <option value={category.id} key={category.id}>{category.name}</option>)}
                        </select>
                        {errors.category_id && <small className='text-xs text-red-500'>{errors.category_id}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Base UOM</label>
                        <select value={data.base_uom_id} onChange={(e) => setData('base_uom_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {uoms.map((uom) => <option value={uom.id} key={uom.id}>{uom.code} - {uom.name}</option>)}
                        </select>
                        {errors.base_uom_id && <small className='text-xs text-red-500'>{errors.base_uom_id}</small>}
                    </div>
                    <Input label="Default Barcode (scan di sini)" type="text" value={data.default_barcode} onChange={(e) => setData('default_barcode', e.target.value)} errors={errors.default_barcode} autoFocus />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Gudang Minimum Stok</label>
                        <select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                        </select>
                        {errors.warehouse_id && <small className='text-xs text-red-500'>{errors.warehouse_id}</small>}
                    </div>
                    <Input label="Minimum Stok" type="number" min="0" step="0.000001" value={data.min_stock_base} onChange={(e) => setData('min_stock_base', e.target.value)} errors={errors.min_stock_base} className="md:col-span-2" />
                    <div className="md:col-span-2 flex flex-col gap-2">
                        <label className="text-gray-600 text-sm">Foto Produk (max 6 foto)</label>
                        <input type="file" multiple accept="image/*" onChange={(e) => setData('pictures', Array.from(e.target.files).slice(0, 6))} className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" />
                        {errors.pictures && <small className='text-xs text-red-500'>{errors.pictures}</small>}
                        <select value={data.default_new_picture_index} onChange={(e) => setData('default_new_picture_index', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>Default foto: otomatis foto pertama</option>
                            {data.pictures.map((_, index) => <option key={index} value={index}>Foto upload #{index + 1} jadi default</option>)}
                        </select>
                    </div>
                </div>
                <div className="mt-4 flex gap-6">
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.track_expired} onChange={(e) => setData('track_expired', e.target.checked)} /> Track Expired</label>
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif</label>
                </div>
            </Card>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
