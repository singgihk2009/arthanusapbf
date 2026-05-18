import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React, { useEffect, useMemo, useState } from 'react';


const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

export default function Index() {
    const { filters, warehouses, categories, items, selectedItem, facilitySchemes, reportData } = usePage().props;
    const isIncomingReport = filters.type === 'incoming-items';
    const isUsageReport = filters.type === 'item-usage';
    const isStockPositionReport = filters.type === 'stock-position';
    const isStockCardReport = filters.type === 'stock-card-movement';
    const isDateRangeReport = isIncomingReport || isUsageReport || isStockPositionReport || isStockCardReport;

    const [itemQuery, setItemQuery] = useState('');
    const [itemOptions, setItemOptions] = useState([]);
    const [itemLoading, setItemLoading] = useState(false);
    const [itemError, setItemError] = useState('');

    const canSearchItem = itemQuery.trim().length >= 3;

    useEffect(() => {
        if (!isStockCardReport) return;
        if (!canSearchItem) {
            setItemOptions([]);
            setItemError('');
            return;
        }

        const timer = setTimeout(async () => {
            setItemLoading(true);
            try {
                const response = await window.axios.get(route('apps.reports.inventory.search-items'), {
                    params: { q: itemQuery.trim(), limit: 20 },
                });
                setItemOptions(response.data?.data ?? []);
                setItemError('');
            } catch (error) {
                setItemOptions([]);
                setItemError(error?.response?.data?.message ?? 'Gagal mengambil data item.');
            } finally {
                setItemLoading(false);
            }
        }, 400);

        return () => clearTimeout(timer);
    }, [itemQuery, canSearchItem, isStockCardReport]);

    const reportTypes = [
        { value: 'incoming-items', label: 'Laporan Barang Masuk (Qty & Value)' },
        { value: 'item-usage', label: 'Laporan Barang Keluar (Qty & Valuation Rp)' },
        { value: 'stock-position', label: 'Laporan Posisi Stok' },
        { value: 'stock-card-movement', label: 'Laporan Kartu Stok Movement per Item' },
    ];

    const columns = useMemo(() => {
        if (isStockPositionReport) {
            return [
                { key: 'number', label: 'No' },
                { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
                { key: 'item_name', label: 'Item', sortKey: 'item' },
                { key: 'category_name', label: 'Kategori', sortKey: 'category' },
                { key: 'sku', label: 'SKU' },
                { key: 'on_hand', label: 'On Hand', sortKey: 'on_hand' },
                { key: 'reserved', label: 'Reserved', sortKey: 'reserved' },
                { key: 'available', label: 'Available', sortKey: 'available' },
            ];
        }

        if (isStockCardReport) {
            return [
                { key: 'number', label: 'No' },
                { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
                { key: 'trx_datetime', label: 'Tanggal', sortKey: 'trx_datetime' },
                { key: 'reference', label: 'Referensi' },
                { key: 'item_name', label: 'Item', sortKey: 'item' },
                { key: 'sku', label: 'SKU' },
                { key: 'qty', label: 'Qty Movement', sortKey: 'qty' },
                { key: 'running_balance', label: 'Saldo Berjalan', sortKey: 'running_balance' },
                { key: 'unit_price', label: 'Unit Cost', sortKey: 'unit_price' },
                { key: 'value', label: 'Value Movement', sortKey: 'value' },
            ];
        }

        return [
            { key: 'number', label: 'No' },
            { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
            { key: 'trx_datetime', label: 'Tanggal', sortKey: 'trx_datetime' },
            { key: 'reference', label: 'Referensi' },
            ...((isIncomingReport || isUsageReport) ? [{ key: 'gr_number', label: 'Nomor PO' }] : []),
            { key: 'transaction_code', label: 'Kode Transaksi' },
            ...((isIncomingReport || isUsageReport) ? [{ key: 'po_date', label: 'Tanggal PO' }] : []),
            { key: 'item_name', label: 'Item', sortKey: 'item' },
            { key: 'category_name', label: 'Kategori', sortKey: 'category' },
            { key: 'sku', label: 'SKU' },
            { key: 'uom_name', label: 'UoM' },
            { key: 'unit_price', label: 'Unit Price', sortKey: 'unit_price' },
            {
                key: 'qty',
                label: isIncomingReport ? 'Qty Masuk' : 'Qty Keluar',
                sortKey: 'qty',
            },
            {
                key: 'value',
                label: isIncomingReport ? 'Value' : 'Value',
                sortKey: 'value',
            },
            ...((isIncomingReport || isUsageReport)
                ? [
                    { key: 'status', label: 'Status', sortKey: 'status' },
                    { key: 'vendor_name', label: 'Vendor', sortKey: 'vendor' },
                    { key: 'facility_name', label: 'Fasilitas' },
                    { key: 'facility_reference_no', label: 'No Fasilitas' },
                ]
                : []),
        ];
    }, [isIncomingReport, isUsageReport, isStockCardReport, isStockPositionReport]);

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

    const reportTitle = isIncomingReport
        ? 'Laporan Barang Masuk'
        : isUsageReport
            ? 'Laporan Barang Keluar'
            : isStockPositionReport
                ? 'Laporan Posisi Stok'
                : 'Laporan Kartu Stok Movement per Item';

    const poLink = (row) => {
        if (!row?.purchase_order_id || !row?.gr_number || row.gr_number === '-') return row?.gr_number ?? '-';

        return (
            <a
                href={route('apps.procurement.purchase-orders.show', row.purchase_order_id)}
                className="text-indigo-600 hover:underline dark:text-indigo-400"
            >
                {row.gr_number}
            </a>
        );
    };

    const vendorLink = (row) => {
        if (!row?.vendor_id || !row?.vendor_name || row.vendor_name === '-') return row?.vendor_name ?? '-';

        return (
            <a
                href={route('apps.procurement.vendors.show', row.vendor_id)}
                className="text-indigo-600 hover:underline dark:text-indigo-400"
            >
                {row.vendor_name}
            </a>
        );
    };

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

                        {!isStockCardReport && (
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
                        )}

                        {isStockPositionReport && (
                            <select
                                value={filters.item_id ?? ''}
                                onChange={(e) => updateFilters({ item_id: e.target.value || null, page: 1 })}
                                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                            >
                                <option value="">Semua Item</option>
                                {items.map((item) => (
                                    <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>
                                ))}
                            </select>
                        )}
                        {isStockCardReport && (
                            <div className="relative lg:col-span-2">
                                <input
                                    type="text"
                                    value={itemQuery}
                                    onChange={(e) => setItemQuery(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                                    placeholder={selectedItem ? `${selectedItem.sku} - ${selectedItem.name}` : 'Pilih Item (Wajib) - ketik min. 3 karakter'}
                                />
                                {filters.item_id && selectedItem && (
                                    <div className="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
                                        Terpilih: {selectedItem.sku} - {selectedItem.name}{' '}
                                        <button type="button" className="text-rose-600" onClick={() => updateFilters({ item_id: null, page: 1 })}>Hapus</button>
                                    </div>
                                )}
                                {!canSearchItem && (
                                    <div className="mt-1 text-xs text-gray-500">Ketik minimal 3 karakter untuk mencari item.</div>
                                )}
                                {itemLoading && <div className="mt-1 text-xs text-gray-500">Mencari item...</div>}
                                {itemError && <div className="mt-1 text-xs text-rose-600">{itemError}</div>}
                                {!itemLoading && canSearchItem && !itemError && itemOptions.length > 0 && (
                                    <div className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-md dark:border-gray-800 dark:bg-gray-900">
                                        {itemOptions.map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() => {
                                                    updateFilters({ item_id: item.id, page: 1 });
                                                    setItemQuery('');
                                                    setItemOptions([]);
                                                }}
                                                className="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                            >
                                                {item.sku} - {item.name}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {(isIncomingReport || isUsageReport) && (
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


                        {(isIncomingReport || isUsageReport || isStockPositionReport || isStockCardReport) && (
                            <select
                                value={filters.facility_scheme_id ?? ''}
                                onChange={(e) => updateFilters({ facility_scheme_id: e.target.value || null, page: 1 })}
                                className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                            >
                                <option value="">Semua Fasilitas</option>
                                {facilitySchemes.map((facility) => (
                                    <option key={facility.id} value={facility.id}>{facility.code} - {facility.name}</option>
                                ))}
                            </select>
                        )}

                        {isDateRangeReport && (
                            <>
                                <input
                                    type="date"
                                    value={filters.start_date ?? ''}
                                    onChange={(e) => updateFilters({ start_date: e.target.value, page: 1 })}
                                    className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                                />
                                <input
                                    type="date"
                                    value={filters.end_date ?? ''}
                                    onChange={(e) => updateFilters({ end_date: e.target.value, page: 1 })}
                                    className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                                />
                            </>
                        )}

                        <input
                            type="text"
                            value={filters.search ?? ''}
                            onChange={(e) => updateFilters({ search: e.target.value, page: 1 })}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-200"
                            placeholder="Cari warehouse/item/sku"
                        />

                        <select
                            value={filters.per_page ?? 15}
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

                <Table.Card title={reportTitle}>
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
                                <tr key={`${row.reference ?? row.sku}-${index}`} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                    <Table.Td>{(pagination?.from ?? 1) + index}</Table.Td>
                                    <Table.Td>{row.warehouse_name}</Table.Td>
                                    {isStockPositionReport ? (
                                        <>
                                            <Table.Td>{row.item_name}</Table.Td>
                                            <Table.Td>{row.category_name}</Table.Td>
                                            <Table.Td>{row.sku}</Table.Td>
                                            <Table.Td>{Number(row.on_hand).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                            <Table.Td>{Number(row.reserved).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                            <Table.Td>{Number(row.available).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                        </>
                                    ) : isStockCardReport ? (
                                        <>
                                            <Table.Td>{formatDate(row.trx_datetime)}</Table.Td>
                                            <Table.Td>{row.reference}</Table.Td>
                                            <Table.Td>{row.item_name}</Table.Td>
                                            <Table.Td>{row.sku}</Table.Td>
                                            <Table.Td>{Number(row.qty).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                            <Table.Td>{Number(row.running_balance).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                            <Table.Td>{Number(row.unit_price).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                                            <Table.Td>{Number(row.value).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                                        </>
                                    ) : (
                                        <>
                                            <Table.Td>{formatDate(row.trx_datetime)}</Table.Td>
                                            <Table.Td>{row.reference}</Table.Td>
                                            {(isIncomingReport || isUsageReport) && <Table.Td>{poLink(row)}</Table.Td>}
                                            <Table.Td>{row.transaction_code}</Table.Td>
                                            {(isIncomingReport || isUsageReport) && <Table.Td>{formatDate(row.po_date)}</Table.Td>}
                                            <Table.Td>{row.item_name}</Table.Td>
                                            <Table.Td>{row.category_name}</Table.Td>
                                            <Table.Td>{row.sku}</Table.Td>
                                            <Table.Td>{row.uom_name}</Table.Td>
                                            <Table.Td>{Number(row.unit_price).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                                            <Table.Td>{Number(row.qty).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</Table.Td>
                                            <Table.Td>{Number(row.value).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</Table.Td>
                                            {(isIncomingReport || isUsageReport) && (
                                                <>
                                                    <Table.Td>{row.status}</Table.Td>
                                                    <Table.Td>{vendorLink(row)}</Table.Td>
                                                    <Table.Td>{row.facility_name}</Table.Td>
                                                    <Table.Td>{row.facility_reference_no}</Table.Td>
                                                </>
                                            )}
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
