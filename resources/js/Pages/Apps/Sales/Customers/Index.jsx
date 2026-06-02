import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, Link, router } from '@inertiajs/react';
import { IconDatabaseOff, IconFileImport, IconRefresh, IconTrash } from '@tabler/icons-react';
import React from 'react';

const defaultFilters = {
    search: '',
    status: '',
};

export default function Page({ customers, filters = {} }) {
    const [form, setForm] = React.useState({ ...defaultFilters, ...filters });
    const [importFile, setImportFile] = React.useState(null);
    const [importing, setImporting] = React.useState(false);
    const [importResult, setImportResult] = React.useState(null);

    const onDelete = (id) => {
        if (!window.confirm('Delete this customer?')) return;
        router.delete(route('apps.customers.destroy', id));
    };

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('apps.customers.index'), {
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
        router.get(route('apps.customers.index'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleImport = async () => {
        if (!importFile || importing) return;
        setImporting(true);
        setImportResult(null);
        try {
            const formData = new FormData();
            formData.append('file', importFile);
            await window.axios.post(route('apps.customers.import.excel'), formData, { headers: { 'Content-Type': 'multipart/form-data' } });
            setImportResult({ type: 'success', message: 'Import customer berhasil diproses.' });
            setImportFile(null);
            router.reload({ only: ['customers'] });
        } catch (error) {
            setImportResult({ type: 'error', message: error?.response?.data?.message || 'Import customer gagal diproses.' });
        } finally {
            setImporting(false);
        }
    };

    return <>
        <Head title='Customers' />

        <form onSubmit={submitSearch} className='mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12'>
            <div className='md:col-span-8'>
                <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Global Search</label>
                <input
                    value={form.search}
                    onChange={(e) => setForm((previous) => ({ ...previous, search: e.target.value }))}
                    placeholder='Cari kode / nama / ID Kemenkes / contact person customer'
                    className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'
                />
            </div>
            <div className='md:col-span-2'>
                <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Status</label>
                <select
                    value={form.status}
                    onChange={(e) => setForm((previous) => ({ ...previous, status: e.target.value }))}
                    className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'
                >
                    <option value=''>All Status</option>
                    <option value='active'>Active</option>
                    <option value='inactive'>Inactive</option>
                </select>
            </div>
            <div className='flex items-end gap-2 md:col-span-2'>
                <button type='submit' className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30'>Terapkan</button>
                <button type='button' onClick={resetFilter} className='inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'><IconRefresh size={16} strokeWidth={1.5} />Refresh Filter</button>
            </div>
        </form>

        <div className='mb-5 flex flex-wrap items-center justify-end gap-2'>
            <a href={route('apps.customers.template.excel')} className='rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50'>Download Template</a>
            <a href={route('apps.customers.export.excel')} className='rounded-lg border border-emerald-500 px-3 py-2 text-sm font-medium text-emerald-600 hover:bg-emerald-50'>Export Customer</a>
            <input type='file' accept='.xlsx,.csv,.txt' onChange={(e) => setImportFile(e.target.files?.[0] || null)} className='block rounded-lg border border-gray-300 px-2 py-2 text-xs' />
            <button type='button' onClick={handleImport} disabled={!importFile || importing} className='inline-flex items-center gap-1 rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50'><IconFileImport size={16} strokeWidth={1.5} />{importing ? 'Importing...' : 'Import Customer'}</button>
            {importResult && <span className={`text-xs ${importResult.type === 'success' ? 'text-emerald-600' : 'text-red-500'}`}>{importResult.message}</span>}
            <Button type='link' href={route('apps.customers.create')} variant='gray' label='Add Customer' />
        </div>

        <Table.Card title='Data Customer'>
            <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th className='w-10'>No</Table.Th>
                        <Table.Th>Customer Code</Table.Th>
                        <Table.Th>Customer Name</Table.Th>
                        <Table.Th>ID Kemenkes</Table.Th>
                        <Table.Th>Contact Person</Table.Th>
                        <Table.Th>Phone</Table.Th>
                        <Table.Th>City</Table.Th>
                        <Table.Th>NPWP</Table.Th>
                        <Table.Th>Payment Term</Table.Th>
                        <Table.Th>Credit Limit</Table.Th>
                        <Table.Th>Status</Table.Th>
                        <Table.Th>Actions</Table.Th>
                    </tr>
                </Table.Thead>
                <Table.Tbody>
                    {customers.data.length ? customers.data.map((c, i) => <tr key={c.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                        <Table.Td className='text-center'>{++i + (customers.current_page - 1) * customers.per_page}</Table.Td>
                        <Table.Td>{c.customer_code}</Table.Td>
                        <Table.Td><Link href={route('apps.customers.show', c.id)} className='text-indigo-600 hover:underline'>{c.customer_name}</Link></Table.Td>
                        <Table.Td>{c.id_kemenkes || '-'}</Table.Td>
                        <Table.Td>{c.contact_person || '-'}</Table.Td>
                        <Table.Td>{c.phone || '-'}</Table.Td>
                        <Table.Td>{c.city || '-'}</Table.Td>
                        <Table.Td>{c.npwp || '-'}</Table.Td>
                        <Table.Td>{c.payment_term_days} days</Table.Td>
                        <Table.Td>{Number(c.credit_limit || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                        <Table.Td><span className={`px-2 py-1 rounded text-xs ${c.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}>{c.status}</span></Table.Td>
                        <Table.Td>
                            <div className='flex gap-2'>
                                <Link href={route('apps.customers.edit', c.id)} className='inline-flex items-center rounded-md border border-amber-300 px-3 py-1 text-sm font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-950/30'>Edit</Link>
                                <button type='button' onClick={() => onDelete(c.id)} className='inline-flex items-center gap-1 rounded-md border border-red-300 px-3 py-1 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/30'><IconTrash size={16} strokeWidth={1.75} />Delete</button>
                            </div>
                        </Table.Td>
                    </tr>) : <Table.Empty colSpan={12} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white' /><span className='text-gray-500'>Data customer tidak ditemukan.</span></>} />}
                </Table.Tbody>
            </Table>
        </Table.Card>

        {customers.last_page !== 1 && <Pagination links={customers.links} />}
    </>;
}

Page.layout = (page) => <AppLayout children={page} />;
