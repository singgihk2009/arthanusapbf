import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, Link, router } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconRefresh } from '@tabler/icons-react';
import { useState } from 'react';

export default function Index({ purchaseOrders, filters = {}, statuses = [] }) {
    const [form, setForm] = useState({ search: filters.search || '', status: filters.status || '' });
    const [selectedDraftIds, setSelectedDraftIds] = useState([]);

    const applyFilter = (e) => {
        e.preventDefault();
        router.get(route('apps.procurement.purchase-orders.index'), form, { preserveState: true, preserveScroll: true });
    };

    const resetFilter = () => {
        setForm({ search: '', status: '' });
        router.get(route('apps.procurement.purchase-orders.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const toggleDraftSelection = (id) => {
        setSelectedDraftIds((prev) => prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]);
    };

    const approveSelectedDrafts = async () => {
        if (!selectedDraftIds.length) return;
        if (!window.confirm(`Approve ${selectedDraftIds.length} PO draft terpilih?`)) return;

        await Promise.all(selectedDraftIds.map((id) => window.axios.post(route('apps.procurement.purchase-orders.approve', id))));
        setSelectedDraftIds([]);
        router.reload({ only: ['purchaseOrders'] });
    };

    const handleDeleteDraft = (id) => {
        if (!window.confirm('Hapus PO draft ini?')) return;
        router.delete(route('apps.procurement.purchase-orders.destroy', id));
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

            <div className='mb-5 flex items-center justify-end gap-2'>
                <button
                    type='button'
                    onClick={approveSelectedDrafts}
                    disabled={!selectedDraftIds.length}
                    className='rounded-lg border border-blue-500 px-3 py-2 text-sm font-medium text-blue-600 enabled:hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50'
                >
                    Approve Selected ({selectedDraftIds.length})
                </button>
                <Button type='link' href={route('apps.procurement.purchase-orders.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Create PO' />
            </div>

            <Table.Card title='Data Purchase Order'>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>Approve</Table.Th><Table.Th>PO Number</Table.Th><Table.Th>Vendor</Table.Th><Table.Th>PO Date</Table.Th><Table.Th>Expected Date</Table.Th><Table.Th>Grand Total</Table.Th><Table.Th>Status</Table.Th><Table.Th>Action</Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {purchaseOrders.data.length ? purchaseOrders.data.map((po) => {
                            const poStatus = String(po.status ?? '').toLowerCase();
                            const isDraft = poStatus === 'draft';

                            return (
                            <tr key={po.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>
                                    {isDraft ? (
                                        <input type='checkbox' checked={selectedDraftIds.includes(po.id)} onChange={() => toggleDraftSelection(po.id)} />
                                    ) : '-'}
                                </Table.Td>
                                <Table.Td>{po.po_number ?? '-'}</Table.Td>
                                <Table.Td>{po.vendor_id ? <Link href={`/apps/procurement/vendors/${po.vendor_id}?tab=overview`} className='text-indigo-600 hover:underline'>{po.vendor?.name ?? '-'}</Link> : (po.vendor?.name ?? '-')}</Table.Td>
                                <Table.Td>{po.po_date ?? '-'}</Table.Td>
                                <Table.Td>{po.expected_delivery_date ?? '-'}</Table.Td>
                                <Table.Td>{Number(po.grand_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</Table.Td>
                                <Table.Td><span className='rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800'>{po.status}</span></Table.Td>
                                <Table.Td>
                                    <div className='flex flex-wrap gap-2'>
                                        <Link className='rounded-lg border border-indigo-500 px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50' href={route('apps.procurement.purchase-orders.show', po.id)}>Detail</Link>
                                        {isDraft && (
                                            <>
                                                <Link className='rounded-lg border border-amber-500 px-2.5 py-1.5 text-xs font-medium text-amber-600 hover:bg-amber-50' href={route('apps.procurement.purchase-orders.edit', po.id)}>Edit</Link>
                                                <button type='button' onClick={() => handleDeleteDraft(po.id)} className='rounded-lg border border-rose-500 px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50'>Delete</button>
                                            </>
                                        )}
                                        {poStatus === 'approved' && (
                                            <Link
                                                className='rounded-lg border border-emerald-500 px-2.5 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50'
                                                href={route('apps.procurement.goods-receipts.create-from-po', po.id)}
                                            >
                                                Create Goods Receiving
                                            </Link>
                                        )}
                                    </div>
                                </Table.Td>
                            </tr>
                            );
                        }) : <Table.Empty colSpan={8} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white' /><span className='text-gray-500'>Data purchase order tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>

            {purchaseOrders.last_page !== 1 && <Pagination links={purchaseOrders.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
