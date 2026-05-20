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

export default function Show() {
    const { item, currentTab, summary, ledgers } = usePage().props;

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
                {currentTab !== 'overview' && currentTab !== 'ledger' && <p className="text-sm text-gray-500">Tab {tabs.find(([k]) => k === currentTab)?.[1]} siap dipakai untuk pengembangan data 360 view.</p>}
                {currentTab === 'ledger' && <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b"><th className="px-2 py-2 text-left">Tanggal</th><th className="px-2 py-2 text-left">Tipe</th><th className="px-2 py-2 text-left">Gudang</th><th className="px-2 py-2 text-right">Qty</th></tr></thead><tbody>{ledgers.map((row) => <tr key={row.id} className="border-b"><td className="px-2 py-2">{row.trx_datetime ?? '-'}</td><td className="px-2 py-2">{row.trx_type}</td><td className="px-2 py-2">{row.warehouse_name ?? '-'}</td><td className="px-2 py-2 text-right">{Number(row.qty_base ?? 0).toLocaleString('id-ID')}</td></tr>)}</tbody></table></div>}
            </div>
        </>
    );
}

function Card({ label, value }) {
    return <div className="rounded border border-gray-200 p-3 dark:border-gray-800"><p className="text-xs text-gray-500">{label}</p><p className="text-lg font-semibold">{Number(value ?? 0).toLocaleString('id-ID')}</p></div>;
}

Show.layout = (page) => <AppLayout children={page} />;
