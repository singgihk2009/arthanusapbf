import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ goodsReceipts }) {
    return (
        <>
            <Head title="Goods Receipt" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Goods Receipt</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">List dokumen receiving dari purchase order.</p>
                        </div>
                        <div className="flex gap-2">
                            <Link href={route('apps.inbound.receiving.create')} className="rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white dark:bg-gray-100 dark:text-gray-900">Add New Receive</Link>
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">No</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Number</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Tanggal</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Warehouse</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Kode Transaksi</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Vendor</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Status</th>
                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Status Tagihan</th>
                                    <th className="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Total</th>
                                    <th className="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {goodsReceipts.data.length === 0 && (
                                    <tr>
                                        <td colSpan={10} className="px-3 py-4 text-center text-gray-500">Belum ada data goods receipt.</td>
                                    </tr>
                                )}
                                {goodsReceipts.data.map((entry, idx) => (
                                    <tr key={entry.id} className="text-gray-800 dark:text-gray-100">
                                        <td className="px-3 py-2">{goodsReceipts.from ? goodsReceipts.from + idx : idx + 1}</td>
                                        <td className="px-3 py-2">{entry.number}</td>
                                        <td className="px-3 py-2">{entry.transaction_date}</td>
                                        <td className="px-3 py-2">{entry.warehouse_label}</td>
                                        <td className="px-3 py-2">{entry.transaction_code}</td>
                                        <td className="px-3 py-2">{entry.vendor_name || '-'}</td>
                                        <td className="px-3 py-2"><span className="rounded border border-gray-300 px-2 py-1 text-xs">{entry.status || 'DRAFT'}</span></td>
                                        <td className="px-3 py-2"><span className="rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs text-indigo-700">{entry.invoice_status || 'Belum Ditagih'}</span></td>
                                        <td className="px-3 py-2 text-right">{Number(entry.total_value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-2 text-center">
                                            <Link href={route('apps.inbound.receiving.edit', entry.id)} className="rounded border border-gray-300 px-2 py-1 text-xs">View</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
