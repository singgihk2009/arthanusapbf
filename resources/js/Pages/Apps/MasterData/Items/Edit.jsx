import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconBox, IconPencilPlus } from '@tabler/icons-react';
import React, { useState } from 'react';
import RegulatoryProductSearch from '@/Components/RegulatoryProductSearch';

export default function Edit() {
    const { item, categories, uoms, warehouses, minimumStockSetting, primaryRegulatoryProduct } = usePage().props;
    const { data, setData, post, errors, processing } = useForm({
        sku: item.sku,
        name: item.name,
        nie: item.nie ?? '',
        category_id: item.category_id ?? '',
        base_uom_id: item.base_uom_id,
        default_barcode: item.default_barcode ?? '',
        warehouse_id: minimumStockSetting?.warehouse_id ?? '',
        min_stock_base: minimumStockSetting?.min_stock_base ?? '',
        track_expired: item.track_expired,
        is_active: item.is_active,
        pictures: [],
        default_new_picture_index: '',
        regulatory_product_id: '',
        manufacturer_name: item?.manufacturer_name ?? '',
        composition_text: item?.composition_text ?? '',
        packing_text: item?.packing_text ?? '',
        regulatory_class: item?.regulatory_class ?? '',
        default_picture_id: item.pictures?.find((picture) => picture.is_default)?.id ?? '',
        _method: 'PUT',
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
        post(route('apps.master-data.items.update', item.id));
    };

    const mapRegulatory = () => {
        if (!data.regulatory_product_id) return;
        post(route('apps.master-data.regulatory-products.mapping.attach'), { data: { item_id: item.id, regulatory_product_id: data.regulatory_product_id }, preserveScroll: true });
    };

    const setPrimary = (regulatoryProductId) => post(route('apps.master-data.regulatory-products.mapping.set-primary'), { data: { item_id: item.id, regulatory_product_id: regulatoryProductId }, preserveScroll: true });
    const detachRegulatory = (regulatoryProductId) => post(route('apps.master-data.regulatory-products.mapping.detach'), { data: { item_id: item.id, regulatory_product_id: regulatoryProductId }, preserveScroll: true });

    return (
        <>
            <Head title="Edit Item" />
            <Card title="Edit Item" icon={<IconBox size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" disabled={processing} label="Update" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="md:col-span-2">
                    <h3 className="mb-2 text-sm font-semibold">Regulatory Reference</h3>
                    <RegulatoryProductSearch selectedProduct={selectedRegulatory} onSelect={handleSelectRegulatory} onClear={clearRegulatory} />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="SKU" type="text" value={data.sku} onChange={(e) => setData('sku', e.target.value)} errors={errors.sku} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <Input label="NIE" type="text" value={data.nie} onChange={(e) => setData('nie', e.target.value)} errors={errors.nie} />
                    <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Category</label><select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{categories.map((category) => <option value={category.id} key={category.id}>{category.hierarchy_name ?? category.name}</option>)}</select>{errors.category_id && <small className='text-xs text-red-500'>{errors.category_id}</small>}</div>
                    <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Base UOM</label><select value={data.base_uom_id} onChange={(e) => setData('base_uom_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{uoms.map((uom) => <option value={uom.id} key={uom.id}>{uom.code} - {uom.name}</option>)}</select>{errors.base_uom_id && <small className='text-xs text-red-500'>{errors.base_uom_id}</small>}</div>
                    <Input label="Default Barcode" type="text" value={data.default_barcode} onChange={(e) => setData('default_barcode', e.target.value)} errors={errors.default_barcode} />
                    <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Gudang Minimum Stok</label><select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}</select>{errors.warehouse_id && <small className='text-xs text-red-500'>{errors.warehouse_id}</small>}</div>
                    <Input label="Minimum Stok" type="number" min="0" step="0.000001" value={data.min_stock_base} onChange={(e) => setData('min_stock_base', e.target.value)} errors={errors.min_stock_base} className="md:col-span-2" />
                </div>

                <div className="mt-6 md:col-span-2">
                    <div className="mb-2 flex items-center justify-between">
                        <h3 className="text-sm font-semibold">Linked Regulatory Products</h3>
                        <Button type="button" label="Add regulatory link" onClick={mapRegulatory} />
                    </div>
                    <div className="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-800">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    {['Source', 'NIE/Kode', 'Nama Regulatory', 'Produsen', 'Sediaan', 'Kekuatan', 'Komoditi', 'Primary', 'Action'].map((header) => <th key={header} className="px-3 py-2 text-left">{header}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {(item.regulatory_products ?? item.regulatoryProducts ?? []).map((product) => (
                                    <tr key={product.id} className="border-t border-gray-100 dark:border-gray-800">
                                        <td className="px-3 py-2">{product.pivot?.source_name ?? product.source?.source_name ?? '-'}</td>
                                        <td className="px-3 py-2">{product.pivot?.source_code ?? product.source_code ?? product.nie ?? '-'}</td>
                                        <td className="px-3 py-2">{product.product_name_source ?? '-'}</td>
                                        <td className="px-3 py-2">{product.industry_name ?? '-'}</td>
                                        <td className="px-3 py-2">{product.dosage_form ?? '-'}</td>
                                        <td className="px-3 py-2">{product.strength ?? '-'}</td>
                                        <td className="px-3 py-2">{product.commodity_type ?? '-'}</td>
                                        <td className="px-3 py-2">{product.pivot?.is_primary ? 'Yes' : 'No'}</td>
                                        <td className="px-3 py-2">
                                            <div className="flex gap-2">
                                                <Button type="button" label="Set as primary" onClick={() => setPrimary(product.id)} />
                                                <Button type="button" label="Remove" onClick={() => detachRegulatory(product.id)} variant="red" />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4 flex gap-6"><label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.track_expired} onChange={(e) => setData('track_expired', e.target.checked)} /> Track Expired</label><label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif</label></div>

            </Card>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
