import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Input from '@/Components/Input';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconBox, IconPencilPlus } from '@tabler/icons-react';
import React from 'react';

export default function Edit() {
    const { item, categories, uoms, warehouses, minimumStockSetting } = usePage().props;
    const { data, setData, post, errors } = useForm({
        sku: item.sku,
        name: item.name,
        category_id: item.category_id ?? '',
        base_uom_id: item.base_uom_id,
        default_barcode: item.default_barcode ?? '',
        warehouse_id: minimumStockSetting?.warehouse_id ?? '',
        min_stock_base: minimumStockSetting?.min_stock_base ?? '',
        track_expired: item.track_expired,
        is_active: item.is_active,
        _method: 'PUT',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.master-data.items.update', item.id));
    };

    return (
        <>
            <Head title="Ubah Item" />
            <Card title="Ubah Item" icon={<IconBox size={20} strokeWidth={1.5} />} form={submit} footer={<Button type="submit" label="Simpan" icon={<IconPencilPlus size={20} strokeWidth={1.5} />} variant="gray" />}>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input label="SKU" type="text" value={data.sku} onChange={(e) => setData('sku', e.target.value)} errors={errors.sku} />
                    <Input label="Nama" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Category</label>
                        <select value={data.category_id ?? ''} onChange={(e) => setData('category_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
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
                    <Input label="Default Barcode" type="text" value={data.default_barcode} onChange={(e) => setData('default_barcode', e.target.value)} errors={errors.default_barcode} />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Gudang Minimum Stok</label>
                        <select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800'>
                            <option value=''>-</option>
                            {warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                        </select>
                        {errors.warehouse_id && <small className='text-xs text-red-500'>{errors.warehouse_id}</small>}
                    </div>
                    <Input label="Minimum Stok" type="number" min="0" step="0.000001" value={data.min_stock_base} onChange={(e) => setData('min_stock_base', e.target.value)} errors={errors.min_stock_base} className="md:col-span-2" />
                </div>
                <div className="mt-4 flex gap-6">
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.track_expired} onChange={(e) => setData('track_expired', e.target.checked)} /> Track Expired</label>
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Aktif</label>
                </div>
            </Card>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
