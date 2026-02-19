import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
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

const inputClassName = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100';
const lineInputClassName = 'rounded border border-gray-300 bg-white px-2 py-1 text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100';

export default function Edit() {
    const { entry, lines, items, uoms, warehouses, transactionCodes } = usePage().props;
    const [form, setForm] = useState({
        warehouse_id: entry.warehouse_id ? String(entry.warehouse_id) : '',
        transaction_date: entry.transaction_date,
        transaction_code: entry.transaction_code,
        reference: entry.reference,
        vendor_name: entry.vendor_name,
        notes: entry.notes,
        lines: lines.length > 0 ? lines : [{ ...emptyLine }],
    });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [loading, setLoading] = useState(false);

    const totalValue = useMemo(
        () => form.lines.reduce((sum, line) => sum + ((Number(line.qty) || 0) * (Number(line.price) || 0)), 0),
        [form.lines],
    );

    const updateHeader = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));

    const updateLine = (index, field, value) => {
        setForm((prev) => {
            const draft = [...prev.lines];
            draft[index] = { ...draft[index], [field]: value };
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
            await window.axios.put(route('apps.inbound.receiving.update', entry.id), form);
            window.location.href = route('apps.inbound.receiving.index');
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
                setMessage({ type: 'error', text: 'Validasi gagal. Cek field yang ditandai.' });
            } else {
                setMessage({ type: 'error', text: 'Gagal memperbarui receiving entry.' });
            }
            setLoading(false);
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

                        <button type="submit" disabled={loading} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{loading ? 'Menyimpan...' : 'Update Receiving Entry'}</button>
                    </form>
                </div>

                {message && <div className={`rounded-lg border p-3 text-sm ${message.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{message.text}</div>}
            </div>
        </>
    );
}

Edit.layout = (page) => <AppLayout children={page} />;
