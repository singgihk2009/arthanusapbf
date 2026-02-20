import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';

export default function Index() {
    const { filters, warehouses, items, reportData } = usePage().props;

    const updateFilter = (key, value) => {
        router.get(route('apps.reports.inventory.index'), {
            ...filters,
            [key]: value,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const reportTypes = [
        { value: 'stock-balance', label: 'Stock Balance per Item' },
        { value: 'stock-card', label: 'Kartu Stok per Item' },
        { value: 'expired-soon', label: 'Expired Soon' },
        { value: 'minimum-stock-alerts', label: 'Minimum Stock Alerts' },
    ];

    return (
        <>
            <Head title="Inventory Reports" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Filter Report</h2>

                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <select
                            value={filters.type}
                            onChange={(e) => updateFilter('type', e.target.value)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            {reportTypes.map((reportType) => (
                                <option key={reportType.value} value={reportType.value}>{reportType.label}</option>
                            ))}
                        </select>

                        <select
                            value={filters.warehouse_id ?? ''}
                            onChange={(e) => updateFilter('warehouse_id', e.target.value || null)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            <option value="">Semua Gudang</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>
                            ))}
                        </select>

                        <select
                            value={filters.item_id ?? ''}
                            onChange={(e) => updateFilter('item_id', e.target.value || null)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        >
                            <option value="">Semua Item</option>
                            {items.map((item) => (
                                <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>
                            ))}
                        </select>

                        <input
                            type="date"
                            value={filters.start_date}
                            onChange={(e) => updateFilter('start_date', e.target.value)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        />

                        <input
                            type="date"
                            value={filters.end_date}
                            onChange={(e) => updateFilter('end_date', e.target.value)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                        />

                        <input
                            type="number"
                            min={1}
                            value={filters.days}
                            onChange={(e) => updateFilter('days', e.target.value)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950"
                            placeholder="days"
                        />
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
                                {(reportData.rows?.length ? Object.keys(reportData.rows[0]) : []).map((key) => (
                                    <Table.Th key={key}>{key}</Table.Th>
                                ))}
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {reportData.rows?.length ? reportData.rows.map((row, index) => (
                                <tr key={index} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                    {Object.keys(row).map((key) => (
                                        <Table.Td key={`${index}-${key}`}>{String(row[key] ?? '')}</Table.Td>
                                    ))}
                                </tr>
                            )) : (
                                <Table.Empty colSpan={6} message={<span className="text-gray-500">Tidak ada data report.</span>} />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                {filters.type === 'stock-card' && (
                    <div className="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-900 dark:bg-gray-950">
                        <div>Item ID: <strong>{filters.item_id ?? '-'}</strong></div>
                        <div>Opening Balance: <strong>{reportData.opening_balance ?? 0}</strong></div>
                        <div>Closing Balance: <strong>{reportData.closing_balance ?? 0}</strong></div>
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
