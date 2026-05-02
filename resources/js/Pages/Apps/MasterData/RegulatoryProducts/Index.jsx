import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, router, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconFileImport, IconPencilCog, IconSearch, IconTrash } from '@tabler/icons-react';
import React, { useEffect, useState } from 'react';

export default function Index() {
    const { products, filters, filterOptions } = usePage().props;
    const activeProductType = filters?.product_type === 'MEDICAL_DEVICE' ? 'MEDICAL_DEVICE' : 'DRUG';
    const isDrugView = activeProductType === 'DRUG';
    const [importFile, setImportFile] = useState(null);
    const [importing, setImporting] = useState(false);
    const [alkesImporting, setAlkesImporting] = useState(false);
    const [importResult, setImportResult] = useState(null);
    const [alkesImportFile, setAlkesImportFile] = useState(null);
    const [alkesImportResult, setAlkesImportResult] = useState(null);
    const [query, setQuery] = useState(filters?.q ?? '');
    const [searchError, setSearchError] = useState('');

    useEffect(() => {
        if (!filters?.product_type) {
            router.get(route('apps.master-data.regulatory-products.index'), {
                ...filters,
                product_type: 'DRUG',
            }, { preserveState: true, replace: true, preserveScroll: true });
        }
    }, []);

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
                product_type: activeProductType,
            }, { preserveState: true, replace: true, preserveScroll: true, only: ['products', 'filters'] });
        }, 500);

        return () => clearTimeout(timeout);
    }, [query, activeProductType]);

    const onFilterChange = (key, value) => {
        router.get(route('apps.master-data.regulatory-products.index'), {
            ...filters,
            [key]: value,
            product_type: key === 'product_type' ? value : activeProductType,
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
    const handleImportAlkes = async () => {
        if (!alkesImportFile || alkesImporting) return;
        setAlkesImporting(true);
        setAlkesImportResult(null);
        try {
            const formData = new FormData();
            formData.append('file', alkesImportFile);
            const response = await window.axios.post(route('apps.master-data.regulatory-products.import.alkes'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setAlkesImportResult({ type: 'success', message: response.data?.message ?? 'Import ALKES berhasil.' });
            setAlkesImportFile(null);
            router.reload({ only: ['products'] });
        } catch (error) {
            const fallback = error?.response?.data?.message ?? 'Import ALKES gagal.';
            setAlkesImportResult({ type: 'error', message: fallback });
        } finally {
            setAlkesImporting(false);
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
                    <Button type="link" href={route('apps.master-data.regulatory-products.create', { product_type: activeProductType })} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                </div>
            </div>
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <button type="button" onClick={() => onFilterChange('product_type', 'DRUG')} className={`rounded-lg border px-3 py-2 text-sm font-medium ${isDrugView ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300' : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-300'}`}>Obat</button>
                <button type="button" onClick={() => onFilterChange('product_type', 'MEDICAL_DEVICE')} className={`rounded-lg border px-3 py-2 text-sm font-medium ${!isDrugView ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300' : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-300'}`}>Alkes</button>
            </div>
            <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.source ?? ''} onChange={(e) => onFilterChange('source', e.target.value)}>
                    <option value="">Semua Source</option>
                    {filterOptions?.sources?.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
                {isDrugView ? (
                    <>
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
                    </>
                ) : (
                    <div className="col-span-3 rounded-lg border border-dashed border-sky-300 px-3 py-2 text-sm text-sky-700 dark:border-sky-900 dark:text-sky-300">View ALKES aktif: filter ditampilkan khusus source dan pagination.</div>
                )}
                <select className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" value={filters?.per_page ?? 10} onChange={(e) => onFilterChange('per_page', e.target.value)}>
                    <option value={10}>10 / halaman</option><option value={25}>25 / halaman</option><option value={50}>50 / halaman</option><option value={100}>100 / halaman</option>
                </select>
            </div>
            {isDrugView && <div className="mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12">
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Import File (XLSX/CSV)</label>
                    <input type="file" accept=".xlsx,.csv,.txt" onChange={(e) => setImportFile(e.target.files?.[0] ?? null)} className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100" />
                </div>
                <div className="flex items-end gap-2 md:col-span-8">
                    <a href="/apps/master-data/regulatory-products?download_template=1" className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900">Download Template Import</a>
                    <button type="button" onClick={handleImport} disabled={!importFile || importing} className="inline-flex items-center gap-1 rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-indigo-950/30"><IconFileImport size={16} strokeWidth={1.5} />{importing ? 'Importing...' : 'Import Excel'}</button>
                    <a href={route('apps.master-data.regulatory-products.export.excel', { ...filters, product_type: activeProductType })} className="inline-flex items-center gap-1 rounded-lg border border-emerald-500 px-3 py-2 text-sm text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30">Export Excel</a>
                    {importResult && <span className={`text-xs ${importResult.type === 'success' ? 'text-emerald-600' : 'text-red-500'}`}>{importResult.message}</span>}
                </div>
            </div>}
            {!isDrugView && <div className="mb-5 grid gap-3 rounded-lg border border-sky-200 bg-sky-50/30 p-3 dark:border-sky-900/40 dark:bg-sky-950/20 md:grid-cols-12">
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Import ALKES (XLSX/CSV)</label>
                    <input type="file" accept=".xlsx,.csv,.txt" onChange={(e) => setAlkesImportFile(e.target.files?.[0] ?? null)} className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-sky-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100" />
                </div>
                <div className="flex items-end gap-2 md:col-span-8">
                    <a href={route('apps.master-data.regulatory-products.import-alkes.template')} className="inline-flex items-center gap-1 rounded-lg border border-sky-400 px-3 py-2 text-sm text-sky-700 hover:bg-sky-100 dark:text-sky-300 dark:hover:bg-sky-950/30">Download Template ALKES</a>
                    <button type="button" onClick={handleImportAlkes} disabled={!alkesImportFile || alkesImporting} className="inline-flex items-center gap-1 rounded-lg border border-sky-500 px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-sky-300 dark:hover:bg-sky-950/30"><IconFileImport size={16} strokeWidth={1.5} />{alkesImporting ? 'Importing...' : 'Import Excel ALKES'}</button>
                    {alkesImportResult && <span className={`text-xs ${alkesImportResult.type === 'success' ? 'text-emerald-600' : 'text-red-500'}`}>{alkesImportResult.message}</span>}
                </div>
            </div>}

            <Table.Card title={'Data Regulatory Product'}>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className="w-10">No</Table.Th>
                            <Table.Th>Source</Table.Th>
                            <Table.Th>NIE</Table.Th>
                            {isDrugView ? (
                                <>
                                    <Table.Th>Kode BPOM</Table.Th>
                                    <Table.Th>Nama Produk</Table.Th>
                                    <Table.Th>Produsen</Table.Th>
                                    <Table.Th>Kemasan</Table.Th>
                                    <Table.Th>Kekuatan</Table.Th>
                                    <Table.Th>Jenis Komoditi</Table.Th>
                                    <Table.Th>Packing</Table.Th>
                                    <Table.Th className="w-[40ch] max-w-[40ch]">Bahan Obat</Table.Th>
                                </>
                            ) : (
                                <>
                                    <Table.Th>MERK</Table.Th>
                                    <Table.Th>AKD/AKL</Table.Th>
                                    <Table.Th>Tgl Terbit</Table.Th>
                                    <Table.Th>Tgl Exp</Table.Th>
                                    <Table.Th>Jenis Produk</Table.Th>
                                    <Table.Th>Kelas Risiko</Table.Th>
                                    <Table.Th>Pendaftar</Table.Th>
                                    <Table.Th>Pabrik</Table.Th>
                                </>
                            )}
                            <Table.Th className="w-32"></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {products.data.length ? products.data.map((product, i) => (
                            <tr key={product.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (products.current_page - 1) * products.per_page}</Table.Td>
                                <Table.Td>{product.source?.source_name ?? '-'}</Table.Td>
                                <Table.Td>{product.nie}</Table.Td>
                                {isDrugView ? (
                                    <>
                                        <Table.Td>{product.source_code ?? '-'}</Table.Td>
                                        <Table.Td>{product.product_name_source}</Table.Td>
                                        <Table.Td>{product.industry_name ?? '-'}</Table.Td>
                                        <Table.Td>{product.dosage_form ?? '-'}</Table.Td>
                                        <Table.Td>{product.strength ?? '-'}</Table.Td>
                                        <Table.Td>{product.commodity_type ?? '-'}</Table.Td>
                                        <Table.Td>{product.raw_packaging_text ?? '-'}</Table.Td>
                                        <Table.Td className="w-[40ch] max-w-[40ch]"><div className="min-w-[40ch] max-w-[40ch] whitespace-normal break-words leading-relaxed">{product.raw_composition_text ?? '-'}</div></Table.Td>
                                    </>
                                ) : (
                                    <>
                                        <Table.Td>{product.brand ?? product.product_name_source ?? '-'}</Table.Td>
                                        <Table.Td>{product.license_type ?? '-'}</Table.Td>
                                        <Table.Td>{product.registration_date ?? '-'}</Table.Td>
                                        <Table.Td>{product.expiry_date ?? '-'}</Table.Td>
                                        <Table.Td>{product.device_type ?? '-'}</Table.Td>
                                        <Table.Td>{product.risk_class ?? '-'}</Table.Td>
                                        <Table.Td>{product.registrant_name ?? '-'}</Table.Td>
                                        <Table.Td>{product.manufacturer_name ?? '-'}</Table.Td>
                                    </>
                                )}
                                <Table.Td>
                                    <div className="flex gap-2">
                                        <Button type="edit" href={route('apps.master-data.regulatory-products.edit', product.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />
                                        <Button type="delete" url={route('apps.master-data.regulatory-products.destroy', product.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={12} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data regulatory product tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {products.last_page !== 1 && <Pagination links={products.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
