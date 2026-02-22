import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React, { useMemo } from 'react';

export default function Index() {
    const { filters, warehouses, items, categories, reportData } = usePage().props;

    const updateFilters = (nextFilters) => {
        router.get(route('apps.reports.inventory.index'), {
            ...filters,
            ...nextFilters,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const stockBalanceColumns = [
        { key: 'number', label: 'No' },
        { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
        { key: 'item_name', label: 'Item', sortKey: 'item' },
        { key: 'category_name', label: 'Kategori', sortKey: 'category' },
        { key: 'sku', label: 'SKU', sortKey: 'sku' },
        { key: 'on_hand_base', label: 'On Hand Base', sortKey: 'on_hand_base' },
        { key: 'reserved_base', label: 'Reserved Base' },
        { key: 'batch_no', label: 'Batch' },
        { key: 'expired_date', label: 'Expired Date' },
    ];

    const reportTypes = [
        { value: 'stock-balance', label: 'Stock Balance per Item' },
        { value: 'stock-card', label: 'Kartu Stok per Item' },
        { value: 'expired-soon', label: 'Expired Soon' },
        { value: 'minimum-stock-alerts', label: 'Minimum Stock Alerts' },
    ];

    const exportUrl = useMemo(() => route('apps.reports.inventory.export.excel', {
        ...filters,
        page: undefined,
    }), [filters]);

    const toggleSort = (sortKey) => {
        const nextDirection = filters.sort_by === sortKey && filters.sort_dir === 'asc' ? 'desc' : 'asc';
        updateFilters({ sort_by: sortKey, sort_dir: nextDirection, page: 1 });
    };

    const pagination = reportData.pagination;

    return (
        <>
            <Head title="Inventory Reports" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Filter Report</h2>

                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <select
                            value={filters.type}
                            onChange={(e) => updateFilters({ type: e.target.value, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            {reportTypes.map((reportType) => (
                                <option key={reportType.value} value={reportType.value}>{reportType.label}</option>
                            ))}
                        </select>

                        <select
                            value={filters.warehouse_id ?? ''}
                            onChange={(e) => updateFilters({ warehouse_id: e.target.value || null, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            <option value="">Semua Gudang</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>
                            ))}
                        </select>

                        <select
                            value={filters.item_id ?? ''}
                            onChange={(e) => updateFilters({ item_id: e.target.value || null, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            <option value="">Semua Item</option>
                            {items.map((item) => (
                                <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>
                            ))}
                        </select>

                        <select
                            value={filters.category_id ?? ''}
                            onChange={(e) => updateFilters({ category_id: e.target.value || null, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            <option value="">Semua Kategori</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>{category.name}</option>
                            ))}
                        </select>

                        <input
                            type="text"
                            value={filters.search ?? ''}
                            onChange={(e) => updateFilters({ search: e.target.value, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                            placeholder="Search warehouse/item/sku/kategori"
                        />

                        <a
                            href={exportUrl}
                            className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-900 dark:text-gray-200 dark:hover:bg-gray-900"
                        >
                            Export to Excel
                        </a>

                        <input
                            type="date"
                            value={filters.start_date}
                            onChange={(e) => updateFilters({ start_date: e.target.value })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        />

                        <input
                            type="date"
                            value={filters.end_date}
                            onChange={(e) => updateFilters({ end_date: e.target.value })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        />

                        <input
                            type="number"
                            min={1}
                            value={filters.days}
                            onChange={(e) => updateFilters({ days: e.target.value })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                            placeholder="days"
                        />

                        {filters.type === 'stock-balance' && (
                            <select
                                value={filters.per_page}
                                onChange={(e) => updateFilters({ per_page: Number(e.target.value), page: 1 })}
                                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                            >
                                {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size} / page</option>)}
                            </select>
                        )}
                    </div>

                    {filters.type === 'stock-card' && !filters.item_id && (
                        <p className="mt-3 text-xs text-amber-600 dark:text-amber-400">
                            Pilih item terlebih dahulu untuk menampilkan kartu stok.
                        </p>
                    )}
                </div>

                <Table.Card title={reportData.title}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                {filters.type === 'stock-balance'
                                    ? stockBalanceColumns.map((column) => (
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
                                    ))
                                    : (reportData.rows?.length ? Object.keys(reportData.rows[0]) : []).map((key) => (
                                        <Table.Th key={key}>{key}</Table.Th>
                                    ))}
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {reportData.rows?.length ? reportData.rows.map((row, index) => (
                                <tr key={index} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                    {filters.type === 'stock-balance' ? (
                                        <>
                                            <Table.Td>{(pagination?.from ?? 1) + index}</Table.Td>
                                            <Table.Td>{row.warehouse_name}</Table.Td>
                                            <Table.Td>{row.item_name}</Table.Td>
                                            <Table.Td>{row.category_name}</Table.Td>
                                            <Table.Td>{row.sku}</Table.Td>
                                            <Table.Td>{row.on_hand_base}</Table.Td>
                                            <Table.Td>{row.reserved_base}</Table.Td>
                                            <Table.Td>{row.batch_no ?? '-'}</Table.Td>
                                            <Table.Td>{row.expired_date ?? '-'}</Table.Td>
                                        </>
                                    ) : Object.keys(row).map((key) => (
                                        <Table.Td key={`${index}-${key}`}>{String(row[key] ?? '')}</Table.Td>
                                    ))}
                                </tr>
                            )) : (
                                <Table.Empty colSpan={9} message={<span className="text-gray-500">Tidak ada data report.</span>} />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                {filters.type === 'stock-balance' && pagination && (
                    <div className="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-900 dark:bg-gray-950 md:flex-row md:items-center md:justify-between">
                        <div className="text-gray-600 dark:text-gray-300">
                            Menampilkan {pagination.from ?? 0} - {pagination.to ?? 0} dari {pagination.total} data
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => updateFilters({ page: pagination.current_page - 1 })}
                                disabled={pagination.current_page <= 1}
                                className="rounded border border-gray-300 px-3 py-1 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700"
                            >
                                Prev
                            </button>
                            <span>Page {pagination.current_page} / {pagination.last_page}</span>
                            <button
                                type="button"
                                onClick={() => updateFilters({ page: pagination.current_page + 1 })}
                                disabled={pagination.current_page >= pagination.last_page}
                                className="rounded border border-gray-300 px-3 py-1 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}

                {filters.type === 'stock-card' && (
                    <div className="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-900 dark:bg-gray-950">
                        <div>Item ID: <strong>{filters.item_id ?? '-'}</strong></div>
                        <div>Opening Balance: <strong>{reportData.opening_balance ?? 0}</strong></div>
                        <div>Closing Balance: <strong>{reportData.closing_balance ?? 0}</strong></div>
                    </div>
                )}

                {filters.type === 'expired-soon' && (
                    <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200">
                        Menampilkan batch item tracked-expired dengan stok &gt; 0 yang sudah expired atau akan expired dalam <strong>{filters.days}</strong> hari.
                        Prioritas: <strong>EXPIRED</strong> (sudah lewat), <strong>KRITIS</strong> (≤ 7 hari), dan <strong>PERINGATAN</strong> (&gt; 7 hari).
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
