import { Link } from '@inertiajs/react';

const isDraft = (status) => String(status || '').toLowerCase() === 'draft';

const resolveReceivingAmount = (entry) => Number(entry?.total_value ?? entry?.grand_total ?? entry?.total ?? 0);

const formatAmount = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ReceivingsTab({ data }) {
    const receivings = data?.receivings;
    const rows = receivings?.data || [];

    const extractErrorMessage = (error, fallbackMessage) => {
        return error?.response?.data?.message || fallbackMessage;
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Yakin hapus receiving entry ini?')) {
            return;
        }

        try {
            await window.axios.delete(route('apps.inbound.receiving.destroy', { receivingEntry: id }));
            window.location.reload();
        } catch (error) {
            window.alert(extractErrorMessage(error, 'Gagal menghapus receiving entry.'));
        }
    };

    const handlePost = async (id) => {
        if (!window.confirm('Posting dokumen ini? Stok akan bertambah.')) {
            return;
        }

        try {
            await window.axios.post(route('apps.inventory.posting.receiving', id));
            window.location.reload();
        } catch (error) {
            window.alert(extractErrorMessage(error, 'Gagal posting receiving entry.'));
        }
    };

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
                                <td className="px-3 py-2 text-right">{formatAmount(resolveReceivingAmount(entry))}</td>
                                <td className="px-3 py-2 text-center">
                                    <div className="flex flex-wrap justify-center gap-2">
                                        <Link href={route('apps.inbound.receiving.edit', entry.id)} className="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700">Detail</Link>
                                        {isDraft(entry.status) && <Link href={route('apps.inbound.receiving.edit', entry.id)} className="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700">Edit</Link>}
                                        {isDraft(entry.status) && <button type="button" onClick={() => handlePost(entry.id)} className="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700">Post</button>}
                                        {isDraft(entry.status) && <button type="button" onClick={() => handleDelete(entry.id)} className="rounded border border-red-300 px-2 py-1 text-xs text-red-600">Delete</button>}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
