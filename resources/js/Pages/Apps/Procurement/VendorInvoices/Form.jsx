import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';

const numberOrZero = (value) => Number(value || 0);

export default function Page({ vendor, receivingLines, internalInvoiceNoPreview, documentTypes = [] }) {
  const { data, setData, post, processing } = useForm({
    vendor_invoice_no: '',
    invoice_date: '',
    due_date: '',
    currency_code: 'IDR',
    exchange_rate: 1,
    notes: '',
    discount_amount: 0,
    freight_amount: 0,
    tax_rate: 11,
    wht_tax_type: '',
    wht_tax_rate: 0,
    wht_tax_base_amount: 0,
    lines: [],
    documents: [{ document_type_id: '', title: '', document_number: '', issue_date: '', expiry_date: '', notes: '', file: null }],
  });

  const selected = useMemo(() => data.lines, [data.lines]);
  const subtotal = selected.reduce((a, b) => a + (numberOrZero(b.qty_invoiced) * numberOrZero(b.unit_price)), 0);
  const taxBase = Math.max(0, subtotal - numberOrZero(data.discount_amount));
  const taxAmount = taxBase * numberOrZero(data.tax_rate) / 100;
  const grand = taxBase + taxAmount + numberOrZero(data.freight_amount);
  const whtBase = numberOrZero(data.wht_tax_base_amount) || taxBase;
  const wht = whtBase * numberOrZero(data.wht_tax_rate) / 100;
  const net = grand - wht;

  const toggle = (line, checked) => {
    if (checked) {
      setData('lines', [...data.lines, {
        source_line_type: line.source_line_type,
        source_line_id: line.source_line_id,
        qty_invoiced: line.qty_available_to_invoice,
        unit_price: line.unit_price,
      }]);
      return;
    }

    setData('lines', data.lines.filter((x) => !(x.source_line_type === line.source_line_type && x.source_line_id === line.source_line_id)));
  };

  const setNumericField = (field) => (event) => {
    const value = event.target.value;
    setData(field, value === '' ? '' : Number(value));
  };

  return <AppLayout><div className='p-6 space-y-6'>
    <h1 className='text-xl font-semibold'>Create Vendor Invoice</h1>

    <div className='grid grid-cols-1 gap-3 bg-white p-4 border rounded md:grid-cols-2'>
      <input disabled value={vendor.vendor_name || vendor.name} className='border p-2 rounded bg-slate-50' />
      <input disabled value={internalInvoiceNoPreview} className='border p-2 rounded bg-slate-50' />
      <input placeholder='Vendor invoice no' value={data.vendor_invoice_no} onChange={(e) => setData('vendor_invoice_no', e.target.value)} className='border p-2 rounded' />
      <input type='date' value={data.invoice_date} onChange={(e) => setData('invoice_date', e.target.value)} className='border p-2 rounded' />
    </div>

    <div className='bg-white p-4 border rounded overflow-auto'>
      <table className='min-w-full text-sm'><thead><tr><th></th><th>Receiving</th><th>PO</th><th>Item</th><th>Qty Rec</th><th>Qty Inv</th><th>Qty Avail</th><th>Qty To Invoice</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>{receivingLines.map((l) => {
        const s = data.lines.find((x) => x.source_line_type === l.source_line_type && x.source_line_id === l.source_line_id);
        return <tr key={`${l.source_line_type}-${l.source_line_id}`} className='border-t'><td><input type='checkbox' checked={!!s} onChange={(e) => toggle(l, e.target.checked)} /></td><td>{l.receiving_no}</td><td>{l.po_no}</td><td>{l.item_name}</td><td>{l.qty_received}</td><td>{l.qty_already_invoiced}</td><td>{l.qty_available_to_invoice}</td><td><input disabled={!s} type='number' value={s?.qty_invoiced ?? ''} onChange={(e) => setData('lines', data.lines.map((x) => x.source_line_type === l.source_line_type && x.source_line_id === l.source_line_id ? { ...x, qty_invoiced: e.target.value } : x))} className='border p-1 w-24 rounded' /></td><td><input disabled={!s} type='number' value={s?.unit_price ?? ''} onChange={(e) => setData('lines', data.lines.map((x) => x.source_line_type === l.source_line_type && x.source_line_id === l.source_line_id ? { ...x, unit_price: e.target.value } : x))} className='border p-1 w-28 rounded' /></td><td>{(numberOrZero(s?.qty_invoiced) * numberOrZero(s?.unit_price)).toFixed(2)}</td></tr>;
      })}</tbody></table>
    </div>

    <div className='bg-white p-4 border rounded space-y-3'>
      <h2 className='font-semibold text-slate-700'>Tax & Deduction</h2>
      <div className='grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3'>
        <label className='text-sm text-slate-600'>
          Discount Amount
          <input type='number' min='0' step='0.01' value={data.discount_amount} onChange={setNumericField('discount_amount')} className='mt-1 w-full border p-2 rounded' />
        </label>
        <label className='text-sm text-slate-600'>
          PPN Rate (%)
          <input type='number' min='0' step='0.01' value={data.tax_rate} onChange={setNumericField('tax_rate')} className='mt-1 w-full border p-2 rounded' />
        </label>
        <label className='text-sm text-slate-600'>
          WHT Type
          <input value={data.wht_tax_type} onChange={(e) => setData('wht_tax_type', e.target.value)} placeholder='e.g. PPh 23' className='mt-1 w-full border p-2 rounded' />
        </label>
        <label className='text-sm text-slate-600'>
          WHT Rate (%)
          <input type='number' min='0' step='0.01' value={data.wht_tax_rate} onChange={setNumericField('wht_tax_rate')} className='mt-1 w-full border p-2 rounded' />
        </label>
        <label className='text-sm text-slate-600'>
          WHT Base Amount
          <input type='number' min='0' step='0.01' value={data.wht_tax_base_amount} onChange={setNumericField('wht_tax_base_amount')} className='mt-1 w-full border p-2 rounded' />
        </label>
        <label className='text-sm text-slate-600'>
          Freight Amount
          <input type='number' min='0' step='0.01' value={data.freight_amount} onChange={setNumericField('freight_amount')} className='mt-1 w-full border p-2 rounded' />
        </label>
      </div>
    </div>

    <div className='bg-white p-4 border rounded text-sm md:text-base'>
      <div className='grid grid-cols-2 gap-y-1 md:grid-cols-5'>
        <span>Subtotal: <strong>{subtotal.toFixed(2)}</strong></span>
        <span>Tax: <strong>{taxAmount.toFixed(2)}</strong></span>
        <span>Grand: <strong>{grand.toFixed(2)}</strong></span>
        <span>WHT: <strong>{wht.toFixed(2)}</strong></span>
        <span>Net: <strong>{net.toFixed(2)}</strong></span>
      </div>
    </div>

    <div className='bg-white p-4 border rounded'>
      <h3 className='text-sm font-semibold text-gray-700'>Upload Dokumen Invoice (Document Center)</h3>
      {data.documents.map((doc, idx) => <div key={idx} className='mt-3 grid gap-2 md:grid-cols-4'>
        <select value={doc.document_type_id} onChange={(e) => setData('documents', data.documents.map((d, i) => i === idx ? { ...d, document_type_id: e.target.value } : d))} className='rounded border p-2'>
          <option value=''>Pilih Tipe Dokumen</option>
          {documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name || type.code}</option>)}
        </select>
        <input value={doc.title} onChange={(e) => setData('documents', data.documents.map((d, i) => i === idx ? { ...d, title: e.target.value } : d))} placeholder='Judul dokumen' className='rounded border p-2' />
        <input value={doc.document_number} onChange={(e) => setData('documents', data.documents.map((d, i) => i === idx ? { ...d, document_number: e.target.value } : d))} placeholder='No dokumen' className='rounded border p-2' />
        <input type='file' accept='.pdf,.jpg,.jpeg,.png' onChange={(e) => setData('documents', data.documents.map((d, i) => i === idx ? { ...d, file: e.target.files?.[0] ?? null } : d))} className='rounded border p-2' />
      </div>)}
      <button type='button' onClick={() => setData('documents', [...data.documents, { document_type_id: '', title: '', document_number: '', issue_date: '', expiry_date: '', notes: '', file: null }])} className='mt-3 rounded border px-3 py-1 text-sm'>+ Add Dokumen</button>
    </div>

    <button disabled={processing} onClick={() => post(`/apps/procurement/vendors/${vendor.id}/invoices`, { forceFormData: true })} className='px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50'>Submit</button>
  </div></AppLayout>;
}
