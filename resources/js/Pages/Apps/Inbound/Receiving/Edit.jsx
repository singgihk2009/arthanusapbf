import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

const emptyLine = {
    source_item_id: '',
    item_id: '',
    qty: '',
    uom_id: '',
    price: '',
    batch_number: '',
    expired_date: '',
    notes: '',
};
const emptyDocument = { document_type_id: '', title: '', document_number: '', issue_date: '', expiry_date: '', notes: '', file: null };

const inputClassName = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100';
const lineInputClassName = 'rounded border border-gray-300 bg-white px-2 py-1 text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100';
const extractErrorMessage = (error, fallbackMessage) => {
    const responseMessage = error?.response?.data?.message;
    const firstError = Object.values(error?.response?.data?.errors ?? {})[0]?.[0];

    return responseMessage || firstError || fallbackMessage;
};

export default function Edit() {
    const { entry, lines, items, uoms, warehouses, transactionCodes, documentTypes, documents } = usePage().props;
    const [form, setForm] = useState({
        warehouse_id: entry.warehouse_id ? String(entry.warehouse_id) : '',
        transaction_date: entry.transaction_date,
        transaction_code: entry.transaction_code,
        reference: entry.reference,
        vendor_name: entry.vendor_name,
        notes: entry.notes,
        lines: lines.length > 0 ? lines : [{ ...emptyLine }],
        documents: [{ ...emptyDocument }],
    });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);
    const [removingDocumentId, setRemovingDocumentId] = useState(null);

    const defaultUomByItemId = useMemo(() => new Map(items.map((item) => [String(item.id), String(item.base_uom_id ?? '')])), [items]);

    const totalValue = useMemo(
        () => form.lines.reduce((sum, line) => sum + ((Number(line.qty) || 0) * (Number(line.price) || 0)), 0),
        [form.lines],
    );

    const updateHeader = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));

    const updateLine = (index, field, value) => {
        setForm((prev) => {
            const draft = [...prev.lines];
            const updatedLine = { ...draft[index], [field]: value };

            if (field === 'item_id') {
                updatedLine.uom_id = defaultUomByItemId.get(String(value)) ?? '';
            }

            draft[index] = updatedLine;

            return { ...prev, lines: draft };
        });
    };

    const addLine = () => setForm((prev) => ({ ...prev, lines: [...prev.lines, { ...emptyLine }] }));
    const removeLine = (index) => setForm((prev) => ({ ...prev, lines: prev.lines.length === 1 ? [{ ...emptyLine }] : prev.lines.filter((_, idx) => idx !== index) }));

    const submit = async (event) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            const normalizedDocuments = (form.documents || []).filter((doc) => doc?.file);
            const payload = new FormData();
            payload.append('_method', 'PUT');
            payload.append('warehouse_id', form.warehouse_id);
            payload.append('transaction_date', form.transaction_date);
            payload.append('transaction_code', form.transaction_code);
            payload.append('reference', form.reference || '');
            payload.append('vendor_name', form.vendor_name || '');
            payload.append('notes', form.notes || '');
            form.lines.forEach((line, index) => {
                Object.entries(line).forEach(([key, value]) => payload.append(`lines[${index}][${key}]`, value ?? ''));
            });
            normalizedDocuments.forEach((doc, index) => {
                payload.append(`documents[${index}][document_type_id]`, doc.document_type_id || '');
                payload.append(`documents[${index}][title]`, doc.title || '');
                payload.append(`documents[${index}][document_number]`, doc.document_number || '');
                payload.append(`documents[${index}][issue_date]`, doc.issue_date || '');
                payload.append(`documents[${index}][expiry_date]`, doc.expiry_date || '');
                payload.append(`documents[${index}][notes]`, doc.notes || '');
                payload.append(`documents[${index}][file]`, doc.file);
            });
            await window.axios.post(route('apps.inbound.receiving.update', entry.id), payload, {
                headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            window.location.href = route('apps.inbound.receiving.index');
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
                const hasFieldErrors = Object.keys(error.response.data.errors ?? {}).length > 0;
                setMessage({
                    type: 'error',
                    text: hasFieldErrors
                        ? 'Validasi gagal. Cek field yang ditandai.'
                        : extractErrorMessage(error, 'Gagal memperbarui receiving entry.'),
                });
            } else {
                setMessage({ type: 'error', text: extractErrorMessage(error, 'Gagal memperbarui receiving entry.') });
            }
            setLoading(false);
        }
    };

    const removeUploadedDocument = async (documentId) => {
        if (!window.confirm('Hapus dokumen ini?')) {
            return;
        }

        setRemovingDocumentId(documentId);
        setMessage(null);

        try {
            await window.axios.delete(route('apps.document-center.documents.destroy', documentId), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            window.location.reload();
        } catch (error) {
            setMessage({ type: 'error', text: extractErrorMessage(error, 'Gagal menghapus dokumen.') });
            setRemovingDocumentId(null);
        }
    };

    return (
        <>
            <Head title="Edit Receiving Entry" />
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Edit Receiving Entry</h2>
                    <Link href={route('apps.inbound.receiving.index')} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">Kembali</Link>
                </div>

                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Warehouse</label>
                                <select value={form.warehouse_id} onChange={(e) => updateHeader('warehouse_id', e.target.value)} className={inputClassName}>
                                    <option value="">Pilih Warehouse</option>
                                    {warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                                </select>
                            </div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Tanggal</label><input type="date" value={form.transaction_date} onChange={(e) => updateHeader('transaction_date', e.target.value)} className={inputClassName} /></div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Kode Transaksi</label><select value={form.transaction_code} onChange={(e) => updateHeader('transaction_code', e.target.value)} className={inputClassName}>{transactionCodes.map((code) => <option key={code} value={code}>{code}</option>)}</select></div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Referensi</label><input value={form.reference} onChange={(e) => updateHeader('reference', e.target.value)} className={inputClassName} /></div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Vendor</label><input value={form.vendor_name} onChange={(e) => updateHeader('vendor_name', e.target.value)} className={inputClassName} /></div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Keterangan</label><input value={form.notes} onChange={(e) => updateHeader('notes', e.target.value)} className={inputClassName} /></div>
                        </div>
                        <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
                            <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead className="bg-gray-50 dark:bg-gray-900"><tr><th className="px-2 py-2 text-left">Item</th><th className="px-2 py-2 text-left">Qty</th><th className="px-2 py-2 text-left">UOM</th><th className="px-2 py-2 text-left">Price</th><th className="px-2 py-2 text-left">Value</th><th className="px-2 py-2 text-left">Batch Number</th><th className="px-2 py-2 text-left">Expired Date</th><th className="px-2 py-2 text-left">Keterangan</th><th className="px-2 py-2 text-left">Aksi</th></tr></thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {form.lines.map((line, index) => {
                                        const value = (Number(line.qty) || 0) * (Number(line.price) || 0);
                                        return (
                                            <tr key={index}>
                                                <td className="p-2"><select value={line.item_id} onChange={(e) => updateLine(index, 'item_id', e.target.value)} className={`w-56 ${lineInputClassName}`}><option value="">Pilih Item</option>{items.map((item) => <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>)}</select>{errors[`lines.${index}.item_id`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.item_id`][0]}</p>}</td>
                                                <td className="p-2"><input type="number" min="0" step="0.000001" value={line.qty} onChange={(e) => updateLine(index, 'qty', e.target.value)} className={`w-28 ${lineInputClassName}`} /></td>
                                                <td className="p-2"><select value={line.uom_id} onChange={(e) => updateLine(index, 'uom_id', e.target.value)} className={`w-36 ${lineInputClassName}`}><option value="">UOM</option>{uoms.map((uom) => <option key={uom.id} value={uom.id}>{uom.code}</option>)}</select></td>
                                                <td className="p-2"><input type="number" min="0" step="0.000001" value={line.price} onChange={(e) => updateLine(index, 'price', e.target.value)} className={`w-36 ${lineInputClassName}`} /></td>
                                                <td className="p-2">{value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                <td className="p-2"><input value={line.batch_number} onChange={(e) => updateLine(index, 'batch_number', e.target.value)} className={`w-36 ${lineInputClassName}`} /></td>
                                                <td className="p-2"><input type="date" value={line.expired_date} onChange={(e) => updateLine(index, 'expired_date', e.target.value)} className={`w-44 ${lineInputClassName}`} /></td>
                                                <td className="p-2"><input value={line.notes} onChange={(e) => updateLine(index, 'notes', e.target.value)} className={`w-44 ${lineInputClassName}`} /></td>
                                                <td className="p-2"><button type="button" onClick={() => removeLine(index)} className="rounded border border-red-300 px-2 py-1 text-red-600">Hapus</button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <button type="button" onClick={addLine} className="rounded-lg border border-gray-400 px-3 py-2 text-sm text-gray-800">+ Tambah Line</button>
                            <div className="text-sm font-semibold text-gray-900">Total Value: {totalValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                        </div>

                        <div className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200">Upload Dokumen Receiving (Document Center)</h3>
                            {form.documents.map((doc, idx) => (
                                <div key={idx} className="mt-3 grid gap-2 md:grid-cols-4">
                                    <select value={doc.document_type_id} onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, document_type_id: e.target.value } : d) }))} className={inputClassName}>
                                        <option value="">Pilih tipe dokumen</option>
                                        {documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                                    </select>
                                    <input value={doc.title} onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, title: e.target.value } : d) }))} placeholder="Judul dokumen" className={inputClassName} />
                                    <input value={doc.document_number} onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, document_number: e.target.value } : d) }))} placeholder="No dokumen" className={inputClassName} />
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, file: e.target.files?.[0] ?? null } : d) }))} className={inputClassName} />
                                </div>
                            ))}
                            <button type="button" onClick={() => setForm((prev) => ({ ...prev, documents: [...prev.documents, { ...emptyDocument }] }))} className="mt-3 rounded border px-3 py-1 text-sm">+ Add Dokumen</button>

                            <div className="mt-4 rounded border border-gray-200 dark:border-gray-800">
                                <div className="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                    Daftar Dokumen Terupload ({(documents || []).length})
                                </div>
                                <div className="overflow-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="bg-white dark:bg-gray-950">
                                                <th className="border px-2 py-2 text-left">Document Type</th>
                                                <th className="border px-2 py-2 text-left">Judul</th>
                                                <th className="border px-2 py-2 text-left">No Dokumen</th>
                                                <th className="border px-2 py-2 text-left">Nama File</th>
                                                <th className="border px-2 py-2 text-left">Status Upload</th>
                                                <th className="border px-2 py-2 text-left">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(documents || []).length > 0 ? (documents || []).map((doc) => (
                                                <tr key={doc.id}>
                                                    <td className="border px-2 py-2">{doc.document_type?.name || '-'}</td>
                                                    <td className="border px-2 py-2">{doc.title || '-'}</td>
                                                    <td className="border px-2 py-2">{doc.document_number || '-'}</td>
                                                    <td className="border px-2 py-2">{doc.original_file_name || '-'}</td>
                                                    <td className="border px-2 py-2">
                                                        <span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${doc.status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                                                            {doc.status || 'uploaded'}
                                                        </span>
                                                    </td>
                                                    <td className="border px-2 py-2">
                                                        <div className="flex items-center gap-2">
                                                            <a href={route('apps.document-center.documents.download', doc.id)} target="_blank" rel="noreferrer" className="rounded border border-blue-300 px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">View</a>
                                                            <button
                                                                type="button"
                                                                onClick={() => removeUploadedDocument(doc.id)}
                                                                disabled={removingDocumentId === doc.id}
                                                                className="rounded border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                            >
                                                                {removingDocumentId === doc.id ? 'Removing...' : 'Remove'}
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )) : (
                                                <tr><td colSpan={6} className="border px-2 py-4 text-center text-gray-500">Belum ada dokumen yang terupload untuk receiving ini.</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <button type="submit" disabled={loading} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{loading ? 'Menyimpan...' : 'Update Receiving Entry'}</button>
                    </form>
                </div>

                {message && <div className={`rounded-lg border p-3 text-sm ${message.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{message.text}</div>}
            </div>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
