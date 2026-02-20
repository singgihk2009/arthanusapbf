import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index() {
    const { entries, flash } = usePage().props;
    const [processingId, setProcessingId] = useState(null);

    const handleDelete = async (id) => {
        if (!window.confirm('Yakin hapus stock opname ini?')) return;
        setProcessingId(id);
        try {
            await window.axios.delete(route('apps.outbound.stock-opname.destroy', id));
            window.location.reload();
        } finally { setProcessingId(null); }
    };

    const handlePost = async (id) => {
        if (!window.confirm('Posting stock opname? Variance akan membuat adjustment otomatis.')) return;
        setProcessingId(id);
        try {
            await window.axios.post(route('apps.inventory.posting.opname', id));
            window.location.reload();
        } finally { setProcessingId(null); }
    };

    return (
        <>
            <Head title="Stock Opname" />
            <div className="space-y-4">
                {flash?.success && <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{flash.success}</div>}
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <div className="mb-4 flex items-center justify-between">
                        <div><h2 className="text-base font-semibold">Stock Opname</h2><p className="text-sm text-gray-600">Pencatatan hitung fisik stok gudang.</p></div>
                        <Link href={route('apps.outbound.stock-opname.create')} className="rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white">Add Opname</Link>
                    </div>
                    <div className="overflow-x-auto rounded border border-gray-200">
                        <table className="min-w-full text-sm"><thead className="bg-gray-50"><tr><th className="px-3 py-2 text-left">No</th><th className="px-3 py-2 text-left">Number</th><th className="px-3 py-2 text-left">Tanggal</th><th className="px-3 py-2 text-left">Warehouse</th><th className="px-3 py-2 text-left">Type</th><th className="px-3 py-2 text-left">Status</th><th className="px-3 py-2 text-center">Aksi</th></tr></thead><tbody>
                            {entries.data.length === 0 && <tr><td colSpan={7} className="px-3 py-4 text-center text-gray-500">Belum ada data stock opname.</td></tr>}
                            {entries.data.map((entry, idx) => <tr key={entry.id}><td className="px-3 py-2">{entries.from ? entries.from + idx : idx + 1}</td><td className="px-3 py-2">{entry.number}</td><td className="px-3 py-2">{entry.document_date}</td><td className="px-3 py-2">{entry.warehouse_label}</td><td className="px-3 py-2">{entry.type}</td><td className="px-3 py-2"><span className="rounded border border-gray-300 px-2 py-1 text-xs">{entry.status}</span></td><td className="px-3 py-2"><div className="flex justify-center gap-2"><Link href={route('apps.outbound.stock-opname.edit', entry.id)} className="rounded border border-gray-300 px-2 py-1 text-xs">Edit</Link>{entry.status !== 'POSTED' && <button type="button" onClick={() => handlePost(entry.id)} disabled={processingId === entry.id} className="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700">Post</button>}<button type="button" onClick={() => handleDelete(entry.id)} disabled={processingId === entry.id} className="rounded border border-red-300 px-2 py-1 text-xs text-red-600">Hapus</button></div></td></tr>)}
                        </tbody></table>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
