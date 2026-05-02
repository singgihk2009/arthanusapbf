import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { IconCircleCheck, IconCirclePlus, IconDatabaseOff, IconFileImport, IconRefresh, IconTrash } from '@tabler/icons-react';
import React from 'react';

const defaultFilters = {
    search: '',
};

export default function Index() {
    const { vendors, filters } = usePage().props;
    const { post, delete: destroy } = useForm();
    const [form, setForm] = React.useState({ ...defaultFilters, ...(filters ?? {}) });
    const importForm = useForm({ file: null });

    const deleteVendor = (vendorId) => {
        if (!window.confirm('Apakah kamu yakin ingin menghapus data ini?')) return;
        destroy(`/apps/procurement/vendors/${vendorId}`);
    };

    const qualifyVendor = (vendorId) => {
        if (!window.confirm('Ubah status vendor menjadi Qualified?')) return;
        post(`/apps/procurement/vendors/${vendorId}/approve-qualification`);
    };

    const submitSearch = (e) => {
        e.preventDefault();
        router.get('/apps/procurement/vendors', form.search ? { search: form.search } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilter = () => {
        setForm(defaultFilters);
        router.get('/apps/procurement/vendors', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submitImport = (e) => {
        e.preventDefault();
        importForm.post('/apps/procurement/vendors/import/excel');
    };

    return <>
        <Head title='Vendors' />

        <form onSubmit={submitSearch} className='mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12'>
            <div className='md:col-span-8'>
                <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Global Search</label>
                <input
                    value={form.search}
                    onChange={(e) => setForm((previous) => ({ ...previous, search: e.target.value }))}
                    placeholder='Cari nama / type / status kualifikasi vendor'
                    className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'
                />
            </div>
            <div className='flex items-end gap-2 md:col-span-4'>
                <button type='submit' className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30'>Terapkan</button>
                <button type='button' onClick={resetFilter} className='inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'><IconRefresh size={16} strokeWidth={1.5} />Refresh Filter</button>
            </div>
        </form>

        <div className='mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12'>
            <div className='md:col-span-6'>
                <label className='mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300'>Import File (XLSX/CSV)</label>
                <input type='file' accept='.xlsx,.csv,.txt' onChange={(e) => importForm.setData('file', e.target.files?.[0] || null)} className='block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100' />
            </div>
            <div className='flex items-end gap-2 md:col-span-6'>
                <a href='/apps/procurement/vendors/template/excel' className='inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'>Template Import Excel</a>
                <button type='button' onClick={submitImport} className='inline-flex items-center gap-1 rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-indigo-950/30' disabled={!importForm.data.file || importForm.processing}><IconFileImport size={16} strokeWidth={1.5} />{importForm.processing ? 'Importing...' : 'Import Excel'}</button>
            </div>
        </div>

        <div className='mb-5 flex justify-end'>
            <Button
                type='link'
                href='/apps/procurement/vendors/create'
                icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                variant='gray'
                label='Tambah Vendor'
            />
        </div>

        <Table.Card title='Data Vendor'>
            <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th className='w-10'>No</Table.Th>
                        <Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Type Vendor</Table.Th><Table.Th>Alamat</Table.Th><Table.Th>Provinsi</Table.Th><Table.Th>Status Qualification</Table.Th><Table.Th>Action</Table.Th>
                    </tr>
                </Table.Thead>
                <Table.Tbody>
                    {vendors.data.length ? vendors.data.map((v, i) => <tr key={v.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                        <Table.Td className='text-center'>{++i + (vendors.current_page - 1) * vendors.per_page}</Table.Td>
                        <Table.Td>{v.vendor_code}</Table.Td>
                        <Table.Td><Link href={`/apps/procurement/vendors/${v.id}?tab=overview`} className='text-indigo-600 hover:underline'>{v.vendor_name || v.name || '-'}</Link></Table.Td>
                        <Table.Td>{v.vendor_type || '-'}</Table.Td>
                        <Table.Td>{v.address || '-'}</Table.Td>
                        <Table.Td>{v.province || '-'}</Table.Td>
                        <Table.Td>{v.qualification_status || '-'}</Table.Td>
                        <Table.Td>
                            <div className='flex gap-2'>
                                <button
                                    type='button'
                                    onClick={() => qualifyVendor(v.id)}
                                    disabled={v.qualification_status === 'qualified'}
                                    className='inline-flex items-center gap-1 rounded-md border border-emerald-300 px-3 py-1 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/30'
                                >
                                    <IconCircleCheck size={16} strokeWidth={1.75} /> Checklist
                                </button>
                                <button type='button' onClick={() => deleteVendor(v.id)} className='inline-flex items-center gap-1 rounded-md border border-red-300 px-3 py-1 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/30'><IconTrash size={16} strokeWidth={1.75} />Delete</button>
                            </div>
                        </Table.Td>
                    </tr>) : <Table.Empty colSpan={8} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white' /><span className='text-gray-500'>Data vendor tidak ditemukan.</span></>} />}
                </Table.Tbody>
            </Table>
        </Table.Card>
        {vendors.last_page !== 1 && <Pagination links={vendors.links} />}
    </>;
}
Index.layout = (page) => <AppLayout children={page} />;
