import { Link } from '@inertiajs/react';

export default function ReceivingsTab({ data }) {
    const receivings = data?.receivings;
    const rows = receivings?.data || [];

    return (
        <div className="space-y-3">
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
                            <th className="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Total</th>
                            <th className="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={9} className="px-3 py-4 text-center text-gray-500">Belum ada data receiving vendor terkait.</td>
                            </tr>
                        )}
                        {rows.map((entry, idx) => (
                            <tr key={entry.id} className="text-gray-800 dark:text-gray-100">
                                <td className="px-3 py-2">{receivings?.from ? receivings.from + idx : idx + 1}</td>
                                <td className="px-3 py-2">{entry.number || '-'}</td>
                                <td className="px-3 py-2">{entry.transaction_date || '-'}</td>
                                <td className="px-3 py-2">{entry.warehouse_label || '-'}</td>
                                <td className="px-3 py-2">{entry.transaction_code || '-'}</td>
                                <td className="px-3 py-2">{entry.vendor_name || '-'}</td>
                                <td className="px-3 py-2"><span className="rounded border border-gray-300 px-2 py-1 text-xs">{entry.status || 'DRAFT'}</span></td>
                                <td className="px-3 py-2 text-right">{Number(entry.total_value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td className="px-3 py-2 text-center">
                                    <Link href={route('apps.inbound.receiving.edit', entry.id)} className="rounded border border-gray-300 px-2 py-1 text-xs">Detail</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
