import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import PurchaseOrderTable from '@/Components/Procurement/PurchaseOrders/PurchaseOrderTable';
import { Head, router } from '@inertiajs/react';
import { IconCirclePlus, IconRefresh } from '@tabler/icons-react';
import { useState } from 'react';

export default function Index({ purchaseOrders, filters = {}, statuses = [] }) {
    const [form, setForm] = useState({ search: filters.search || '', status: filters.status || '' });

    const applyFilter = (e) => {
        e.preventDefault();
        router.get(route('apps.procurement.purchase-orders.index'), form, { preserveState: true, preserveScroll: true });
    };

    const resetFilter = () => {
        setForm({ search: '', status: '' });
        router.get(route('apps.procurement.purchase-orders.index'), {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title='Purchase Orders' />

            <form onSubmit={applyFilter} className='mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12'>
                <div className='md:col-span-5'>
                    <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Search</label>
                    <input value={form.search} onChange={(e) => setForm((p) => ({ ...p, search: e.target.value }))} className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100' placeholder='Cari vendor / nomor PO' />
                </div>
                <div className='md:col-span-3'>
                    <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Status</label>
                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'>
                        <option value=''>Semua status</option>
                        {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                <div className='flex items-end gap-2 md:col-span-4'>
                    <button type='submit' className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30'>Terapkan</button>
                    <button type='button' onClick={resetFilter} className='inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'><IconRefresh size={16} strokeWidth={1.5} />Refresh Filter</button>
                </div>
            </form>

            <Table.Card title='Data Purchase Order'>
                <PurchaseOrderTable
                    purchaseOrders={purchaseOrders}
                    showVendor={true}
                    emptyMessage='Data purchase order tidak ditemukan.'
                    onApproved={() => router.reload({ only: ['purchaseOrders'] })}
                    topActions={<Button type='link' href={route('apps.procurement.purchase-orders.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Create PO' />}
                />
            </Table.Card>

            {purchaseOrders.last_page !== 1 && <Pagination links={purchaseOrders.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
