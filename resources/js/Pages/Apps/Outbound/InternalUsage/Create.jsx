import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

const emptyLine = { item_id: '', batch_id: '', qty_used: '', uom_id: '', notes: '' };
const inputClassName = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100';
const lineInputClassName = 'rounded border border-gray-300 bg-white px-2 py-1 text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100';

export default function Create() {
    const { items, uoms, warehouses, batches, transactionCodes } = usePage().props;
    const [form, setForm] = useState({
        warehouse_id: '',
        document_date: new Date().toISOString().slice(0, 10),
        transaction_code: '',
        department: '',
        cost_center: '',
        notes: '',
        lines: [{ ...emptyLine }],
    });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);

    const defaultUomByItemId = useMemo(() => new Map(items.map((item) => [String(item.id), String(item.base_uom_id ?? '')])), [items]);

    const updateHeader = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));
    const addLine = () => setForm((prev) => ({ ...prev, lines: [...prev.lines, { ...emptyLine }] }));
    const updateLine = (index, field, value) => setForm((prev) => {
        const lines = [...prev.lines];
        const updatedLine = { ...lines[index], [field]: value };

        if (field === 'item_id') {
            updatedLine.uom_id = defaultUomByItemId.get(String(value)) ?? '';
        }

        lines[index] = updatedLine;

        return { ...prev, lines };
    });

    const removeLine = (index) => setForm((prev) => ({
        ...prev,
        lines: prev.lines.length === 1 ? [{ ...emptyLine }] : prev.lines.filter((_, idx) => idx !== index),
    }));

    const availableBatches = (itemId) => batches.filter((batch) => String(batch.item_id) === String(itemId));

    const submit = async (event) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            await window.axios.post(route('apps.outbound.internal-usage.store'), form);
            setMessage({ type: 'success', text: 'Dispatch berhasil disimpan.' });
            setForm((prev) => ({
                ...prev,
                warehouse_id: '',
                transaction_code: '',
                department: '',
                cost_center: '',
                notes: '',
                lines: [{ ...emptyLine }],
            }));
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
                setMessage({ type: 'error', text: 'Validasi gagal. Cek field yang ditandai.' });
            } else {
                setMessage({ type: 'error', text: 'Gagal menyimpan dispatch.' });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="Dispatch" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Form Dispatch</h2>
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">Catat pengeluaran inventory untuk kebutuhan dispatch.</p>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Warehouse</label><select value={form.warehouse_id} onChange={(e) => updateHeader('warehouse_id', e.target.value)} className={inputClassName}><option value="">Pilih Warehouse</option>{warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}</select>{errors.warehouse_id && <p className="mt-1 text-xs text-red-500">{errors.warehouse_id[0]}</p>}</div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Tanggal Dokumen</label><input type="date" value={form.document_date} onChange={(e) => updateHeader('document_date', e.target.value)} className={inputClassName} />{errors.document_date && <p className="mt-1 text-xs text-red-500">{errors.document_date[0]}</p>}</div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Kode Transaksi</label><select value={form.transaction_code} onChange={(e) => updateHeader('transaction_code', e.target.value)} className={inputClassName}><option value="">Pilih Kode Transaksi</option>{transactionCodes.map((transactionCode) => <option key={transactionCode.value} value={transactionCode.value}>{transactionCode.label}</option>)}</select>{errors.transaction_code && <p className="mt-1 text-xs text-red-500">{errors.transaction_code[0]}</p>}</div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Department</label><input value={form.department} onChange={(e) => updateHeader('department', e.target.value)} className={inputClassName} /></div>
                            <div><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Cost Center</label><input value={form.cost_center} onChange={(e) => updateHeader('cost_center', e.target.value)} className={inputClassName} /></div>
                            <div className="md:col-span-2"><label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Keterangan</label><input value={form.notes} onChange={(e) => updateHeader('notes', e.target.value)} className={inputClassName} /></div>
                        </div>

                        <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
                            <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead className="bg-gray-50 dark:bg-gray-900"><tr><th className="px-2 py-2 text-left">Item</th><th className="px-2 py-2 text-left">Batch</th><th className="px-2 py-2 text-left">Qty</th><th className="px-2 py-2 text-left">UOM</th><th className="px-2 py-2 text-left">Keterangan</th><th className="px-2 py-2 text-left">Aksi</th></tr></thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {form.lines.map((line, index) => (
                                        <tr key={index}>
                                            <td className="p-2"><select value={line.item_id} onChange={(e) => { updateLine(index, 'item_id', e.target.value); updateLine(index, 'batch_id', ''); }} className={`w-56 ${lineInputClassName}`}><option value="">Pilih Item</option>{items.map((item) => <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>)}</select>{errors[`lines.${index}.item_id`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.item_id`][0]}</p>}</td>
                                            <td className="p-2"><select value={line.batch_id} onChange={(e) => updateLine(index, 'batch_id', e.target.value)} className={`w-44 ${lineInputClassName}`} disabled={!line.item_id}><option value="">Tanpa batch (AVG)</option>{availableBatches(line.item_id).map((batch) => <option key={batch.id} value={batch.id}>{batch.batch_no}{batch.expired_date ? ` (EXP ${batch.expired_date})` : ''}</option>)}</select>{errors[`lines.${index}.batch_id`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.batch_id`][0]}</p>}</td>
                                            <td className="p-2"><input type="number" min="0" step="0.000001" value={line.qty_used} onChange={(e) => updateLine(index, 'qty_used', e.target.value)} className={`w-28 ${lineInputClassName}`} />{errors[`lines.${index}.qty_used`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.qty_used`][0]}</p>}</td>
                                            <td className="p-2"><select value={line.uom_id} onChange={(e) => updateLine(index, 'uom_id', e.target.value)} className={`w-36 ${lineInputClassName}`}><option value="">UOM</option>{uoms.map((uom) => <option key={uom.id} value={uom.id}>{uom.code}</option>)}</select>{errors[`lines.${index}.uom_id`] && <p className="mt-1 text-xs text-red-500">{errors[`lines.${index}.uom_id`][0]}</p>}</td>
                                            <td className="p-2"><input value={line.notes} onChange={(e) => updateLine(index, 'notes', e.target.value)} className={`w-44 ${lineInputClassName}`} /></td>
                                            <td className="p-2"><button type="button" onClick={() => removeLine(index)} className="rounded border border-red-300 px-2 py-1 text-red-600">Hapus</button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <button type="button" onClick={addLine} className="rounded-lg border border-gray-400 px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-100">+ Tambah Line</button>
                        </div>

                        <button type="submit" disabled={loading} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900">{loading ? 'Menyimpan...' : 'Simpan Dispatch'}</button>
                    </form>
                </div>

                {message && <div className={`rounded-lg border p-3 text-sm ${message.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{message.text}</div>}
            </div>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
