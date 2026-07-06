import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';

const money = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Form({ receivingEntries = [], selectedReceivingEntry = null, lines = [], reasons = [] }) {
  const { data, setData, post, processing, errors } = useForm({
    receiving_entry_id: selectedReceivingEntry?.id || '',
    return_date: new Date().toISOString().slice(0, 10),
    reason_category: 'DAMAGED',
    notes: '',
    lines: lines.map((line) => ({ receiving_entry_line_id: line.receiving_entry_line_id, qty_returned: '', reason: 'DAMAGED', condition_notes: '' })),
  });

  const chooseReceivingEntry = (id) => router.get(route('apps.procurement.purchase-returns.create'), { receiving_entry_id: id }, { preserveScroll: true });
  const updateLine = (index, field, value) => setData('lines', data.lines.map((line, i) => i === index ? { ...line, [field]: value } : line));
  const submit = (event) => { event.preventDefault(); post(route('apps.procurement.purchase-returns.store')); };
  const total = data.lines.reduce((sum, line, index) => sum + Number(line.qty_returned || 0) * Number(lines[index]?.unit_cost || 0), 0);

  return <>
    <Head title="Create Purchase Return" />
    <form onSubmit={submit} className="space-y-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
      <div>
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Create Purchase Return</h2>
        <p className="text-sm text-gray-600 dark:text-gray-400">Pilih Receiving Entry posted, isi qty retur, reason, dan catatan kondisi.</p>
      </div>
      <div className="grid gap-3 md:grid-cols-4">
        <label className="md:col-span-2"><span className="mb-1 block text-xs font-semibold">Goods Receipt</span><select value={data.receiving_entry_id} onChange={(e) => chooseReceivingEntry(e.target.value)} className="w-full rounded border-gray-300 text-sm"><option value="">Pilih Goods Receipt</option>{receivingEntries.map((gr) => <option key={gr.id} value={gr.id}>{gr.number || gr.reference} - {gr.vendor_label || gr.vendor_name || '-'}</option>)}</select></label>
        <label><span className="mb-1 block text-xs font-semibold">Return Date</span><input type="date" value={data.return_date} onChange={(e) => setData('return_date', e.target.value)} className="w-full rounded border-gray-300 text-sm" /></label>
        <label><span className="mb-1 block text-xs font-semibold">Reason</span><select value={data.reason_category} onChange={(e) => setData('reason_category', e.target.value)} className="w-full rounded border-gray-300 text-sm">{reasons.map((r) => <option key={r}>{r}</option>)}</select></label>
      </div>
      {selectedReceivingEntry && <div className="rounded bg-gray-50 p-3 text-sm dark:bg-gray-900">Vendor: <b>{selectedReceivingEntry.vendor_label || selectedReceivingEntry.vendor_name}</b> | Warehouse: <b>{selectedReceivingEntry.warehouse_label}</b></div>}
      {errors.lines && <div className="rounded bg-rose-50 p-2 text-sm text-rose-700">{errors.lines}</div>}
      <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
        <table className="min-w-full text-sm"><thead className="bg-gray-50"><tr>{['Item','Batch','Expired','Received','Available','Qty Return','Reason','Notes','Amount'].map((h) => <th key={h} className="px-3 py-2 text-left">{h}</th>)}</tr></thead><tbody>
          {lines.length === 0 && <tr><td colSpan={9} className="px-3 py-4 text-center text-gray-500">Pilih Receiving Entry yang masih punya qty tersedia.</td></tr>}
          {lines.map((line, index) => <tr key={line.receiving_entry_line_id} className="border-t">
            <td className="px-3 py-2">{line.item_name}</td><td className="px-3 py-2">{line.batch_number || '-'}</td><td className="px-3 py-2">{line.expired_date || '-'}</td><td className="px-3 py-2 text-right">{money(line.qty_received)}</td><td className="px-3 py-2 text-right">{money(line.qty_available_to_return)}</td>
            <td className="px-3 py-2"><input type="number" min="0" max={line.qty_available_to_return} step="0.000001" value={data.lines[index]?.qty_returned || ''} onChange={(e) => updateLine(index, 'qty_returned', e.target.value)} className="w-28 rounded border-gray-300 text-right text-sm" /></td>
            <td className="px-3 py-2"><select value={data.lines[index]?.reason || data.reason_category} onChange={(e) => updateLine(index, 'reason', e.target.value)} className="rounded border-gray-300 text-sm">{reasons.map((r) => <option key={r}>{r}</option>)}</select></td>
            <td className="px-3 py-2"><input value={data.lines[index]?.condition_notes || ''} onChange={(e) => updateLine(index, 'condition_notes', e.target.value)} className="rounded border-gray-300 text-sm" placeholder="Catatan" /></td>
            <td className="px-3 py-2 text-right">{money(Number(data.lines[index]?.qty_returned || 0) * Number(line.unit_cost || 0))}</td>
          </tr>)}
        </tbody></table>
      </div>
      <label><span className="mb-1 block text-xs font-semibold">Notes</span><textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="w-full rounded border-gray-300 text-sm" /></label>
      <div className="flex items-center justify-between"><div className="font-semibold">Total Return: {money(total)}</div><button disabled={processing || !selectedReceivingEntry} className="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Save Draft</button></div>
    </form>
  </>;
}

Form.layout = (page) => <AppLayout children={page} />;
