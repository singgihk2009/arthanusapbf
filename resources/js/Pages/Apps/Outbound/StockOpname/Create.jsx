import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

const emptyLine = { item_id: '', batch_id: '', counted_qty_base: '' };
const inputClassName = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm';

export default function Create({ initial = null, isEdit = false }) {
    const { items, warehouses, batches } = usePage().props;
    const defaultForm = useMemo(() => initial ?? { warehouse_id: '', document_date: new Date().toISOString().slice(0, 10), type: 'CYCLE', notes: '', lines: [{ ...emptyLine }] }, [initial]);
    const [form, setForm] = useState(defaultForm);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);

    const updateLine = (index, field, value) => setForm((prev) => {
        const lines = [...prev.lines];
        lines[index] = { ...lines[index], [field]: value };
        return { ...prev, lines };
    });

    const submit = async (event) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        try {
            if (isEdit) {
                await window.axios.put(route('apps.outbound.stock-opname.update', initial.id), form);
            } else {
                await window.axios.post(route('apps.outbound.stock-opname.store'), form);
            }
            window.location.href = route('apps.outbound.stock-opname.index');
        } catch (error) {
            if (error.response?.status === 422) setErrors(error.response.data.errors ?? {});
        } finally { setLoading(false); }
    };

    return (
        <>
            <Head title={isEdit ? 'Edit Stock Opname' : 'Create Stock Opname'} />
            <form onSubmit={submit} className="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <select value={form.warehouse_id} onChange={(e) => setForm((p) => ({ ...p, warehouse_id: e.target.value }))} className={inputClassName}><option value="">Pilih Warehouse</option>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.code} - {w.name}</option>)}</select>
                    <input type="date" value={form.document_date} onChange={(e) => setForm((p) => ({ ...p, document_date: e.target.value }))} className={inputClassName} />
                    <select value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))} className={inputClassName}><option value="CYCLE">CYCLE</option><option value="FULL">FULL</option></select>
                    <input value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} placeholder="Keterangan" className={inputClassName} />
                </div>
                <div className="overflow-x-auto rounded border border-gray-200">
                    <table className="min-w-full text-sm"><thead><tr><th className="p-2 text-left">Item</th><th className="p-2 text-left">Batch</th><th className="p-2 text-left">Qty Counted (Base)</th><th className="p-2 text-left">Aksi</th></tr></thead><tbody>
                        {form.lines.map((line, idx) => <tr key={idx}><td className="p-2"><select value={line.item_id} onChange={(e) => updateLine(idx, 'item_id', e.target.value)} className={inputClassName}><option value="">Pilih Item</option>{items.map((item) => <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>)}</select></td><td className="p-2"><select value={line.batch_id} onChange={(e) => updateLine(idx, 'batch_id', e.target.value)} className={inputClassName}><option value="">-</option>{batches.filter((batch) => !line.item_id || String(batch.item_id) === String(line.item_id)).map((batch) => <option key={batch.id} value={batch.id}>{batch.batch_no}</option>)}</select></td><td className="p-2"><input type="number" min="0" step="0.000001" value={line.counted_qty_base} onChange={(e) => updateLine(idx, 'counted_qty_base', e.target.value)} className={inputClassName} /></td><td className="p-2"><button type="button" onClick={() => setForm((prev) => ({ ...prev, lines: prev.lines.length === 1 ? [{ ...emptyLine }] : prev.lines.filter((_, i) => i !== idx) }))} className="rounded border border-red-300 px-2 py-1 text-red-600">Hapus</button></td></tr>)}
                    </tbody></table>
                </div>
                {errors.lines && <p className="text-xs text-red-500">{errors.lines[0]}</p>}
                <button type="button" onClick={() => setForm((prev) => ({ ...prev, lines: [...prev.lines, { ...emptyLine }] }))} className="rounded border border-gray-300 px-3 py-2">+ Tambah Line</button>
                <div><button type="submit" disabled={loading} className="rounded bg-gray-900 px-4 py-2 text-white">{loading ? 'Menyimpan...' : isEdit ? 'Update Opname' : 'Simpan Opname'}</button></div>
            </form>
        </>
    );
}

Create.layout = (page) => <AppLayout children={page} />;
