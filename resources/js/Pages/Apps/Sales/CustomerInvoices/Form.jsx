import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });

export default function Page({ invoiceDraft, mode = 'create' }) {
  const defaults = invoiceDraft?.defaults ?? {};
  const invoice = invoiceDraft?.invoice ?? null;
  const isEdit = mode === 'edit' || Boolean(invoice?.id);
  const [draftLines, setDraftLines] = useState(invoiceDraft?.lines ?? []);
  const [editingLineId, setEditingLineId] = useState(null);
  const [form, setForm] = useState({
    customer_id: invoiceDraft?.customer?.id ?? '',
    dispatch_ids: invoiceDraft?.dispatches?.map((dispatch) => dispatch.id) ?? [],
    line_prices: Object.fromEntries((invoiceDraft?.lines ?? []).map((line) => [line.internal_usage_line_id, line.unit_price])),
    invoice_date: defaults.invoice_date ?? new Date().toISOString().slice(0, 10),
    due_date: defaults.due_date ?? '',
    discount_type: defaults.discount_type ?? 'amount',
    discount_value: defaults.discount_value ?? 0,
    tax_enabled: defaults.tax_enabled ?? true,
    tax_percent: defaults.tax_percent ?? 11,
    freight_amount: defaults.freight_amount ?? 0,
    notes: defaults.notes ?? '',
  });
  const [errors, setErrors] = useState({});
  const [processing, setProcessing] = useState(false);

  const totals = useMemo(() => {
    const subtotal = Number(draftLines.reduce((sum, line) => sum + Number(line.line_total || 0), 0) || 0);
    const discountValue = Number(form.discount_value || 0);
    const discountTotal = form.discount_type === 'percent' ? subtotal * discountValue / 100 : discountValue;
    const cappedDiscount = Math.min(discountTotal, subtotal);
    const freightAmount = Number(form.freight_amount || 0);
    const taxBase = Math.max(0, subtotal - cappedDiscount + freightAmount);
    const taxTotal = form.tax_enabled ? taxBase * Number(form.tax_percent || 0) / 100 : 0;

    return {
      subtotal,
      discountTotal: cappedDiscount,
      freightAmount,
      taxBase,
      taxTotal,
      grandTotal: taxBase + taxTotal,
    };
  }, [draftLines, form.discount_type, form.discount_value, form.freight_amount, form.tax_enabled, form.tax_percent]);

  const updateForm = (field, value) => setForm((previous) => ({ ...previous, [field]: value }));
  const updateLinePrice = (lineId, value) => {
    const numericValue = Math.max(0, Number(value || 0));
    setDraftLines((previous) => previous.map((line) => {
      if (String(line.internal_usage_line_id) !== String(lineId)) return line;

      return { ...line, unit_price: numericValue, line_total: Number(line.qty || 0) * numericValue };
    }));
    setForm((previous) => ({
      ...previous,
      line_prices: { ...previous.line_prices, [lineId]: numericValue },
    }));
  };

  const submit = (event) => {
    event.preventDefault();
    setProcessing(true);
    setErrors({});

    const url = isEdit ? route('apps.customer-invoices.update', invoice.id) : route('apps.customer-invoices.store');
    const options = {
      onError: (serverErrors) => setErrors(serverErrors),
      onFinish: () => setProcessing(false),
    };

    if (isEdit) {
      router.put(url, form, options);
      return;
    }

    router.post(url, form, options);
  };

  if (!invoiceDraft) {
    return (
      <AppLayout>
        <Head title={isEdit ? 'Edit Customer Invoice' : 'Create Customer Invoice'} />
        <div className='p-6'>
          <div className='rounded border bg-white p-4 shadow-sm'>
            <h1 className='text-xl font-semibold'>{isEdit ? 'Edit Customer Invoice' : 'Create Customer Invoice'}</h1>
            <p className='mt-2 text-sm text-gray-600'>Pilih dispatch POSTED dari tab Fulfillment customer untuk membuat invoice berdasarkan satu atau beberapa dispatch.</p>
            <Link href={route('apps.customers.index')} className='mt-4 inline-block rounded border px-3 py-2 text-sm'>Back to Customers</Link>
          </div>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout>
      <Head title={isEdit ? `Edit Invoice ${invoice?.number || ''}` : 'Create Customer Invoice'} />
      <div className='space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-3'>
            <div>
              <h1 className='text-xl font-semibold'>{isEdit ? `Edit Draft Invoice ${invoice?.number || ''}` : 'Draft Invoice dari Dispatch'}</h1>
              <p className='text-sm text-gray-600'>{invoiceDraft.customer.customer_name} ({invoiceDraft.customer.customer_code}){isEdit ? ' • Status: Draft' : ''}</p>
            </div>
            <div className='flex flex-wrap gap-2'>
              {isEdit && <Link href={route('apps.customer-invoices.show', invoice.id)} className='rounded border px-3 py-2 text-sm'>Back to Invoice</Link>}
              <Link href={route('apps.customers.show', invoiceDraft.customer.id)} className='rounded border px-3 py-2 text-sm'>Back to Customer</Link>
            </div>
          </div>
        </div>

        <form onSubmit={submit} className='grid gap-4 lg:grid-cols-3'>
          <div className='space-y-4 lg:col-span-2'>
            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Dispatch yang Ditagihkan</h2>
              <div className='overflow-x-auto'>
                <table className='min-w-full text-sm'>
                  <thead className='bg-gray-50'>
                    <tr><th className='px-3 py-2 text-left'>Dispatch</th><th className='px-3 py-2 text-left'>Tanggal</th><th className='px-3 py-2 text-left'>Sales Order</th><th className='px-3 py-2 text-left'>Warehouse</th></tr>
                  </thead>
                  <tbody className='divide-y'>
                    {invoiceDraft.dispatches.map((dispatch) => (
                      <tr key={dispatch.id}><td className='px-3 py-2'>{dispatch.number}</td><td className='px-3 py-2'>{dispatch.document_date}</td><td className='px-3 py-2'>{dispatch.source_number || '-'}</td><td className='px-3 py-2'>{dispatch.warehouse_label}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {errors.dispatch_ids && <p className='mt-2 text-sm text-red-600'>{errors.dispatch_ids}</p>}
            </div>

            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Line Invoice</h2>
              <div className='overflow-x-auto'>
                <table className='min-w-full text-sm'>
                  <thead className='bg-gray-50'>
                    <tr><th className='px-3 py-2 text-left'>Dispatch</th><th className='px-3 py-2 text-left'>Item</th><th className='px-3 py-2 text-right'>Qty</th><th className='px-3 py-2 text-left'>UOM</th><th className='px-3 py-2 text-right'>Harga</th><th className='px-3 py-2 text-right'>Total</th></tr>
                  </thead>
                  <tbody className='divide-y'>
                    {draftLines.map((line) => (
                      <tr key={line.internal_usage_line_id}>
                        <td className='px-3 py-2'>{line.dispatch_number}</td>
                        <td className='px-3 py-2'>
                          <div className='font-medium'>{line.item_sku} - {line.item_name}</div>
                          <div className='mt-1 text-xs text-gray-500'>Batch: {line.batch_no || '-'}</div>
                          <div className='text-xs text-gray-500'>Expired: {line.expired_date || '-'}</div>
                        </td>
                        <td className='px-3 py-2 text-right'>{Number(line.qty || 0).toLocaleString('id-ID')}</td>
                        <td className='px-3 py-2'>{line.uom_code}</td>
                        <td className='px-3 py-2 text-right'>
                          {editingLineId === line.internal_usage_line_id ? (
                            <div className='flex flex-col items-end gap-1'>
                              <input
                                type='number'
                                min='0'
                                step='0.01'
                                value={line.unit_price}
                                onChange={(event) => updateLinePrice(line.internal_usage_line_id, event.target.value)}
                                onBlur={() => setEditingLineId(null)}
                                className='w-32 rounded border px-2 py-1 text-right'
                                autoFocus
                              />
                              <button type='button' className='text-xs text-indigo-600' onMouseDown={(event) => event.preventDefault()} onClick={() => setEditingLineId(null)}>Selesai</button>
                            </div>
                          ) : (
                            <div className='flex flex-col items-end gap-1'>
                              <span>{formatCurrency(line.unit_price)}</span>
                              <span className='text-xs text-gray-500'>COGS: {formatCurrency(line.cogs)}</span>
                              <button type='button' className='text-xs font-medium text-indigo-600 hover:underline' onClick={() => setEditingLineId(line.internal_usage_line_id)}>Edit Price</button>
                            </div>
                          )}
                        </td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(line.line_total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div className='space-y-4'>
            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Pengaturan Tagihan</h2>
              <div className='space-y-3 text-sm'>
                <label className='block'>Tanggal Invoice<input type='date' value={form.invoice_date} onChange={(event) => updateForm('invoice_date', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                <label className='block'>Jatuh Tempo<input type='date' value={form.due_date} onChange={(event) => updateForm('due_date', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                <div className='grid grid-cols-2 gap-2'>
                  <label className='block'>Tipe Diskon<select value={form.discount_type} onChange={(event) => updateForm('discount_type', event.target.value)} className='mt-1 w-full rounded border px-3 py-2'><option value='amount'>Nominal</option><option value='percent'>Persen</option></select></label>
                  <label className='block'>Nilai Diskon<input type='number' min='0' step='0.01' value={form.discount_value} onChange={(event) => updateForm('discount_value', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                </div>
                <label className='block'>Biaya Kirim<input type='number' min='0' step='0.01' value={form.freight_amount} onChange={(event) => updateForm('freight_amount', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                <label className='flex items-center gap-2'><input type='checkbox' checked={form.tax_enabled} onChange={(event) => updateForm('tax_enabled', event.target.checked)} /> Kenakan PPN</label>
                <label className='block'>PPN (%)<input type='number' min='0' max='100' step='0.01' value={form.tax_percent} onChange={(event) => updateForm('tax_percent', event.target.value)} disabled={!form.tax_enabled} className='mt-1 w-full rounded border px-3 py-2 disabled:bg-gray-100' /></label>
                <label className='block'>Catatan<textarea value={form.notes} onChange={(event) => updateForm('notes', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' rows={3} /></label>
              </div>
            </div>

            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Ringkasan</h2>
              <div className='space-y-2 text-sm'>
                <div className='flex justify-between'><span>Subtotal</span><b>{formatCurrency(totals.subtotal)}</b></div>
                <div className='flex justify-between'><span>Diskon</span><b>- {formatCurrency(totals.discountTotal)}</b></div>
                <div className='flex justify-between'><span>Biaya Kirim</span><b>{formatCurrency(totals.freightAmount)}</b></div>
                <div className='flex justify-between'><span>DPP</span><b>{formatCurrency(totals.taxBase)}</b></div>
                <div className='flex justify-between'><span>PPN</span><b>{formatCurrency(totals.taxTotal)}</b></div>
                <div className='flex justify-between border-t pt-2 text-base'><span>Grand Total</span><b>{formatCurrency(totals.grandTotal)}</b></div>
              </div>
              <button type='submit' disabled={processing} className='mt-4 w-full rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50'>{processing ? 'Menyimpan...' : isEdit ? 'Update Draft Invoice' : 'Simpan Draft Invoice'}</button>
            </div>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
