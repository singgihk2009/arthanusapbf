import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React, { useMemo } from 'react';

export default function Index() {
    const { filters, warehouses, categories, reportData } = usePage().props;

    const reportTypes = [
        { value: 'incoming-items', label: 'Laporan Barang Masuk (Qty & Value)' },
        { value: 'item-usage', label: 'Laporan Pemakaian Barang (Qty & Valuation Rp)' },
    ];

    const isIncomingReport = filters.type === 'incoming-items';

    const columns = [
        { key: 'number', label: 'No' },
        { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
        { key: 'trx_datetime', label: 'Tanggal', sortKey: 'trx_datetime' },
        { key: 'reference', label: 'Referensi' },
        { key: 'item_name', label: 'Item', sortKey: 'item' },
        { key: 'category_name', label: 'Kategori', sortKey: 'category' },
        { key: 'sku', label: 'SKU' },
        { key: 'uom_name', label: 'UoM' },
        { key: 'unit_price', label: 'Unit Price', sortKey: 'unit_price' },
        {
            key: 'qty',
            label: isIncomingReport ? 'Qty Masuk' : 'Qty Pemakaian',
            sortKey: 'qty',
        },
        {
            key: 'value',
            label: isIncomingReport ? 'Value' : 'Valuation Rp',
            sortKey: 'value',
        },
        ...(isIncomingReport
            ? [
                { key: 'status', label: 'Status', sortKey: 'status' },
                { key: 'vendor_name', label: 'Vendor', sortKey: 'vendor' },
            ]
            : []),
    ];

    const updateFilters = (nextFilters) => {
        router.get(route('apps.reports.inventory.index'), {
            ...filters,
            ...nextFilters,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const toggleSort = (sortKey) => {
        const nextDirection = filters.sort_by === sortKey && filters.sort_dir === 'asc' ? 'desc' : 'asc';
        updateFilters({ sort_by: sortKey, sort_dir: nextDirection, page: 1 });
    };

    const exportUrl = useMemo(() => route('apps.reports.inventory.export.excel', {
        ...filters,
        page: undefined,
    }), [filters]);

    const pagination = reportData.pagination;

    return (
        <>
            <Head title="Inventory Reports" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Filter Report</h2>

                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-7">
                        <select
                            value={filters.type}
                            onChange={(e) => updateFilters({ type: e.target.value, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                        >
                            {reportTypes.map((reportType) => (
                                <option key={reportType.value} value={reportType.value}>{reportType.label}</option>
                            ))}
                        </select>

                        <select
                            value={filters.warehouse_id ?? ''}
                            onChange={(e) => updateFilters({ warehouse_id: e.target.value || null, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                        >
                            <option value="">Semua Gudang</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>
                            ))}
                        </select>

                        <select
                            value={filters.category_id ?? ''}
                            onChange={(e) => updateFilters({ category_id: e.target.value || null, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                        >
                            <option value="">Semua Kategori</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>{category.name}</option>
                            ))}
                        </select>

                        {isIncomingReport && (
                            <select
                                value={filters.status ?? 'all'}
                                onChange={(e) => updateFilters({ status: e.target.value, page: 1 })}
                                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                            >
                                <option value="all">Semua Status</option>
                                <option value="posted">Posted</option>
                                <option value="unposted">Belum Posted</option>
                            </select>
                        )}

                        <input
                            type="text"
                            value={filters.search ?? ''}
                            onChange={(e) => updateFilters({ search: e.target.value, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                            placeholder="Global search warehouse/item/sku/ref"
                        />

                        <select
                            value={filters.per_page}
                            onChange={(e) => updateFilters({ per_page: Number(e.target.value), page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                        >
                            {[15, 50, 100].map((size) => (
                                <option key={size} value={size}>Show {size}</option>
                            ))}
                        </select>

                        <a
                            href={exportUrl}
                            className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-900 dark:text-gray-200 dark:hover:bg-gray-900"
                        >
                            Export to Excel
                        </a>
                    </div>
                </div>

                <Table.Card title={isIncomingReport ? 'Laporan Barang Masuk' : 'Laporan Pemakaian Barang'}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                {columns.map((column) => (
                                    <Table.Th key={column.key}>
                                        {column.sortKey ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1"
                                                onClick={() => toggleSort(column.sortKey)}
                                            >
                                                {column.label}
                                                {filters.sort_by === column.sortKey ? (filters.sort_dir === 'asc' ? '↑' : '↓') : ''}
                                            </button>
                                        ) : column.label}
                                    </Table.Th>
                                ))}
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {reportData.rows?.length ? reportData.rows.map((row, index) => (
                                <tr key={`${row.reference}-${index}`} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                    <Table.Td>{(pagination?.from ?? 1) + index}</Table.Td>
                                    <Table.Td>{row.warehouse_name}</Table.Td>
                                    <Table.Td>{row.trx_datetime}</Table.Td>
                                    <Table.Td>{row.reference}</Table.Td>
                                    <Table.Td>{row.item_name}</Table.Td>
                                    <Table.Td>{row.category_name}</Table.Td>
                                    <Table.Td>{row.sku}</Table.Td>
                                    <Table.Td>{row.uom_name}</Table.Td>
                                    <Table.Td>{Number(row.unit_price).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                                    <Table.Td>{Number(row.qty).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                    <Table.Td>{Number(row.value).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                                    {isIncomingReport && (
                                        <>
                                            <Table.Td>{row.status}</Table.Td>
                                            <Table.Td>{row.vendor_name}</Table.Td>
                                        </>
                                    )}
                                </tr>
                            )) : (
                                <Table.Empty colSpan={columns.length} message={<span className="text-gray-500">Tidak ada data report.</span>} />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                {pagination && (
                    <div className="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-900 dark:bg-gray-950 md:flex-row md:items-center md:justify-between">
                        <div className="text-gray-600 dark:text-gray-300">
                            Menampilkan {pagination.from ?? 0} - {pagination.to ?? 0} dari {pagination.total} data
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => updateFilters({ page: pagination.current_page - 1 })}
                                disabled={pagination.current_page <= 1}
                                className="rounded border border-gray-300 px-3 py-1 text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-200"
                            >
                                Prev
                            </button>
                            <span className="text-gray-700 dark:text-gray-200">Page {pagination.current_page} / {pagination.last_page}</span>
                            <button
                                type="button"
                                onClick={() => updateFilters({ page: pagination.current_page + 1 })}
                                disabled={pagination.current_page >= pagination.last_page}
                                className="rounded border border-gray-300 px-3 py-1 text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-200"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
