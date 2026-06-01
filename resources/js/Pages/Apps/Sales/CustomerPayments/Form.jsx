import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });

export default function Page({ paymentDraft }) {
  const defaults = paymentDraft?.defaults ?? {};
  const [form, setForm] = useState({
    customer_id: paymentDraft?.customer?.id ?? '',
    invoice_ids: paymentDraft?.invoices?.map((invoice) => invoice.id) ?? [],
    payment_date: defaults.payment_date ?? new Date().toISOString().slice(0, 10),
    payment_method: defaults.payment_method ?? 'Transfer Bank',
    bank_account_id: '',
    bank_charge: 0,
    notes: '',
    allocations: paymentDraft?.invoices?.map((invoice) => ({
      customer_invoice_id: invoice.id,
      amount_applied: invoice.balance_due,
      discount_taken: 0,
      wht_amount: 0,
      other_deduction_amount: 0,
      writeoff_amount: 0,
    })) ?? [],
  });
  const [errors, setErrors] = useState({});
  const [processing, setProcessing] = useState(false);

  const invoicesById = useMemo(() => new Map((paymentDraft?.invoices ?? []).map((invoice) => [invoice.id, invoice])), [paymentDraft?.invoices]);

  const totals = useMemo(() => form.allocations.reduce((summary, allocation) => {
    const amountApplied = Number(allocation.amount_applied || 0);
    const discountTaken = Number(allocation.discount_taken || 0);
    const whtAmount = Number(allocation.wht_amount || 0);
    const otherDeductionAmount = Number(allocation.other_deduction_amount || 0);

    return {
      amountApplied: summary.amountApplied + amountApplied,
      discountTaken: summary.discountTaken + discountTaken,
      whtAmount: summary.whtAmount + whtAmount,
      otherDeductionAmount: summary.otherDeductionAmount + otherDeductionAmount,
      grossSettlementAmount: summary.grossSettlementAmount + amountApplied + discountTaken - whtAmount - otherDeductionAmount,
    };
  }, { amountApplied: 0, discountTaken: 0, whtAmount: 0, otherDeductionAmount: 0, grossSettlementAmount: 0 }), [form.allocations]);

  const updateForm = (field, value) => setForm((previous) => ({ ...previous, [field]: value }));
  const updateAllocation = (index, field, value) => setForm((previous) => {
    const allocations = [...previous.allocations];
    allocations[index] = { ...allocations[index], [field]: value };
    return { ...previous, allocations };
  });

  const submit = (event) => {
    event.preventDefault();
    setProcessing(true);
    setErrors({});

    router.post(route('apps.customer-payments.store'), form, {
      onError: (serverErrors) => setErrors(serverErrors),
      onFinish: () => setProcessing(false),
    });
  };

  if (!paymentDraft) {
    return (
      <AppLayout>
        <Head title='Create Customer Payment' />
        <div className='p-6'>
          <div className='rounded border bg-white p-4 shadow-sm'>
            <h1 className='text-xl font-semibold'>Create Customer Payment</h1>
            <p className='mt-2 text-sm text-gray-600'>Pilih satu atau beberapa invoice posted dari menu Customer Invoices untuk membuat collection payment.</p>
            <Link href={route('apps.customer-invoices.index')} className='mt-4 inline-block rounded border px-3 py-2 text-sm'>Back to Customer Invoices</Link>
          </div>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout>
      <Head title='Create Customer Payment' />
      <div className='space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-3'>
            <div>
              <h1 className='text-xl font-semibold'>Collection Payment dari Customer</h1>
              <p className='text-sm text-gray-600'>{paymentDraft.customer.customer_name} ({paymentDraft.customer.customer_code})</p>
            </div>
            <Link href={route('apps.customer-invoices.index')} className='rounded border px-3 py-2 text-sm'>Back to Invoices</Link>
          </div>
        </div>

        <form onSubmit={submit} className='grid gap-4 xl:grid-cols-4'>
          <div className='space-y-4 xl:col-span-3'>
            <div className='rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800'>
              <div className='font-semibold'>Flow Collection Payment</div>
              <ol className='mt-2 list-decimal space-y-1 pl-5'>
                <li>Pilih invoice yang dibayar oleh customer; satu payment dapat melunasi satu atau beberapa invoice.</li>
                <li>Isi nilai invoice/kas pada kolom Bayar, lalu isi Biaya Lainnya, potongan WHT, dan/atau Potongan Lain bila ada.</li>
                <li>Total settlement = Nilai Invoice + Biaya Lainnya - Potongan WHT - Potongan Lainnya.</li>
              </ol>
            </div>

            <div className='overflow-x-auto rounded border bg-white shadow-sm'>
              <table className='min-w-full text-sm'>
                <thead className='bg-gray-50'>
                  <tr>
                    <th className='px-3 py-2 text-left'>Invoice</th>
                    <th className='px-3 py-2 text-right'>Balance Due</th>
                    <th className='px-3 py-2 text-right'>Bayar/Kas</th>
                    <th className='px-3 py-2 text-right'>Biaya Lainnya</th>
                    <th className='px-3 py-2 text-right'>WHT</th>
                    <th className='px-3 py-2 text-right'>Potongan Lain</th>
                    <th className='px-3 py-2 text-right'>Settlement</th>
                  </tr>
                </thead>
                <tbody className='divide-y'>
                  {form.allocations.map((allocation, index) => {
                    const invoice = invoicesById.get(allocation.customer_invoice_id);
                    const settlement = Number(allocation.amount_applied || 0) + Number(allocation.discount_taken || 0) - Number(allocation.wht_amount || 0) - Number(allocation.other_deduction_amount || 0);
                    const overAllocated = Number(allocation.amount_applied || 0) - Number(invoice?.balance_due || 0) > 0.01;

                    return (
                      <tr key={allocation.customer_invoice_id} className={overAllocated ? 'bg-red-50' : ''}>
                        <td className='px-3 py-2'>
                          <div className='font-medium'>{invoice?.number}</div>
                          <div className='text-xs text-gray-500'>Due: {invoice?.due_date || '-'}</div>
                        </td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(invoice?.balance_due)}</td>
                        <td className='px-3 py-2'><input type='number' min='0' step='0.01' value={allocation.amount_applied} onChange={(event) => updateAllocation(index, 'amount_applied', event.target.value)} className='w-28 rounded border px-2 py-1 text-right' /></td>
                        <td className='px-3 py-2'><input type='number' min='0' step='0.01' value={allocation.discount_taken} onChange={(event) => updateAllocation(index, 'discount_taken', event.target.value)} className='w-24 rounded border px-2 py-1 text-right' /></td>
                        <td className='px-3 py-2'><input type='number' min='0' step='0.01' value={allocation.wht_amount} onChange={(event) => updateAllocation(index, 'wht_amount', event.target.value)} className='w-24 rounded border px-2 py-1 text-right' /></td>
                        <td className='px-3 py-2'><input type='number' min='0' step='0.01' value={allocation.other_deduction_amount} onChange={(event) => updateAllocation(index, 'other_deduction_amount', event.target.value)} className='w-24 rounded border px-2 py-1 text-right' /></td>
                        <td className={`px-3 py-2 text-right font-semibold ${overAllocated ? 'text-red-700' : ''}`}>{formatCurrency(settlement)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            {errors.allocations && <p className='text-sm text-red-600'>{errors.allocations}</p>}
            {errors.invoice_ids && <p className='text-sm text-red-600'>{errors.invoice_ids}</p>}
          </div>

          <div className='space-y-4'>
            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Header Payment</h2>
              <div className='space-y-3 text-sm'>
                <label className='block'>Tanggal Terima<input type='date' value={form.payment_date} onChange={(event) => updateForm('payment_date', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                <label className='block'>Metode Pembayaran<input value={form.payment_method} onChange={(event) => updateForm('payment_method', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                <label className='block'>Bank Charge<input type='number' min='0' step='0.01' value={form.bank_charge} onChange={(event) => updateForm('bank_charge', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' /></label>
                <label className='block'>Catatan<textarea value={form.notes} onChange={(event) => updateForm('notes', event.target.value)} className='mt-1 w-full rounded border px-3 py-2' rows={3} /></label>
              </div>
            </div>

            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Ringkasan</h2>
              <div className='space-y-2 text-sm'>
                <div className='flex justify-between'><span>Kas Diterima</span><b>{formatCurrency(totals.amountApplied)}</b></div>
                <div className='flex justify-between'><span>Biaya Lainnya</span><b>{formatCurrency(totals.discountTaken)}</b></div>
                <div className='flex justify-between'><span>WHT</span><b>{formatCurrency(totals.whtAmount)}</b></div>
                <div className='flex justify-between'><span>Potongan Lain</span><b>{formatCurrency(totals.otherDeductionAmount)}</b></div>
                <div className='flex justify-between border-t pt-2 text-base'><span>Total Settlement</span><b>{formatCurrency(totals.grossSettlementAmount)}</b></div>
              </div>
              <button type='submit' disabled={processing} className='mt-4 w-full rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50'>{processing ? 'Menyimpan...' : 'Simpan Draft Payment'}</button>
            </div>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
