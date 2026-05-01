import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, router, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconFileImport, IconPencilCog, IconSearch, IconTrash } from '@tabler/icons-react';
import React, { useEffect, useState } from 'react';

export default function Index() {
    const { products, filters, filterOptions } = usePage().props;
    const [importFile, setImportFile] = useState(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState(null);
    const [query, setQuery] = useState(filters?.q ?? '');
    const [searchError, setSearchError] = useState('');

    useEffect(() => {
        const timeout = setTimeout(() => {
            const normalized = query.trim();
            if (normalized.length > 0 && normalized.length < 3) {
                setSearchError('Minimal 3 karakter untuk pencarian.');
                return;
            }
            setSearchError('');
            router.get(route('apps.master-data.regulatory-products.index'), {
                ...filters,
                q: normalized,
            }, { preserveState: true, replace: true, preserveScroll: true, only: ['products', 'filters'] });
        }, 500);

        return () => clearTimeout(timeout);
    }, [query]);

    const onFilterChange = (key, value) => {
        router.get(route('apps.master-data.regulatory-products.index'), {
            ...filters,
            [key]: value,
        }, { preserveState: true, replace: true, preserveScroll: true });
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
                    <div className="relative">
                        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari NIE / nama produk / produsen / komposisi..." className='py-2 px-4 pr-11 block w-full rounded-lg text-sm border focus:outline-none focus:ring-0 text-gray-700 bg-white border-gray-200 dark:text-gray-200 dark:bg-gray-950 dark:border-gray-900' />
                        <div className='absolute inset-y-0 right-0 flex items-center pointer-events-none pr-4'><IconSearch className='text-gray-500 w-5 h-5' /></div>
                    </div>
                    {searchError && <p className="mt-1 text-xs text-rose-600">{searchError}</p>}
                </div>
                <div className="flex gap-2">
                    <Button type="link" href={route('apps.master-data.regulatory-products.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                </div>
            </div>
            <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.source ?? ''} onChange={(e) => onFilterChange('source', e.target.value)}>
                    <option value="">Semua Source</option>
                    {filterOptions?.sources?.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.commodity_type ?? ''} onChange={(e) => onFilterChange('commodity_type', e.target.value)}>
                    <option value="">Semua Jenis Komoditi</option>
                    {filterOptions?.commodity_types?.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.dosage_form ?? ''} onChange={(e) => onFilterChange('dosage_form', e.target.value)}>
                    <option value="">Semua Dosage Form</option>
                    {filterOptions?.dosage_forms?.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.producer ?? ''} onChange={(e) => onFilterChange('producer', e.target.value)}>
                    <option value="">Semua Produsen</option>
                    {filterOptions?.producers?.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.per_page ?? 10} onChange={(e) => onFilterChange('per_page', e.target.value)}>
                    <option value={10}>10 / halaman</option><option value={25}>25 / halaman</option><option value={50}>50 / halaman</option><option value={100}>100 / halaman</option>
                </select>
            </div>
            <div className="mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12">
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Import File (XLSX/CSV)</label>
                    <input type="file" accept=".xlsx,.csv,.txt" onChange={(e) => setImportFile(e.target.files?.[0] ?? null)} className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100" />
                </div>
                <div className="flex items-end gap-2 md:col-span-8">
                    <a href="/apps/master-data/regulatory-products?download_template=1" className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900">Download Template Import</a>
                    <button type="button" onClick={handleImport} disabled={!importFile || importing} className="inline-flex items-center gap-1 rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-indigo-950/30"><IconFileImport size={16} strokeWidth={1.5} />{importing ? 'Importing...' : 'Import Excel'}</button>
                    <a href={route('apps.master-data.regulatory-products.export.excel')} className="inline-flex items-center gap-1 rounded-lg border border-emerald-500 px-3 py-2 text-sm text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30">Export Excel</a>
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
                            <Table.Th>Produsen</Table.Th>
                            <Table.Th>Kemasan</Table.Th>
                            <Table.Th>Kekuatan</Table.Th>
                            <Table.Th>Jenis Komoditi</Table.Th>
                            <Table.Th>Packing</Table.Th>
                            <Table.Th className="w-[40ch] max-w-[40ch]">Bahan Obat</Table.Th>
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
                                <Table.Td>{product.industry_name ?? '-'}</Table.Td>
                                <Table.Td>{product.dosage_form ?? '-'}</Table.Td>
                                <Table.Td>{product.strength ?? '-'}</Table.Td>
                                <Table.Td>{product.commodity_type ?? '-'}</Table.Td>
                                <Table.Td>{product.raw_packaging_text ?? '-'}</Table.Td>
                                <Table.Td className="w-[40ch] max-w-[40ch] whitespace-normal break-words">{product.raw_composition_text ?? '-'}</Table.Td>
                                <Table.Td>
                                    <div className="flex gap-2">
                                        <Button type="edit" href={route('apps.master-data.regulatory-products.edit', product.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />
                                        <Button type="delete" url={route('apps.master-data.regulatory-products.destroy', product.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={11} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data regulatory product tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {products.last_page !== 1 && <Pagination links={products.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
