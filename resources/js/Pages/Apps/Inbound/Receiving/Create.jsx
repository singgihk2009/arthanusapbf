import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

const emptyLine = {
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

export default function Create() {
    const { items, uoms, warehouses, transactionCodes, documentTypes, prefill } = usePage().props;
    const [form, setForm] = useState({
        warehouse_id: '',
        transaction_date: new Date().toISOString().slice(0, 10),
        transaction_code: prefill?.transaction_code || 'PEMBELIAN',
        reference: prefill?.reference || '',
        vendor_name: prefill?.vendor_name || '',
        notes: '',
        source_type: prefill?.source_type || null,
        source_id: prefill?.source_id || null,
        vendor_id: prefill?.vendor_id || null,
        lines: prefill?.lines?.length ? prefill.lines : [{ ...emptyLine }],
        documents: [{ ...emptyDocument }],
    });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);

    const defaultUomByItemId = useMemo(() => new Map(items.map((item) => [String(item.id), String(item.base_uom_id ?? '')])), [items]);

    const totalValue = useMemo(
        () => form.lines.reduce((sum, line) => sum + ((Number(line.qty) || 0) * (Number(line.price) || 0)), 0),
        [form.lines],
    );

    const updateHeader = (field, value) => {
        setForm((prev) => ({ ...prev, [field]: value }));
    };

    const updateLine = (index, field, value) => {
        setForm((prev) => {
            const lines = [...prev.lines];
            const updatedLine = { ...lines[index], [field]: value };

            if (field === 'item_id') {
                updatedLine.uom_id = defaultUomByItemId.get(String(value)) ?? '';
            }

            lines[index] = updatedLine;

            return { ...prev, lines };
        });
    };

    const addLine = () => { if (form.source_type === 'purchase_order') return; setForm((prev) => ({ ...prev, lines: [...prev.lines, { ...emptyLine }] })); };

    const removeLine = (index) => {
        setForm((prev) => ({
            ...prev,
            lines: prev.lines.length === 1 ? [{ ...emptyLine }] : prev.lines.filter((_, idx) => idx !== index),
        }));
    };

    const submit = async (event, action = 'save') => {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            const normalizedDocuments = (form.documents || []).filter((doc) => doc?.file);
            const payload = new FormData();
            payload.append('warehouse_id', form.warehouse_id);
            payload.append('transaction_date', form.transaction_date);
            payload.append('transaction_code', form.transaction_code);
            payload.append('reference', form.reference || '');
            payload.append('vendor_name', form.vendor_name || '');
            payload.append('notes', form.notes || '');
            if (form.source_type) payload.append('source_type', form.source_type);
            if (form.source_id) payload.append('source_id', form.source_id);
            if (form.vendor_id) payload.append('vendor_id', form.vendor_id);
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
            const response = await window.axios.post(route('apps.inbound.receiving.store'), payload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const isJsonResponse = String(response?.headers?.['content-type'] || '').includes('application/json');
            if (!isJsonResponse) {
                throw new Error('Server did not return JSON response');
            }
            if (action === 'post') {
                const createdId = response?.data?.id;
                if (!createdId) throw new Error('ID receiving entry tidak ditemukan.');
                await window.axios.post(route('apps.inventory.posting.receiving', createdId), null, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                window.location.href = route('apps.inbound.receiving.index');
                return;
            }

            setMessage({ type: 'success', text: response?.data?.message || 'Receiving entry berhasil disimpan.' });
            setForm((prev) => ({
                ...prev,
                warehouse_id: '',
                reference: '',
                vendor_name: '',
                notes: '',
                lines: [{ ...emptyLine }],
                documents: [{ ...emptyDocument }],
            }));
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
                setMessage({ type: 'error', text: 'Validasi gagal. Cek field yang ditandai.' });
            } else {
                setMessage({ type: 'error', text: 'Gagal menyimpan receiving entry.' });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="Receiving Entry" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Receiving Entry</h2>
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">Input barang masuk multi line dalam satu dokumen.</p>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Warehouse</label>
                                <select value={form.warehouse_id} onChange={(e) => updateHeader('warehouse_id', e.target.value)} className={inputClassName}>
                                    <option value="">Pilih Warehouse</option>
                                    {warehouses.map((warehouse) => (
                                        <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>
                                    ))}
                                </select>
                                {errors.warehouse_id && <p className="mt-1 text-xs text-red-500">{errors.warehouse_id[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Tanggal</label>
                                <input type="date" value={form.transaction_date} onChange={(e) => updateHeader('transaction_date', e.target.value)} className={inputClassName} />
                                {errors.transaction_date && <p className="mt-1 text-xs text-red-500">{errors.transaction_date[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Kode Transaksi</label>
                                <select value={form.transaction_code} onChange={(e) => updateHeader('transaction_code', e.target.value)} className={inputClassName}>
                                    {transactionCodes.map((code) => <option key={code} value={code}>{code}</option>)}
                                </select>
                                {errors.transaction_code && <p className="mt-1 text-xs text-red-500">{errors.transaction_code[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Referensi</label>
                                <input value={form.reference} onChange={(e) => updateHeader('reference', e.target.value)} className={inputClassName} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Vendor</label>
                                <input value={form.vendor_name} onChange={(e) => updateHeader('vendor_name', e.target.value)} className={inputClassName} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Keterangan</label>
                                <input value={form.notes} onChange={(e) => updateHeader('notes', e.target.value)} className={inputClassName} />
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-800">
                            <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-900">
                                <thead className="bg-gray-100 dark:bg-gray-900">
                                    <tr>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Item</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Qty</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">UOM</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Price</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Value</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Batch Number</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Expired Date</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Keterangan</th>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {form.lines.map((line, index) => {
                                        const value = (Number(line.qty) || 0) * (Number(line.price) || 0);

                                        return (
                                            <tr key={index} className="text-gray-800 dark:text-gray-100">
                                                <td className="p-2">
                                                    <select disabled={form.source_type === 'purchase_order'} value={line.item_id} onChange={(e) => updateLine(index, 'item_id', e.target.value)} className={`w-56 ${lineInputClassName}`}>
                                                        <option value="">Pilih Item</option>
                                                        {items.map((item) => <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>)}
                                                    </select>
                                                    {errors[`lines.${index}.item_id`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.item_id`][0]}</p>}
                                                </td>
                                                <td className="p-2">
                                                    <input type="number" min="0" max={line.max_qty || undefined} step="0.000001" value={line.qty} onChange={(e) => updateLine(index, 'qty', e.target.value)} className={`w-28 ${lineInputClassName}`} />
                                                    {errors[`lines.${index}.qty`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.qty`][0]}</p>}
                                                </td>
                                                <td className="p-2">
                                                    <select disabled={form.source_type === 'purchase_order'} value={line.uom_id} onChange={(e) => updateLine(index, 'uom_id', e.target.value)} className={`w-36 ${lineInputClassName}`}>
                                                        <option value="">UOM</option>
                                                        {uoms.map((uom) => <option key={uom.id} value={uom.id}>{uom.code}</option>)}
                                                    </select>
                                                    {errors[`lines.${index}.uom_id`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.uom_id`][0]}</p>}
                                                </td>
                                                <td className="p-2">
                                                    <input readOnly={form.source_type === 'purchase_order'} type="number" min="0" step="0.000001" value={line.price} onChange={(e) => updateLine(index, 'price', e.target.value)} className={`w-36 ${lineInputClassName}`} />
                                                    {errors[`lines.${index}.price`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.price`][0]}</p>}
                                                </td>
                                                <td className="p-2 font-medium text-gray-900 dark:text-gray-100">{value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
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
                        <div className="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-950">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200">Upload Dokumen Receiving (Document Center)</h3>
                            {form.documents.map((doc, idx) => (
                                <div key={idx} className="mt-3 grid gap-2 md:grid-cols-4">
                                    <select value={doc.document_type_id} onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, document_type_id: e.target.value } : d) }))} className={lineInputClassName}>
                                        <option value="">Pilih tipe dokumen</option>
                                        {documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                                    </select>
                                    <input value={doc.title} onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, title: e.target.value } : d) }))} placeholder="Judul dokumen" className={lineInputClassName} />
                                    <input value={doc.document_number} onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, document_number: e.target.value } : d) }))} placeholder="No dokumen" className={lineInputClassName} />
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setForm((prev) => ({ ...prev, documents: prev.documents.map((d, i) => i === idx ? { ...d, file: e.target.files?.[0] ?? null } : d) }))} className={lineInputClassName} />
                                </div>
                            ))}
                            <button type="button" onClick={() => setForm((prev) => ({ ...prev, documents: [...prev.documents, { ...emptyDocument }] }))} className="mt-3 rounded border border-gray-300 px-3 py-1 text-sm">+ Add Dokumen</button>

                            <div className="mt-3 overflow-x-auto rounded-md border border-gray-200 dark:border-gray-800">
                                <div className="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                    Daftar Dokumen Terupload ({form.documents.filter((doc) => doc.file).length})
                                </div>
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="bg-gray-50 dark:bg-gray-900">
                                            <th className="border px-3 py-2 text-left font-semibold">Document Type</th>
                                            <th className="border px-3 py-2 text-left font-semibold">Judul</th>
                                            <th className="border px-3 py-2 text-left font-semibold">No Dokumen</th>
                                            <th className="border px-3 py-2 text-left font-semibold">Nama File</th>
                                            <th className="border px-3 py-2 text-left font-semibold">Status Upload</th>
                                            <th className="border px-3 py-2 text-left font-semibold">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {form.documents.filter((doc) => doc.file).length > 0 ? form.documents.filter((doc) => doc.file).map((doc, idx) => (
                                            <tr key={`preview-${idx}`} className="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-950 dark:even:bg-gray-900">
                                                <td className="border px-3 py-2">{documentTypes.find((type) => String(type.id) === String(doc.document_type_id))?.name || '-'}</td>
                                                <td className="border px-3 py-2">{doc.title || '-'}</td>
                                                <td className="border px-3 py-2">{doc.document_number || '-'}</td>
                                                <td className="border px-3 py-2">{doc.file?.name || '-'}</td>
                                                <td className="border px-3 py-2"><span className="inline-flex rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">pending_review</span></td>
                                                <td className="border px-3 py-2 text-gray-500">-</td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan={6} className="border px-3 py-3 text-center text-gray-500">Belum ada dokumen yang dipilih.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-2">
                            {form.source_type !== 'purchase_order' && <button type="button" onClick={addLine} className="rounded-lg border border-gray-400 px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-100">+ Tambah Line</button>}
                            <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">Total Value: {totalValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <button type="submit" disabled={loading} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900">
                                {loading ? 'Menyimpan...' : 'Simpan Receiving Entry'}
                            </button>
                            <button type="button" onClick={(event) => submit(event, 'post')} disabled={loading} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                                {loading ? 'Menyimpan...' : 'Posting'}
                            </button>
                            <button type="button" onClick={() => { window.location.href = route('apps.inbound.receiving.index'); }} disabled={loading} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200">
                                Close
                            </button>
                        </div>
                    </form>
                </div>

                {message && (
                    <div className={`rounded-lg border p-3 text-sm ${message.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
