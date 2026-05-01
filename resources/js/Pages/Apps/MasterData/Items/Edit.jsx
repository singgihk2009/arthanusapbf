import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconBox, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Edit() {
    const { item, categories, uoms, warehouses, minimumStockSetting, regulatoryProducts } = usePage().props;
    const { data, setData, post, errors, processing } = useForm({
        sku: item.sku,
        name: item.name,
        category_id: item.category_id ?? '',
        base_uom_id: item.base_uom_id,
        default_barcode: item.default_barcode ?? '',
        warehouse_id: minimumStockSetting?.warehouse_id ?? '',
        min_stock_base: minimumStockSetting?.min_stock_base ?? '',
        track_expired: item.track_expired,
        is_active: item.is_active,
        pictures: [],
        default_new_picture_index: '',
        default_picture_id: item.pictures?.find((picture) => picture.is_default)?.id ?? '',
        regulatory_product_id: '',
        regulatory_is_primary: false,
        _method: 'PUT',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.items.update', item.id));
    };

    const mapRegulatory = () => {
        if (!data.regulatory_product_id) return;
        post(route('apps.master-data.regulatory-products.mapping.attach'), { data: { item_id: item.id, regulatory_product_id: data.regulatory_product_id }, preserveScroll: true });
        if (data.regulatory_is_primary) {
            post(route('apps.master-data.regulatory-products.mapping.set-primary'), { data: { item_id: item.id, regulatory_product_id: data.regulatory_product_id }, preserveScroll: true });
        }
    };

    const setPrimary = (regulatoryProductId) => post(route('apps.master-data.regulatory-products.mapping.set-primary'), { data: { item_id: item.id, regulatory_product_id: regulatoryProductId }, preserveScroll: true });
    const detachRegulatory = (regulatoryProductId) => post(route('apps.master-data.regulatory-products.mapping.detach'), { data: { item_id: item.id, regulatory_product_id: regulatoryProductId }, preserveScroll: true });

    return (
        <>
            <Head title="Edit Item" />
            <Card title="Edit Item" icon={<IconBox size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" disabled={processing} label="Update" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="SKU" type="text" value={data.sku} onChange={(e) => setData('sku', e.target.value)} errors={errors.sku} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Category</label><select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{categories.map((category) => <option value={category.id} key={category.id}>{category.name}</option>)}</select>{errors.category_id && <small className='text-xs text-red-500'>{errors.category_id}</small>}</div>
                    <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Base UOM</label><select value={data.base_uom_id} onChange={(e) => setData('base_uom_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{uoms.map((uom) => <option value={uom.id} key={uom.id}>{uom.code} - {uom.name}</option>)}</select>{errors.base_uom_id && <small className='text-xs text-red-500'>{errors.base_uom_id}</small>}</div>
                    <Input label="Default Barcode" type="text" value={data.default_barcode} onChange={(e) => setData('default_barcode', e.target.value)} errors={errors.default_barcode} />
                    <div className='flex flex-col gap-2'><label className='text-gray-600 text-sm'>Gudang Minimum Stok</label><select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}</select>{errors.warehouse_id && <small className='text-xs text-red-500'>{errors.warehouse_id}</small>}</div>
                    <Input label="Minimum Stok" type="number" min="0" step="0.000001" value={data.min_stock_base} onChange={(e) => setData('min_stock_base', e.target.value)} errors={errors.min_stock_base} className="md:col-span-2" />
                </div>

                <div className="mt-4 flex gap-6"><label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.track_expired} onChange={(e) => setData('track_expired', e.target.checked)} /> Track Expired</label><label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif</label></div>

                <div className="mt-8 border-t pt-6">
                    <h3 className="text-base font-semibold mb-3">Mapping Regulatory Product</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div className='flex flex-col gap-2 md:col-span-2'>
                            <label className='text-gray-600 text-sm'>Pilih Regulatory Product</label>
                            <select value={data.regulatory_product_id} onChange={(e) => setData('regulatory_product_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'><option value=''>-</option>{regulatoryProducts.map((product) => <option value={product.id} key={product.id}>{product.nie} - {product.product_name_source}</option>)}</select>
                        </div>
                        <Button type="button" onClick={mapRegulatory} label="Hubungkan" variant="gray" />
                    </div>
                    <label className="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.regulatory_is_primary} onChange={(e) => setData('regulatory_is_primary', e.target.checked)} /> Set sebagai primary</label>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead><tr><th className="px-3 py-2 text-left">NIE</th><th className="px-3 py-2 text-left">Nama Produk</th><th className="px-3 py-2 text-left">Primary</th><th className="px-3 py-2 text-left">Aksi</th></tr></thead>
                            <tbody>
                                {item.regulatory_products?.length ? item.regulatory_products.map((product) => (<tr key={product.id}><td className="px-3 py-2">{product.nie}</td><td className="px-3 py-2">{product.product_name_source}</td><td className="px-3 py-2">{product.pivot?.is_primary ? 'Ya' : 'Tidak'}</td><td className="px-3 py-2 flex gap-2">{!product.pivot?.is_primary && <Button type="button" onClick={() => setPrimary(product.id)} label="Set Primary" variant="orange" />}<Button type="button" onClick={() => detachRegulatory(product.id)} label="Lepas" variant="rose" /></td></tr>)) : <tr><td colSpan={4} className="px-3 py-4 text-center text-gray-500">Belum ada regulatory product terhubung.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </Card>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
