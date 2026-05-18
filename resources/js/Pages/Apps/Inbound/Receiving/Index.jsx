import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index() {
    const { entries, flash } = usePage().props;
    const [processingId, setProcessingId] = useState(null);
    const isPosted = (status) => String(status || '').toLowerCase() === 'posted';

    const extractErrorMessage = (error, fallbackMessage) => {
        return error?.response?.data?.message || fallbackMessage;
    };


    const handleDelete = async (id) => {
        if (!window.confirm('Yakin hapus receiving entry ini?')) {
            return;
        }

        setProcessingId(id);
        try {
            await window.axios.delete(route('apps.inbound.receiving.destroy', id));
            window.location.reload();
        } catch (error) {
            window.alert(extractErrorMessage(error, 'Gagal menghapus receiving entry.'));
        } finally {
            setProcessingId(null);
        }
    };


    const handlePost = async (id) => {
        if (!window.confirm('Posting dokumen ini? Stok akan bertambah.')) {
            return;
        }

        setProcessingId(id);
        try {
            await window.axios.post(route('apps.inventory.posting.receiving', id));
            window.location.reload();
        } catch (error) {
            window.alert(extractErrorMessage(error, 'Gagal posting receiving entry.'));
        } finally {
            setProcessingId(null);
        }
    };


    const handleUnpost = async (id) => {
        if (!window.confirm('Batalkan posting dokumen ini? Stok akan dikurangi kembali.')) {
            return;
        }

        setProcessingId(id);
        try {
            await window.axios.post(route('apps.inventory.unposting.receiving', id));
            window.location.reload();
        } catch (error) {
            window.alert(extractErrorMessage(error, 'Gagal unpost receiving entry.'));
        } finally {
            setProcessingId(null);
        }
    };

    return (
        <>
            <Head title="Receiving Entry" />

            <div className="space-y-4">
                {flash?.success && <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{flash.success}</div>}

                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Receiving Entry</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">List dokumen receiving barang masuk.</p>
                        </div>
                        <div className="flex gap-2">
                            <a href={route('apps.inbound.receiving.export.excel')} className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900">Export Excel</a>
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
                                    <th className="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Total</th>
                                    <th className="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {entries.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-3 py-4 text-center text-gray-500">Belum ada data receiving.</td>
                                    </tr>
                                )}
                                {entries.data.map((entry, idx) => (
                                    <tr key={entry.id} className="text-gray-800 dark:text-gray-100">
                                        <td className="px-3 py-2">{entries.from ? entries.from + idx : idx + 1}</td>
                                        <td className="px-3 py-2">{entry.number}</td>
                                        <td className="px-3 py-2">{entry.transaction_date}</td>
                                        <td className="px-3 py-2">{entry.warehouse_label}</td>
                                        <td className="px-3 py-2">{entry.transaction_code}</td>
                                        <td className="px-3 py-2">{entry.vendor_name || '-'}</td>
                                        <td className="px-3 py-2"><span className="rounded border border-gray-300 px-2 py-1 text-xs">{entry.status || 'DRAFT'}</span></td>
                                        <td className="px-3 py-2 text-right">{Number(entry.total_value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-center gap-2">
                                                <Link href={route('apps.inbound.receiving.edit', entry.id)} className="rounded border border-gray-300 px-2 py-1 text-xs">Edit</Link>
                                                {!isPosted(entry.status) && <button type="button" onClick={() => handlePost(entry.id)} disabled={processingId === entry.id} className="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 disabled:opacity-50">Post</button>}
                                                {isPosted(entry.status) && <button type="button" onClick={() => handleUnpost(entry.id)} disabled={processingId === entry.id} className="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700 disabled:opacity-50">Unpost</button>}
                                                <button type="button" onClick={() => handleDelete(entry.id)} disabled={processingId === entry.id || isPosted(entry.status)} className="rounded border border-red-300 px-2 py-1 text-xs text-red-600 disabled:opacity-50">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {entries?.links?.length > 0 && (
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <p>
                                Menampilkan {entries.from ?? 0} - {entries.to ?? 0} dari {entries.total ?? 0} data
                            </p>
                            <Pagination links={entries.links} />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
