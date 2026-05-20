import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import hasAnyPermission from '@/Utils/Permissions';
import { Head, router, usePage } from '@inertiajs/react';
import { IconArrowsSort, IconCirclePlus, IconDatabaseOff, IconFileImport, IconPencilCog, IconRefresh, IconTrash } from '@tabler/icons-react';
import React, { useMemo, useState } from 'react';

const defaultFilters = {
    search_item: '',
    search_category: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
};

export default function Index() {
    const { items, filters } = usePage().props;
    const [form, setForm] = useState({ ...defaultFilters, ...(filters ?? {}) });
    const [importFile, setImportFile] = useState(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState(null);

    const queryParams = useMemo(() => {
        const params = {
            ...form,
        };

        Object.keys(params).forEach((key) => {
            if (params[key] === '') {
                delete params[key];
            }
        });

        return params;
    }, [form]);

    const applyFilter = (e) => {
        e.preventDefault();
        router.get(route('apps.master-data.items.index'), queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilter = () => {
        setForm(defaultFilters);
        router.get(route('apps.master-data.items.index'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const toggleSort = (column) => {
        const isCurrentColumn = form.sort_by === column;
        const nextDirection = isCurrentColumn && form.sort_dir === 'asc' ? 'desc' : 'asc';

        const nextParams = {
            ...queryParams,
            sort_by: column,
            sort_dir: nextDirection,
        };

        setForm((previous) => ({
            ...previous,
            sort_by: column,
            sort_dir: nextDirection,
        }));

        router.get(route('apps.master-data.items.index'), nextParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const sortIcon = (column) => {
        if (form.sort_by !== column) {
            return <IconArrowsSort size={14} strokeWidth={1.5} className="text-gray-400" />;
        }

        return <span className="text-xs font-semibold text-indigo-600">{form.sort_dir === 'asc' ? '▲' : '▼'}</span>;
    };

    const handleImport = async () => {
        if (!importFile || importing) {
            return;
        }

        setImporting(true);
        setImportResult(null);

        try {
            const formData = new FormData();
            formData.append('file', importFile);

            const response = await window.axios.post(route('apps.master-data.items.import.excel'), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            setImportResult({ type: 'success', message: response.data?.message ?? 'Import item berhasil.' });
            setImportFile(null);
            router.reload({ only: ['items'] });
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
            <Head title="Master Item" />

            <form onSubmit={applyFilter} className="mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12">
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Item</label>
                    <input
                        type="text"
                        value={form.search_item}
                        onChange={(e) => setForm((previous) => ({ ...previous, search_item: e.target.value }))}
                        className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100"
                        placeholder="Cari nama atau SKU item"
                    />
                </div>
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Kategori</label>
                    <input
                        type="text"
                        value={form.search_category}
                        onChange={(e) => setForm((previous) => ({ ...previous, search_category: e.target.value }))}
                        className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100"
                        placeholder="Cari kategori"
                    />
                </div>
                <div className="flex items-end gap-2 md:col-span-4">
                    <button type="submit" className="rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30">Terapkan</button>
                    <button type="button" onClick={resetFilter} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900"><IconRefresh size={16} strokeWidth={1.5} />Refresh Filter</button>
                    <a href={`${route('apps.master-data.items.export.excel')}?${new URLSearchParams(queryParams).toString()}`} className="inline-flex items-center gap-1 rounded-lg border border-emerald-500 px-3 py-2 text-sm text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"><IconFileImport size={16} strokeWidth={1.5} />Export Excel</a>
                </div>
            </form>

            <div className="mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 md:grid-cols-12">
                <div className="md:col-span-4">
                    <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Import File (XLSX/CSV)</label>
                    <input
                        type="file"
                        accept=".xlsx,.csv,.txt"
                        onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                        className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100"
                    />
                </div>
                <div className="flex items-end gap-2 md:col-span-8">
                    <a href={route('apps.master-data.items.template.excel')} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900">Download Template</a>
                    <button type="button" onClick={handleImport} disabled={!importFile || importing} className="inline-flex items-center gap-1 rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-indigo-950/30"><IconFileImport size={16} strokeWidth={1.5} />{importing ? 'Importing...' : 'Import Item'}</button>
                    {importResult && <span className={`text-xs ${importResult.type === 'success' ? 'text-emerald-600' : 'text-red-500'}`}>{importResult.message}</span>}
                </div>
            </div>

            <div className="mb-5 flex justify-end gap-2">
                {hasAnyPermission(['master-item-create']) && <Button type="link" href={route('apps.master-data.items.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />}
            </div>

            <Table.Card title={'Data Item'}>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className="w-10">No</Table.Th>
                            <Table.Th><button type="button" onClick={() => toggleSort('sku')} className="inline-flex items-center gap-1">SKU {sortIcon('sku')}</button></Table.Th>
                            <Table.Th>NIE</Table.Th>
                            <Table.Th><button type="button" onClick={() => toggleSort('name')} className="inline-flex items-center gap-1">Nama {sortIcon('name')}</button></Table.Th>
                            <Table.Th><button type="button" onClick={() => toggleSort('category_name')} className="inline-flex items-center gap-1">Kategori {sortIcon('category_name')}</button></Table.Th>
                            <Table.Th>Base UOM</Table.Th>
                            <Table.Th>Min. Stok</Table.Th>
                            <Table.Th>Foto</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className="w-24"></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {items.data.length ? items.data.map((item, i) => (
                            <tr key={item.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (items.current_page - 1) * items.per_page}</Table.Td>
                                <Table.Td>{item.sku}</Table.Td>
                                <Table.Td>{item.nie ?? '-'}</Table.Td>
                                <Table.Td><a href={route('apps.inventory.items.card', item.id)} className="text-indigo-600 hover:underline">{item.name}</a></Table.Td>
                                <Table.Td>{item.category?.name ?? '-'}</Table.Td>
                                <Table.Td>{item.base_uom?.code ?? '-'}</Table.Td>
                                <Table.Td>{Number(item.minimum_stock_base ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 })}</Table.Td>
                                <Table.Td>
                                    <div className="flex items-center gap-2">
                                        {item.default_picture?.image_url ? <img src={item.default_picture.image_url} alt={item.name} className="h-10 w-10 rounded object-cover" /> : <span className="text-xs text-gray-500">-</span>}
                                        <span className="text-xs">{item.pictures_count ?? 0}/6</span>
                                    </div>
                                </Table.Td>
                                <Table.Td>{item.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>
                                    <div className="flex gap-2">
                                        {hasAnyPermission(['master-item-update']) && <Button type="edit" href={route('apps.master-data.items.edit', item.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />}
                                        {hasAnyPermission(['master-item-delete']) && <Button type="delete" url={route('apps.master-data.items.destroy', item.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />}
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={10} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data item tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {items.last_page !== 1 && <Pagination links={items.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
