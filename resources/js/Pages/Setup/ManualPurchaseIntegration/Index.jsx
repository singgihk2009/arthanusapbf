import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const inputClassName = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950';
const buttonClassName = 'rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50';

export default function Index({ batches, purposes }) {
    const { flash } = usePage().props;
    const [committingId, setCommittingId] = useState(null);
    const [deletingId, setDeletingId] = useState(null);
    const [repairBatch, setRepairBatch] = useState(null);
    const uploadSectionRef = useRef(null);
    const fileInputRef = useRef(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        source_system: '',
        source_branch_code: '',
        import_purpose: 'BRANCH_INTEGRATION',
        file: null,
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        const submitRoute = repairBatch
            ? route('apps.setup.manual-purchase-integration.imports.retry', repairBatch.id)
            : route('apps.setup.manual-purchase-integration.imports.store');

        post(submitRoute, {
            forceFormData: true,
            onSuccess: () => {
                reset('file');
                setRepairBatch(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const startRepair = (batch) => {
        setRepairBatch(batch);
        setData({
            source_system: batch.source_system ?? '',
            source_branch_code: batch.source_branch_code ?? '',
            import_purpose: batch.import_purpose ?? 'BRANCH_INTEGRATION',
            file: null,
        });
        if (fileInputRef.current) fileInputRef.current.value = '';
        uploadSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const cancelRepair = () => {
        setRepairBatch(null);
        reset('file');
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const deleteBatch = (batch) => {
        if (!window.confirm(`Hapus batch ${batch.batch_no}? Data preview dan error validasi akan dihapus permanen.`)) return;
        setDeletingId(batch.id);
        window.axios.post(route('apps.setup.manual-purchase-integration.imports.discard', batch.id))
            .then(() => window.location.reload())
            .catch((error) => window.alert(error.response?.data?.message ?? 'Hapus batch gagal.'))
            .finally(() => setDeletingId(null));
    };

    const commitBatch = (batchId) => {
        if (!window.confirm('Commit batch ini? Proses akan membuat PO, receiving_entries, stock movement RCV_IN, vendor invoice, payment, dan AP ledger tanpa jurnal akunting.')) return;
        setCommittingId(batchId);
        window.axios.post(route('apps.setup.manual-purchase-integration.imports.commit', batchId))
            .then(() => window.location.reload())
            .catch((error) => window.alert(error.response?.data?.message ?? 'Commit batch gagal.'))
            .finally(() => setCommittingId(null));
    };

    return (
        <>
            <Head title="Manual Purchase Integration" />
            <div className="space-y-5 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Manual Purchase Integration</h1>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Import siklus historis/manual PO → Receiving → Vendor Invoice → Payment dari cabang atau sistem eksternal.
                        Receiving masuk ke receiving_entries dan stock ledger RCV_IN; AP masuk ke ledger vendor yang sama dengan transaksi regular tanpa jurnal akunting.
                    </p>
                </div>

                {flash?.success && <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{flash.success}</div>}
                {flash?.error && <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{flash.error}</div>}

                <div ref={uploadSectionRef} className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-900 dark:bg-gray-950">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-base font-semibold">Download Template & Upload Import</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Upload akan membuat batch validasi terlebih dahulu. Commit hanya aktif jika tidak ada blocking error.</p>
                            {repairBatch && (
                                <p className="mt-1 text-sm font-medium text-amber-700">
                                    Mode perbaiki data untuk batch {repairBatch.batch_no}. Upload file yang sudah diperbaiki untuk mengganti hasil validasi batch ini.
                                </p>
                            )}
                        </div>
                        <a href={route('apps.setup.manual-purchase-integration.template.excel')} className="rounded-lg border border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30">
                            Download Template Excel
                        </a>
                    </div>

                    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-3 md:grid-cols-5">
                        <input value={data.source_system} onChange={(e) => setData('source_system', e.target.value)} className={inputClassName} placeholder="Source system, contoh: CABANG-LEGACY" />
                        <input value={data.source_branch_code} onChange={(e) => setData('source_branch_code', e.target.value)} className={inputClassName} placeholder="Branch code, contoh: BDG01" />
                        <select value={data.import_purpose} onChange={(e) => setData('import_purpose', e.target.value)} className={inputClassName}>
                            {purposes.map((purpose) => <option key={purpose} value={purpose}>{purpose}</option>)}
                        </select>
                        <input ref={fileInputRef} type="file" accept=".xlsx" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} className={inputClassName} />
                        <div className="flex gap-2">
                            <button type="submit" disabled={processing} className={buttonClassName}>{processing ? 'Uploading...' : (repairBatch ? 'Upload Perbaikan' : 'Upload & Validate')}</button>
                            {repairBatch && <button type="button" onClick={cancelRepair} disabled={processing} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50">Batal</button>}
                        </div>
                    </form>
                    <div className="mt-2 space-y-1 text-xs text-red-600">
                        {Object.entries(errors).map(([key, value]) => <p key={key}>{value}</p>)}
                    </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-900 dark:bg-gray-950">
                    <div className="mb-4">
                        <h2 className="text-base font-semibold">Import Batch History</h2>
                        <p className="text-sm text-gray-600 dark:text-gray-400">Audit batch untuk initial history, branch integration, manual backfill, dan correction.</p>
                    </div>
                    <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-900">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-left dark:bg-gray-900">
                                <tr>
                                    <th className="px-3 py-2">Batch</th>
                                    <th className="px-3 py-2">Source</th>
                                    <th className="px-3 py-2">Purpose</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Rows</th>
                                    <th className="px-3 py-2">Errors</th>
                                    <th className="px-3 py-2">Created</th>
                                    <th className="px-3 py-2 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {batches.data.length === 0 && <tr><td colSpan="8" className="px-3 py-6 text-center text-gray-500">Belum ada batch import.</td></tr>}
                                {batches.data.map((batch) => {
                                    const totalRows = Object.values(batch.summary || {}).reduce((sum, item) => sum + Number(item?.rows || 0), 0);
                                    return (
                                        <tr key={batch.id} className="border-t border-gray-100 dark:border-gray-900">
                                            <td className="px-3 py-2 font-medium">{batch.batch_no}<div className="text-xs font-normal text-gray-500">{batch.file_name}</div></td>
                                            <td className="px-3 py-2">{batch.source_system}<div className="text-xs text-gray-500">{batch.source_branch_code}</div></td>
                                            <td className="px-3 py-2">{batch.import_purpose}</td>
                                            <td className="px-3 py-2"><span className="rounded border border-gray-300 px-2 py-1 text-xs">{batch.status}</span></td>
                                            <td className="px-3 py-2">{totalRows}</td>
                                            <td className="px-3 py-2 text-red-600">{batch.errors?.length ?? 0}{batch.errors?.[0] && <div className="max-w-sm truncate text-xs">{batch.errors[0].sheet} row {batch.errors[0].row}: {batch.errors[0].message}</div>}</td>
                                            <td className="px-3 py-2">{batch.created_at}</td>
                                            <td className="px-3 py-2">
                                                <div className="flex flex-wrap justify-center gap-2">
                                                    <button type="button" onClick={() => window.open(route('apps.setup.manual-purchase-integration.imports.show', batch.id), '_blank')} className="rounded border border-gray-300 px-2 py-1 text-xs">JSON Detail</button>
                                                    {batch.status === 'validated' && <button type="button" disabled={committingId === batch.id} onClick={() => commitBatch(batch.id)} className="rounded border border-emerald-500 px-2 py-1 text-xs font-medium text-emerald-700">{committingId === batch.id ? 'Committing...' : 'Commit'}</button>}
                                                    {batch.status === 'validation_failed' && (
                                                        <>
                                                            <button type="button" disabled={processing || deletingId === batch.id} onClick={() => startRepair(batch)} className="rounded border border-amber-500 px-2 py-1 text-xs font-medium text-amber-700">Perbaiki Data</button>
                                                            <button type="button" disabled={deletingId === batch.id} onClick={() => deleteBatch(batch)} className="rounded border border-red-500 px-2 py-1 text-xs font-medium text-red-700">{deletingId === batch.id ? 'Menghapus...' : 'Hapus Data'}</button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {batches.links?.length > 0 && <div className="mt-3 flex flex-wrap gap-2 text-sm">
                        {batches.links.map((link) => link.url
                            ? <Link key={link.label} href={link.url} className="rounded border border-gray-300 px-3 py-1" dangerouslySetInnerHTML={{ __html: link.label }} />
                            : <span key={link.label} className="rounded border border-gray-200 px-3 py-1 text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                    </div>}
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
