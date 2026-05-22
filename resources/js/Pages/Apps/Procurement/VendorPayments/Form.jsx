import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const n = (v) => Number(v || 0);

export default function Form({ vendor, outstandingInvoices = [], payment = null }) {
  const editing = !!payment;
  const existingLines = payment?.lines || [];

  const { flash } = usePage().props;
  const [notice, setNotice] = useState(null);

  const { data, setData, post, put, processing, errors } = useForm({
    payment_date: payment?.payment_date || '',
    payment_method: payment?.payment_method || 'BANK_TRANSFER',
    bank_account_id: payment?.bank_account_id || '',
    notes: payment?.notes || '',
    stamp_duty_amount: payment?.stamp_duty_amount || 0,
    freight_amount: payment?.freight_amount || 0,
    bank_charge_amount: payment?.bank_charge_amount || 0,
    lines: existingLines.length > 0
      ? existingLines.map((l) => ({ vendor_invoice_id: l.vendor_invoice_id, payment_amount: n(l.payment_amount), wht_amount: n(l.wht_amount), notes: l.notes || '' }))
      : [],
  });

  const lineMap = useMemo(() => new Map(data.lines.map((l) => [String(l.vendor_invoice_id), l])), [data.lines]);
  const totalInvoice = useMemo(() => data.lines.reduce((s, l) => s + n(l.payment_amount), 0), [data.lines]);
  const totalWht = useMemo(() => data.lines.reduce((s, l) => s + n(l.wht_amount), 0), [data.lines]);
  const stamp = n(data.stamp_duty_amount);
  const freight = n(data.freight_amount);
  const bank = n(data.bank_charge_amount);
  const netVendorPayment = totalInvoice - totalWht + stamp + freight;
  const cashOut = netVendorPayment + bank;

  const toggleInvoice = (inv, checked) => {
    if (checked) {
      setData('lines', [...data.lines, { vendor_invoice_id: inv.id, payment_amount: n(inv.outstanding_amount), wht_amount: 0, notes: '' }]);
      return;
    }
    setData('lines', data.lines.filter((l) => String(l.vendor_invoice_id) !== String(inv.id)));
  };

  const updateLine = (invoiceId, field, value) => {
    setData('lines', data.lines.map((l) => String(l.vendor_invoice_id) === String(invoiceId) ? { ...l, [field]: value === '' ? '' : Number(value) } : l));
  };

  useEffect(() => {
    if (flash?.success) setNotice({ type: 'success', text: flash.success });
    if (flash?.error) setNotice({ type: 'error', text: flash.error });
  }, [flash]);

  const submit = () => {
    setNotice({ type: 'info', text: 'Menyimpan draft payment...' });
    if (editing) {
      put(`/apps/procurement/vendors/${vendor.id}/payments/${payment.id}`, {
        preserveScroll: true,
        onSuccess: () => setNotice({ type: 'success', text: 'Payment draft berhasil diperbarui.' }),
        onError: () => setNotice({ type: 'error', text: 'Gagal menyimpan payment. Silakan cek field yang masih invalid.' }),
      });
      return;
    }
    post(`/apps/procurement/vendors/${vendor.id}/payments`, {
      preserveScroll: true,
      onSuccess: () => setNotice({ type: 'success', text: 'Payment draft berhasil disimpan.' }),
      onError: () => setNotice({ type: 'error', text: 'Gagal menyimpan payment. Silakan cek field yang masih invalid.' }),
    });
  };

  return <AppLayout><div className='p-6 space-y-5'>
    <div className='flex items-center justify-between'>
      <h1 className='text-xl font-semibold'>{editing ? 'Edit Vendor Payment' : 'Create Vendor Payment'}</h1>
      <Link className='rounded border px-3 py-2 text-sm' href={`/apps/procurement/vendors/${vendor.id}?tab=payments`}>Back</Link>
    </div>

    {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}

    <div className='grid grid-cols-1 md:grid-cols-2 gap-3 border rounded bg-white p-4'>
      <input type='date' value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} className='rounded border p-2' />
      <select value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)} className='rounded border p-2'>
        <option value='BANK_TRANSFER'>Bank Transfer</option><option value='CASH'>Cash</option><option value='GIRO'>Giro</option>
      </select>
      <input value={data.bank_account_id} onChange={(e) => setData('bank_account_id', e.target.value)} placeholder='Bank Account ID (optional)' className='rounded border p-2' />
      <input value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder='Notes' className='rounded border p-2' />
    </div>

    <div className='border rounded bg-white p-4 overflow-auto'>
      <h2 className='font-semibold mb-2'>Outstanding Invoices</h2>
      <table className='min-w-full text-sm'>
        <thead><tr><th></th><th>Invoice No</th><th>Date</th><th>Total</th><th>Outstanding</th><th>Payment Amount</th><th>WHT</th><th>Net</th></tr></thead>
        <tbody>{outstandingInvoices.map((inv) => {
          const line = lineMap.get(String(inv.id));
          return <tr key={inv.id} className='border-t'>
            <td><input type='checkbox' checked={!!line} onChange={(e) => toggleInvoice(inv, e.target.checked)} /></td>
            <td>{inv.vendor_invoice_no || inv.invoice_no_internal}</td>
            <td>{inv.invoice_date}</td>
            <td>{inv.net_payable_amount || inv.grand_total}</td>
            <td>{inv.outstanding_amount}</td>
            <td><input disabled={!line} type='number' className='border rounded p-1 w-32' value={line?.payment_amount ?? ''} onChange={(e) => updateLine(inv.id, 'payment_amount', e.target.value)} /></td>
            <td><input disabled={!line} type='number' className='border rounded p-1 w-24' value={line?.wht_amount ?? ''} onChange={(e) => updateLine(inv.id, 'wht_amount', e.target.value)} /></td>
            <td>{line ? (n(line.payment_amount) - n(line.wht_amount)).toFixed(2) : '-'}</td>
          </tr>;
        })}</tbody>
      </table>
      {errors.lines && <div className='text-red-600 text-sm mt-2'>{errors.lines}</div>}
    </div>

    <div className='grid grid-cols-1 md:grid-cols-3 gap-3 border rounded bg-white p-4'>
      <input type='number' min='0' step='0.01' value={data.stamp_duty_amount} onChange={(e) => setData('stamp_duty_amount', e.target.value)} placeholder='Stamp Duty' className='rounded border p-2' />
      <input type='number' min='0' step='0.01' value={data.freight_amount} onChange={(e) => setData('freight_amount', e.target.value)} placeholder='Freight' className='rounded border p-2' />
      <input type='number' min='0' step='0.01' value={data.bank_charge_amount} onChange={(e) => setData('bank_charge_amount', e.target.value)} placeholder='Bank Charge' className='rounded border p-2' />
    </div>

    <div className='border rounded bg-white p-4 text-sm'>
      <div>Total Invoice Payment: <strong>{totalInvoice.toFixed(2)}</strong></div>
      <div>Total WHT: <strong>{totalWht.toFixed(2)}</strong></div>
      <div>Net Payment to Vendor: <strong>{netVendorPayment.toFixed(2)}</strong></div>
      <div>Total Cash/Bank Out: <strong>{cashOut.toFixed(2)}</strong></div>
    </div>

    <button disabled={processing} onClick={submit} className='px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50'>{editing ? 'Update Draft Payment' : 'Save Draft Payment'}</button>
  </div></AppLayout>;
}
