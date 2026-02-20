import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const inputClassName = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm';

export default function Index() {
    const { entries, flash, warehouses } = usePage().props;
    const [processingId, setProcessingId] = useState(null);
    const [warehouseId, setWarehouseId] = useState('');
    const [documentDate, setDocumentDate] = useState(new Date().toISOString().slice(0, 10));
    const [type, setType] = useState('FULL');
    const [notes, setNotes] = useState('Import from stock opname excel');
    const [file, setFile] = useState(null);
    const [importError, setImportError] = useState('');

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

    const downloadTemplate = () => {
        if (!warehouseId) {
            window.alert('Pilih warehouse terlebih dahulu.');
            return;
        }

        window.open(`${route('apps.outbound.stock-opname.template.excel')}?warehouse_id=${warehouseId}`, '_blank');
    };

    const handleImport = async (event) => {
        event.preventDefault();
        setImportError('');

        if (!warehouseId || !file) {
            setImportError('Warehouse dan file excel wajib diisi.');
            return;
        }

        setProcessingId('import');
        try {
            const formData = new FormData();
            formData.append('warehouse_id', warehouseId);
            formData.append('document_date', documentDate);
            formData.append('type', type);
            formData.append('notes', notes);
            formData.append('file', file);

            await window.axios.post(route('apps.outbound.stock-opname.import.excel'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            window.location.reload();
        } catch (error) {
            const payload = error.response?.data;
            if (payload?.errors?.length) {
                setImportError(payload.errors.map((item) => `Baris ${item.row}: ${item.message}`).join(' | '));
            } else {
                setImportError(payload?.message ?? 'Import gagal diproses.');
            }
        } finally { setProcessingId(null); }
    };

    return (
        <>
            <Head title="Stock Opname" />
            <div className="space-y-4">
                {flash?.success && <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{flash.success}</div>}
                <div className="rounded-lg border border-gray-200 bg-white p-4">
                    <h3 className="text-sm font-semibold">Template & Import Stock Opname (Excel)</h3>
                    <p className="mb-3 text-xs text-gray-600">Download template formal per warehouse, isi qty hasil hitung fisik, lalu upload kembali agar sistem otomatis adjustment.</p>
                    <form onSubmit={handleImport} className="grid grid-cols-1 gap-3 md:grid-cols-5">
                        <select value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} className={inputClassName}>
                            <option value="">Pilih Warehouse</option>
                            {warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                        </select>
                        <input type="date" value={documentDate} onChange={(e) => setDocumentDate(e.target.value)} className={inputClassName} />
                        <select value={type} onChange={(e) => setType(e.target.value)} className={inputClassName}><option value="FULL">FULL</option><option value="CYCLE">CYCLE</option></select>
                        <input value={notes} onChange={(e) => setNotes(e.target.value)} className={inputClassName} placeholder="Catatan import" />
                        <input type="file" accept=".xlsx,.csv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className={inputClassName} />
                        <button type="button" onClick={downloadTemplate} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">Download Template Excel</button>
                        <button type="submit" disabled={processingId === 'import'} className="rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white">{processingId === 'import' ? 'Mengupload...' : 'Upload & Auto Adjustment'}</button>
                    </form>
                    {importError && <p className="mt-2 text-xs text-red-600">{importError}</p>}
                </div>

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
