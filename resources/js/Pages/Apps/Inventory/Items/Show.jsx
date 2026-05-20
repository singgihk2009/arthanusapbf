import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';

const tabs = [
    ['overview', 'Overview'],
    ['item', 'Item'],
    ['barang-masuk', 'Barang Masuk'],
    ['barang-keluar', 'Barang Keluar'],
    ['dokumen', 'Dokumen'],
    ['ledger', 'Ledger'],
];

const incomingColumns = [
    { key: 'number', label: 'No' },
    { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
    { key: 'trx_datetime', label: 'Tanggal', sortKey: 'trx_datetime' },
    { key: 'reference', label: 'Referensi' },
    { key: 'gr_number', label: 'Nomor PO' },
    { key: 'transaction_code', label: 'Kode Transaksi' },
    { key: 'po_date', label: 'Tanggal PO' },
    { key: 'item_name', label: 'Item', sortKey: 'item' },
    { key: 'category_name', label: 'Kategori', sortKey: 'category' },
    { key: 'sku', label: 'SKU' },
    { key: 'uom_name', label: 'UoM' },
    { key: 'unit_price', label: 'Unit Price', sortKey: 'unit_price' },
    { key: 'qty', label: 'Qty Masuk', sortKey: 'qty' },
    { key: 'value', label: 'Value', sortKey: 'value' },
    { key: 'status', label: 'Status', sortKey: 'status' },
    { key: 'vendor_name', label: 'Vendor', sortKey: 'vendor' },
    { key: 'facility_name', label: 'Fasilitas' },
    { key: 'facility_reference_no', label: 'No Fasilitas' },
];


const outgoingColumns = [
    { key: 'number', label: 'No' },
    { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
    { key: 'trx_datetime', label: 'Tanggal', sortKey: 'trx_datetime' },
    { key: 'reference', label: 'Referensi' },
    { key: 'gr_number', label: 'Nomor PO' },
    { key: 'transaction_code', label: 'Kode Transaksi' },
    { key: 'po_date', label: 'Tanggal PO' },
    { key: 'item_name', label: 'Item', sortKey: 'item' },
    { key: 'category_name', label: 'Kategori', sortKey: 'category' },
    { key: 'sku', label: 'SKU' },
    { key: 'uom_name', label: 'UoM' },
    { key: 'unit_price', label: 'Unit Price', sortKey: 'unit_price' },
    { key: 'qty', label: 'Qty Keluar', sortKey: 'qty' },
    { key: 'value', label: 'Value', sortKey: 'value' },
    { key: 'status', label: 'Status', sortKey: 'status' },
    { key: 'vendor_name', label: 'Vendor', sortKey: 'vendor' },
    { key: 'facility_name', label: 'Fasilitas' },
    { key: 'facility_reference_no', label: 'No Fasilitas' },
];


const ledgerColumns = [
    { key: 'number', label: 'No' },
    { key: 'warehouse_name', label: 'Warehouse', sortKey: 'warehouse' },
    { key: 'trx_datetime', label: 'Tanggal', sortKey: 'trx_datetime' },
    { key: 'reference', label: 'Referensi' },
    { key: 'item_name', label: 'Item' },
    { key: 'sku', label: 'SKU' },
    { key: 'qty', label: 'Qty Movement', sortKey: 'qty' },
    { key: 'running_balance', label: 'Saldo Berjalan', sortKey: 'running_balance' },
    { key: 'unit_price', label: 'Unit Cost', sortKey: 'unit_price' },
    { key: 'value', label: 'Value Movement', sortKey: 'value' },
];

const formatDate = (value) => {
    if (!value || value === '-') return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

export default function Show() {
    const { item, currentTab, summary, incomingFilters, incomingReportData, outgoingFilters, outgoingReportData, ledgerFilters, ledgerReportData, warehouses = [], categories = [], facilitySchemes = [] } = usePage().props;

    const updateIncomingFilters = (nextFilters) => {
        router.get(`/apps/inventory/item-cards/${item.id}`, { tab: 'barang-masuk', ...incomingFilters, ...nextFilters }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const updateOutgoingFilters = (nextFilters) => {
        router.get(`/apps/inventory/item-cards/${item.id}`, { tab: 'barang-keluar', ...outgoingFilters, ...nextFilters }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const updateLedgerFilters = (nextFilters) => {
        router.get(`/apps/inventory/item-cards/${item.id}`, { tab: 'ledger', ...ledgerFilters, ...nextFilters }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title={`Inventory Card - ${item.name}`} />
            <div className="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                <h1 className="text-lg font-semibold">Inventory Card: {item.name}</h1>
                <p className="text-sm text-gray-500">SKU: {item.sku} · UOM: {item.base_uom?.code ?? '-'} · Kategori: {item.category?.name ?? '-'}</p>
            </div>

            <div className="mb-4 flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950">
                {tabs.map(([key, label]) => (
                    <button key={key} onClick={() => router.get(`/apps/inventory/item-cards/${item.id}`, { tab: key }, { preserveState: true, preserveScroll: true })} className={`rounded px-3 py-1 text-sm ${currentTab === key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-100'}`}>{label}</button>
                ))}
            </div>

            <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                {currentTab === 'overview' && <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                    <Card label="On Hand" value={summary.on_hand_total} />
                    <Card label="Warehouse" value={summary.warehouse_count} />
                    <Card label="Barang Masuk" value={summary.incoming_total} />
                    <Card label="Barang Keluar" value={summary.outgoing_total} />
                    <Card label="Total Ledger" value={summary.ledger_rows} />
                </div>}

                {currentTab === 'barang-masuk' && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-7">
                            <select value={incomingFilters.warehouse_id ?? ''} onChange={(e) => updateIncomingFilters({ warehouse_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Semua Gudang</option>
                                {warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                            </select>
                            <select value={incomingFilters.category_id ?? ''} onChange={(e) => updateIncomingFilters({ category_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Semua Kategori</option>
                                {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                            </select>
                            <select value={incomingFilters.status ?? 'all'} onChange={(e) => updateIncomingFilters({ status: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="all">Semua Status</option><option value="posted">Posted</option><option value="unposted">Belum Posted</option>
                            </select>
                            <select value={incomingFilters.facility_scheme_id ?? ''} onChange={(e) => updateIncomingFilters({ facility_scheme_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Semua Fasilitas</option>
                                {facilitySchemes.map((facility) => <option key={facility.id} value={facility.id}>{facility.code} - {facility.name}</option>)}
                            </select>
                            <input type="date" value={incomingFilters.start_date ?? ''} onChange={(e) => updateIncomingFilters({ start_date: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" />
                            <input type="date" value={incomingFilters.end_date ?? ''} onChange={(e) => updateIncomingFilters({ end_date: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" />
                            <input type="text" value={incomingFilters.search ?? ''} onChange={(e) => updateIncomingFilters({ search: e.target.value, page: 1 })} placeholder="Cari warehouse/item/sku" className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" />
                            <select value={incomingFilters.per_page ?? 15} onChange={(e) => updateIncomingFilters({ per_page: Number(e.target.value), page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                {[15, 50, 100].map((size) => <option key={size} value={size}>Show {size}</option>)}
                            </select>
                        </div>
                        <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b">{incomingColumns.map((column) => <th key={column.key} className="px-2 py-2 text-left">{column.sortKey ? <button type="button" onClick={() => updateIncomingFilters({ sort_by: column.sortKey, sort_dir: incomingFilters.sort_by === column.sortKey && incomingFilters.sort_dir === 'asc' ? 'desc' : 'asc', page: 1 })}>{column.label}{incomingFilters.sort_by === column.sortKey ? (incomingFilters.sort_dir === 'asc' ? '↑' : '↓') : ''}</button> : column.label}</th>)}</tr></thead>
                            <tbody>{incomingReportData?.rows?.length ? incomingReportData.rows.map((row, index) => <tr key={`${row.reference ?? row.sku}-${index}`} className="border-b"><td className="px-2 py-2">{(incomingReportData.pagination?.from ?? 1) + index}</td><td className="px-2 py-2">{row.warehouse_name}</td><td className="px-2 py-2">{formatDate(row.trx_datetime)}</td><td className="px-2 py-2">{row.reference}</td><td className="px-2 py-2">{row.gr_number}</td><td className="px-2 py-2">{row.transaction_code}</td><td className="px-2 py-2">{formatDate(row.po_date)}</td><td className="px-2 py-2">{row.item_name}</td><td className="px-2 py-2">{row.category_name}</td><td className="px-2 py-2">{row.sku}</td><td className="px-2 py-2">{row.uom_name}</td><td className="px-2 py-2">{Number(row.unit_price).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</td><td className="px-2 py-2">{Number(row.qty).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</td><td className="px-2 py-2">{Number(row.value).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</td><td className="px-2 py-2">{row.status}</td><td className="px-2 py-2">{row.vendor_name}</td><td className="px-2 py-2">{row.facility_name}</td><td className="px-2 py-2">{row.facility_reference_no}</td></tr>) : <tr><td colSpan={incomingColumns.length} className="px-2 py-4 text-center text-gray-500">Tidak ada data report.</td></tr>}</tbody></table></div>
                        {incomingReportData?.pagination && <div className="flex items-center justify-between text-sm"><div>Menampilkan {incomingReportData.pagination.from ?? 0} - {incomingReportData.pagination.to ?? 0} dari {incomingReportData.pagination.total} data</div><div className="flex items-center gap-2"><button type="button" onClick={() => updateIncomingFilters({ page: incomingReportData.pagination.current_page - 1 })} disabled={incomingReportData.pagination.current_page <= 1} className="rounded border px-3 py-1 disabled:opacity-40">Prev</button><span>Page {incomingReportData.pagination.current_page} / {incomingReportData.pagination.last_page}</span><button type="button" onClick={() => updateIncomingFilters({ page: incomingReportData.pagination.current_page + 1 })} disabled={incomingReportData.pagination.current_page >= incomingReportData.pagination.last_page} className="rounded border px-3 py-1 disabled:opacity-40">Next</button></div></div>}
                    </div>
                )}

                {currentTab === 'barang-keluar' && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-7">
                            <select value={outgoingFilters.warehouse_id ?? ''} onChange={(e) => updateOutgoingFilters({ warehouse_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">Semua Gudang</option>{warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}</select>
                            <select value={outgoingFilters.category_id ?? ''} onChange={(e) => updateOutgoingFilters({ category_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">Semua Kategori</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select>
                            <select value={outgoingFilters.status ?? 'all'} onChange={(e) => updateOutgoingFilters({ status: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="all">Semua Status</option><option value="posted">Posted</option><option value="unposted">Belum Posted</option></select>
                            <select value={outgoingFilters.facility_scheme_id ?? ''} onChange={(e) => updateOutgoingFilters({ facility_scheme_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">Semua Fasilitas</option>{facilitySchemes.map((facility) => <option key={facility.id} value={facility.id}>{facility.code} - {facility.name}</option>)}</select>
                            <input type="date" value={outgoingFilters.start_date ?? ''} onChange={(e) => updateOutgoingFilters({ start_date: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" />
                            <input type="date" value={outgoingFilters.end_date ?? ''} onChange={(e) => updateOutgoingFilters({ end_date: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" />
                            <input type="text" value={outgoingFilters.search ?? ''} onChange={(e) => updateOutgoingFilters({ search: e.target.value, page: 1 })} placeholder="Cari warehouse/item/sku" className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" />
                            <select value={outgoingFilters.per_page ?? 15} onChange={(e) => updateOutgoingFilters({ per_page: Number(e.target.value), page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">{[15, 50, 100].map((size) => <option key={size} value={size}>Show {size}</option>)}</select>
                        </div>
                        <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b">{outgoingColumns.map((column) => <th key={column.key} className="px-2 py-2 text-left">{column.sortKey ? <button type="button" onClick={() => updateOutgoingFilters({ sort_by: column.sortKey, sort_dir: outgoingFilters.sort_by === column.sortKey && outgoingFilters.sort_dir === 'asc' ? 'desc' : 'asc', page: 1 })}>{column.label}{outgoingFilters.sort_by === column.sortKey ? (outgoingFilters.sort_dir === 'asc' ? '↑' : '↓') : ''}</button> : column.label}</th>)}</tr></thead>
                        <tbody>{outgoingReportData?.rows?.length ? outgoingReportData.rows.map((row, index) => <tr key={`${row.reference ?? row.sku}-${index}`} className="border-b"><td className="px-2 py-2">{(outgoingReportData.pagination?.from ?? 1) + index}</td><td className="px-2 py-2">{row.warehouse_name}</td><td className="px-2 py-2">{formatDate(row.trx_datetime)}</td><td className="px-2 py-2">{row.reference}</td><td className="px-2 py-2">{row.gr_number}</td><td className="px-2 py-2">{row.transaction_code}</td><td className="px-2 py-2">{formatDate(row.po_date)}</td><td className="px-2 py-2">{row.item_name}</td><td className="px-2 py-2">{row.category_name}</td><td className="px-2 py-2">{row.sku}</td><td className="px-2 py-2">{row.uom_name}</td><td className="px-2 py-2">{Number(row.unit_price).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</td><td className="px-2 py-2">{Number(row.qty).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</td><td className="px-2 py-2">{Number(row.value).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</td><td className="px-2 py-2">{row.status}</td><td className="px-2 py-2">{row.vendor_name}</td><td className="px-2 py-2">{row.facility_name}</td><td className="px-2 py-2">{row.facility_reference_no}</td></tr>) : <tr><td colSpan={outgoingColumns.length} className="px-2 py-4 text-center text-gray-500">Tidak ada data report.</td></tr>}</tbody></table></div>
                        {outgoingReportData?.pagination && <div className="flex items-center justify-between text-sm"><div>Menampilkan {outgoingReportData.pagination.from ?? 0} - {outgoingReportData.pagination.to ?? 0} dari {outgoingReportData.pagination.total} data</div><div className="flex items-center gap-2"><button type="button" onClick={() => updateOutgoingFilters({ page: outgoingReportData.pagination.current_page - 1 })} disabled={outgoingReportData.pagination.current_page <= 1} className="rounded border px-3 py-1 disabled:opacity-40">Prev</button><span>Page {outgoingReportData.pagination.current_page} / {outgoingReportData.pagination.last_page}</span><button type="button" onClick={() => updateOutgoingFilters({ page: outgoingReportData.pagination.current_page + 1 })} disabled={outgoingReportData.pagination.current_page >= outgoingReportData.pagination.last_page} className="rounded border px-3 py-1 disabled:opacity-40">Next</button></div></div>}
                    </div>
                )}

                {currentTab !== 'overview' && currentTab !== 'ledger' && currentTab !== 'barang-masuk' && currentTab !== 'barang-keluar' && <p className="text-sm text-gray-500">Tab {tabs.find(([k]) => k === currentTab)?.[1]} siap dipakai untuk pengembangan data 360 view.</p>}
                {currentTab === 'ledger' && (<div className="space-y-4"><div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-6"><select value={ledgerFilters.warehouse_id ?? ''} onChange={(e) => updateLedgerFilters({ warehouse_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">Semua Gudang</option>{warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}</select><select value={ledgerFilters.facility_scheme_id ?? ''} onChange={(e) => updateLedgerFilters({ facility_scheme_id: e.target.value || null, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">Semua Fasilitas</option>{facilitySchemes.map((facility) => <option key={facility.id} value={facility.id}>{facility.code} - {facility.name}</option>)}</select><input type="date" value={ledgerFilters.start_date ?? ''} onChange={(e) => updateLedgerFilters({ start_date: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" /><input type="date" value={ledgerFilters.end_date ?? ''} onChange={(e) => updateLedgerFilters({ end_date: e.target.value, page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" /><input type="text" value={ledgerFilters.search ?? ''} onChange={(e) => updateLedgerFilters({ search: e.target.value, page: 1 })} placeholder="Cari warehouse/item/sku" className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" /><select value={ledgerFilters.per_page ?? 15} onChange={(e) => updateLedgerFilters({ per_page: Number(e.target.value), page: 1 })} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">{[15, 50, 100].map((size) => <option key={size} value={size}>Show {size}</option>)}</select></div><div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b">{ledgerColumns.map((column) => <th key={column.key} className="px-2 py-2 text-left">{column.sortKey ? <button type="button" onClick={() => updateLedgerFilters({ sort_by: column.sortKey, sort_dir: ledgerFilters.sort_by === column.sortKey && ledgerFilters.sort_dir === 'asc' ? 'desc' : 'asc', page: 1 })}>{column.label}{ledgerFilters.sort_by === column.sortKey ? (ledgerFilters.sort_dir === 'asc' ? '↑' : '↓') : ''}</button> : column.label}</th>)}</tr></thead><tbody>{ledgerReportData?.rows?.length ? ledgerReportData.rows.map((row, index) => <tr key={`${row.reference ?? row.sku}-${index}`} className="border-b"><td className="px-2 py-2">{(ledgerReportData.pagination?.from ?? 1) + index}</td><td className="px-2 py-2">{row.warehouse_name}</td><td className="px-2 py-2">{formatDate(row.trx_datetime)}</td><td className="px-2 py-2">{row.reference}</td><td className="px-2 py-2">{row.item_name}</td><td className="px-2 py-2">{row.sku}</td><td className="px-2 py-2">{Number(row.qty).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</td><td className="px-2 py-2">{Number(row.running_balance).toLocaleString('id-ID', { maximumFractionDigits: 6 })}</td><td className="px-2 py-2">{Number(row.unit_price).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</td><td className="px-2 py-2">{Number(row.value).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })}</td></tr>) : <tr><td colSpan={ledgerColumns.length} className="px-2 py-4 text-center text-gray-500">Tidak ada data report.</td></tr>}</tbody></table></div>{ledgerReportData?.pagination && <div className="flex items-center justify-between text-sm"><div>Menampilkan {ledgerReportData.pagination.from ?? 0} - {ledgerReportData.pagination.to ?? 0} dari {ledgerReportData.pagination.total} data</div><div className="flex items-center gap-2"><button type="button" onClick={() => updateLedgerFilters({ page: ledgerReportData.pagination.current_page - 1 })} disabled={ledgerReportData.pagination.current_page <= 1} className="rounded border px-3 py-1 disabled:opacity-40">Prev</button><span>Page {ledgerReportData.pagination.current_page} / {ledgerReportData.pagination.last_page}</span><button type="button" onClick={() => updateLedgerFilters({ page: ledgerReportData.pagination.current_page + 1 })} disabled={ledgerReportData.pagination.current_page >= ledgerReportData.pagination.last_page} className="rounded border px-3 py-1 disabled:opacity-40">Next</button></div></div>}</div>)}
            </div>
        </>
    );
}

function Card({ label, value }) {
    return <div className="rounded border border-gray-200 p-3 dark:border-gray-800"><p className="text-xs text-gray-500">{label}</p><p className="text-lg font-semibold">{Number(value ?? 0).toLocaleString('id-ID')}</p></div>;
}

Show.layout = (page) => <AppLayout children={page} />;
