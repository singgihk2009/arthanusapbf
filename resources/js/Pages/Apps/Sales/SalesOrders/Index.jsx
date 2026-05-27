import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, Link, router } from '@inertiajs/react';
import { IconDatabaseOff, IconRefresh } from '@tabler/icons-react';
import React from 'react';

const defaultFilters = {
    search: '',
    status: '',
};

const APPROVAL_STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

export default function Page({ salesOrders, filters = {} }) {
    const [form, setForm] = React.useState({ ...defaultFilters, ...filters });
    const [selectedIds, setSelectedIds] = React.useState([]);
    const rows = salesOrders?.data || [];

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('apps.sales-orders.index'), {
            ...(form.search ? { search: form.search } : {}),
            ...(form.status ? { status: form.status } : {}),
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilter = () => {
        setForm(defaultFilters);
        setSelectedIds([]);
        router.get(route('apps.sales-orders.index'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const toggleSelection = (id) => {
        setSelectedIds((previous) => previous.includes(id)
            ? previous.filter((item) => item !== id)
            : [...previous, id]);
    };

    const toggleSelectAllApprovable = () => {
        const approvableIds = rows.filter((so) => {
            const status = String(so.status || '').toLowerCase();
            if (status === 'submitted') return true;
            if (status === 'draft') return Number(so.lines_count || 0) > 0;
            return false;
        }).map((so) => so.id);
        if (!approvableIds.length) return;
        const isAllSelected = approvableIds.every((id) => selectedIds.includes(id));
        setSelectedIds(isAllSelected ? selectedIds.filter((id) => !approvableIds.includes(id)) : Array.from(new Set([...selectedIds, ...approvableIds])));
    };

    const approveSelected = async () => {
        if (!selectedIds.length) return;
        if (!window.confirm(`Approve ${selectedIds.length} sales order terpilih?`)) return;
        const selectedOrders = rows.filter((so) => selectedIds.includes(so.id));
        const failedOrders = [];
        for (const salesOrder of selectedOrders) {
            try {
                const status = String(salesOrder.status || '').toLowerCase();
                await window.axios.post(route('apps.sales-orders.approve', salesOrder.id));
            } catch (error) {
                const serverError = error?.response?.data?.errors ?? {};
                const firstError = Object.values(serverError).flat().find(Boolean) || error?.response?.data?.message || 'Unknown error';
                failedOrders.push(`${salesOrder.number || salesOrder.id}: ${firstError}`);
            }
        }
        if (failedOrders.length) window.alert(`Sebagian approval gagal:\n${failedOrders.join('\n')}`);
        setSelectedIds([]);
        router.reload({ only: ['salesOrders'] });
    };

    const approvableIds = rows.filter((so) => {
        const status = String(so.status || '').toLowerCase();
        if (status === 'submitted') return true;
        if (status === 'draft') return Number(so.lines_count || 0) > 0;
        return false;
    }).map((so) => so.id);
    const allApprovableSelected = approvableIds.length > 0 && approvableIds.every((id) => selectedIds.includes(id));

    return <>
        <Head title='Sales Orders' />

        <form onSubmit={submitSearch} className='mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12'>
            <div className='md:col-span-8'>
                <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Global Search</label>
                <input
                    value={form.search}
                    onChange={(e) => setForm((previous) => ({ ...previous, search: e.target.value }))}
                    placeholder='Cari nomor sales order / nama customer'
                    className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'
                />
            </div>
            <div className='md:col-span-2'>
                <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Status Approval</label>
                <select
                    value={form.status}
                    onChange={(e) => setForm((previous) => ({ ...previous, status: e.target.value }))}
                    className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'
                >
                    <option value=''>All Status</option>
                    <option value='draft'>Draft</option>
                    <option value='submitted'>Submitted</option>
                    <option value='approved'>Approved</option>
                    <option value='cancelled'>Cancelled</option>
                </select>
            </div>
            <div className='flex items-end gap-2 md:col-span-2'>
                <button type='submit' className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30'>Terapkan</button>
                <button type='button' onClick={resetFilter} className='inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'><IconRefresh size={16} strokeWidth={1.5} />Refresh Filter</button>
            </div>
        </form>

        <Table.Card title='Data Sales Order'>
            <div className='mb-3 flex justify-end'>
                <button
                    type='button'
                    onClick={approveSelected}
                    disabled={!selectedIds.length}
                    className='rounded-lg border border-blue-500 px-3 py-2 text-sm font-medium text-blue-600 enabled:hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50'
                >
                    Approve Selected ({selectedIds.length})
                </button>
            </div>
            <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th className='w-10 text-center'>
                            <input type='checkbox' checked={allApprovableSelected} onChange={toggleSelectAllApprovable} disabled={!approvableIds.length} />
                        </Table.Th>
                        <Table.Th>No</Table.Th>
                        <Table.Th>SO Number</Table.Th>
                        <Table.Th>Customer</Table.Th>
                        <Table.Th>Status Approval</Table.Th>
                        <Table.Th>Grand Total</Table.Th>
                        <Table.Th>Actions</Table.Th>
                    </tr>
                </Table.Thead>
                <Table.Tbody>
                    {rows.length ? rows.map((so, i) => {
                        const status = String(so.status || '').toLowerCase();
                        const selectable = status === 'submitted' || (status === 'draft' && Number(so.lines_count || 0) > 0);

                        return <tr key={so.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                            <Table.Td className='text-center'>{selectable ? <input type='checkbox' checked={selectedIds.includes(so.id)} onChange={() => toggleSelection(so.id)} /> : '-'}</Table.Td>
                            <Table.Td className='text-center'>{++i + (salesOrders.current_page - 1) * salesOrders.per_page}</Table.Td>
                            <Table.Td>{so.number}</Table.Td>
                            <Table.Td>{so.customer?.customer_name || '-'}</Table.Td>
                            <Table.Td>
                                <span className={`rounded px-2 py-1 text-xs ${APPROVAL_STATUS_STYLES[status] || APPROVAL_STATUS_STYLES.draft}`}>
                                    {so.status || '-'}
                                </span>
                            </Table.Td>
                            <Table.Td>{Number(so.grand_total || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                            <Table.Td>
                                <Link href={route('apps.sales-orders.show', so.id)} className='inline-flex items-center rounded-md border border-indigo-500 px-3 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50'>Detail</Link>
                            </Table.Td>
                        </tr>;
                    }) : <Table.Empty colSpan={7} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white' /><span className='text-gray-500'>Data sales order tidak ditemukan.</span></>} />}
                </Table.Tbody>
            </Table>
        </Table.Card>

        {salesOrders.last_page !== 1 && <Pagination links={salesOrders.links} />}
    </>;
}

Page.layout = (page) => <AppLayout children={page} />;
