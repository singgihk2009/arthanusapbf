import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconFileImport, IconPencilCog, IconTrash } from '@tabler/icons-react';
import React, { useState } from 'react';

export default function Index() {
    const { products } = usePage().props;
    const { delete: destroy } = useForm();
    const [importFile, setImportFile] = useState(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState(null);

    const bulkDelete = () => {
        const ids = products.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.regulatory-products.destroy', ids));
    };
    const handleImport = async () => {
        if (!importFile || importing) return;
        setImporting(true);
        setImportResult(null);
        try {
            const formData = new FormData();
            formData.append('file', importFile);
            const response = await window.axios.post('/apps/master-data/regulatory-products/import/excel', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setImportResult({ type: 'success', message: response.data?.message ?? 'Import regulatory product berhasil.' });
            setImportFile(null);
            router.reload({ only: ['products'] });
        } catch (error) {
            const data = error?.response?.data;
            const errorLines = Array.isArray(data?.errors) ? data.errors.map((line) => `Baris ${line.row}: ${line.message}`).join(' | ') : '';
            setImportResult({ type: 'error', message: data?.message ? `${data.message}${errorLines ? ` (${errorLines})` : ''}` : 'Import gagal.' });
        } finally {
            setImporting(false);
        }
    };

    return (
        <>
            <Head title="Master Regulatory Product" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3">
                    <Search url={route('apps.master-data.regulatory-products.index')} placeholder="Cari NIE / nama produk..." />
                </div>
                <div className="flex gap-2">
                    <Button type="link" href={route('apps.master-data.regulatory-products.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                    <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />
                </div>
            </div>
            <div className="mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12">
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Import File (XLSX/CSV)</label>
                    <input type="file" accept=".xlsx,.csv,.txt" onChange={(e) => setImportFile(e.target.files?.[0] ?? null)} className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100" />
                </div>
                <div className="flex items-end gap-2 md:col-span-8">
                    <a href="/apps/master-data/regulatory-products/template/excel" className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900">Download Template Import</a>
                    <button type="button" onClick={handleImport} disabled={!importFile || importing} className="inline-flex items-center gap-1 rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-indigo-950/30"><IconFileImport size={16} strokeWidth={1.5} />{importing ? 'Importing...' : 'Import Excel'}</button>
                    {importResult && <span className={`text-xs ${importResult.type === 'success' ? 'text-emerald-600' : 'text-red-500'}`}>{importResult.message}</span>}
                </div>
            </div>

            <Table.Card title={'Data Regulatory Product'}>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className="w-10">No</Table.Th>
                            <Table.Th>Source</Table.Th>
                            <Table.Th>NIE</Table.Th>
                            <Table.Th>Nama Produk</Table.Th>
                            <Table.Th className="w-32"></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {products.data.length ? products.data.map((product, i) => (
                            <tr key={product.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (products.current_page - 1) * products.per_page}</Table.Td>
                                <Table.Td>{product.source?.source_name ?? '-'}</Table.Td>
                                <Table.Td>{product.nie}</Table.Td>
                                <Table.Td>{product.product_name_source}</Table.Td>
                                <Table.Td>
                                    <div className="flex gap-2">
                                        <Button type="edit" href={route('apps.master-data.regulatory-products.edit', product.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />
                                        <Button type="delete" url={route('apps.master-data.regulatory-products.destroy', product.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={5} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data regulatory product tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {products.last_page !== 1 && <Pagination links={products.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
