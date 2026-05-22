import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Edit({ vendor, invoice, receivingLines, selectedLines, documentTypes = [], uploadedDocuments = [] }) {
  const [notice, setNotice] = useState(null);
  const { data, setData, post, processing, transform } = useForm({
    vendor_invoice_no: invoice.vendor_invoice_no ?? '',
    invoice_date: invoice.invoice_date ?? '',
    due_date: invoice.due_date ?? '',
    currency_code: invoice.currency_code ?? 'IDR',
    exchange_rate: invoice.exchange_rate ?? 1,
    notes: invoice.notes ?? '',
    discount_amount: Number(invoice.discount_amount ?? 0),
    freight_amount: Number(invoice.freight_amount ?? 0),
    tax_rate: Number(invoice.tax_rate ?? 11),
    wht_tax_type: invoice.wht_tax_type ?? '',
    wht_tax_rate: Number(invoice.wht_tax_rate ?? 0),
    wht_tax_base_amount: Number(invoice.wht_tax_base_amount ?? 0),
    lines: selectedLines ?? [],
    documents: [{ document_type_id: '', title: '', document_number: '', issue_date: '', expiry_date: '', notes: '', file: null }],
  });

  const selected = useMemo(() => data.lines, [data.lines]);
  const subtotal = selected.reduce((a, b) => a + ((+b.qty_invoiced || 0) * (+b.unit_price || 0)), 0);
  const taxBase = Math.max(0, subtotal - (+data.discount_amount || 0));
  const taxAmount = taxBase * (+data.tax_rate || 0) / 100;
  const grand = taxBase + taxAmount + (+data.freight_amount || 0);
  const whtBase = (+data.wht_tax_base_amount || taxBase);
  const wht = whtBase * (+data.wht_tax_rate || 0) / 100;
  const net = grand - wht;

  const toggle = (line, checked) => {
    if (checked) setData('lines', [...data.lines, { source_line_type: line.source_line_type, source_line_id: line.source_line_id, qty_invoiced: line.qty_available_to_invoice, unit_price: line.unit_price }]);
    else setData('lines', data.lines.filter((x) => !(x.source_line_type === line.source_line_type && x.source_line_id === line.source_line_id)));
  };

  const setNumericField = (field) => (event) => {
    const value = event.target.value;
    setData(field, value === '' ? '' : Number(value));
  };

  const submitUpdate = () => {
    const normalizedDocuments = (data.documents || []).filter((doc) => doc?.file);
    const invalidDocument = normalizedDocuments.find((doc) => !doc?.document_type_id);
    if (invalidDocument) {
      setNotice({ type: 'error', text: 'Upload gagal: setiap file wajib memilih Tipe Dokumen.' });
      return;
    }

    setNotice({ type: 'info', text: 'Sedang update invoice dan upload dokumen...' });
    transform(() => ({ ...data, documents: normalizedDocuments, _method: 'put' }));
    post(`/apps/procurement/vendor-invoices/${invoice.id}`, {
      forceFormData: true,
      onError: (errors) => {
        const firstError = Object.values(errors ?? {}).flat().find(Boolean);
        setNotice({ type: 'error', text: firstError || 'Update invoice / upload dokumen gagal.' });
      },
      onSuccess: () => {
        setNotice({ type: 'success', text: 'Update invoice dan upload dokumen berhasil.' });
      },
    });
  };

  return <AppLayout><div className='p-6 space-y-6'>
    <div className='flex items-center justify-between gap-3'>
      <h1 className='text-xl font-semibold'>Edit Vendor Invoice</h1>
      <Link href={`/apps/procurement/vendors/${vendor.id}?tab=invoices`} className='rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50'>Back</Link>
    </div>
    {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}
    <div className='grid grid-cols-2 gap-3 bg-white p-4 border rounded'>
      <input disabled value={vendor?.vendor_name || vendor?.name} className='border p-2 rounded' /><input disabled value={invoice.invoice_no_internal} className='border p-2 rounded' />
      <input placeholder='Vendor invoice no' value={data.vendor_invoice_no} onChange={(e) => setData('vendor_invoice_no', e.target.value)} className='border p-2 rounded' /><input type='date' value={data.invoice_date} onChange={(e) => setData('invoice_date', e.target.value)} className='border p-2 rounded' />
    </div>
    <div className='bg-white p-4 border rounded overflow-auto'>
      <table className='min-w-full text-sm'><thead><tr><th></th><th>Receiving</th><th>PO</th><th>Item</th><th>Qty Rec</th><th>Qty Inv</th><th>Qty Avail</th><th>Qty To Invoice</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>{receivingLines.map((l) => { const s = data.lines.find((x) => x.source_line_type === l.source_line_type && x.source_line_id === l.source_line_id); return <tr key={`${l.source_line_type}-${l.source_line_id}`} className='border-t'><td><input type='checkbox' checked={!!s} onChange={(e) => toggle(l, e.target.checked)} /></td><td>{l.receiving_no}</td><td>{l.po_no}</td><td>{l.item_name}</td><td>{l.qty_received}</td><td>{l.qty_already_invoiced}</td><td>{l.qty_available_to_invoice}</td><td><input disabled={!s} type='number' value={s?.qty_invoiced ?? ''} onChange={(e) => setData('lines', data.lines.map((x) => x.source_line_type === l.source_line_type && x.source_line_id === l.source_line_id ? { ...x, qty_invoiced: e.target.value } : x))} className='border p-1 w-24' /></td><td><input disabled={!s} type='number' value={s?.unit_price ?? ''} onChange={(e) => setData('lines', data.lines.map((x) => x.source_line_type === l.source_line_type && x.source_line_id === l.source_line_id ? { ...x, unit_price: e.target.value } : x))} className='border p-1 w-28' /></td><td>{(((+s?.qty_invoiced || 0) * (+s?.unit_price || 0))).toFixed(2)}</td></tr>; })}</tbody></table>
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
      <h3 className='text-sm font-semibold text-gray-700'>Upload Dokumen Tambahan Invoice</h3>
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
      <div className='mt-4 rounded border'>
        <div className='border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700'>
          Daftar Dokumen Berhasil Terupload ({uploadedDocuments.length})
        </div>
        <div className='overflow-auto'>
          <table className='min-w-full text-sm'>
            <thead>
              <tr className='bg-white'>
                <th className='border px-2 py-2 text-left'>Document Type</th>
                <th className='border px-2 py-2 text-left'>Judul</th>
                <th className='border px-2 py-2 text-left'>Nama File</th>
                <th className='border px-2 py-2 text-left'>Status Upload</th>
                <th className='border px-2 py-2 text-left'>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {uploadedDocuments.length ? uploadedDocuments.map((doc) => <tr key={doc.id}>
                <td className='border px-2 py-2'>{doc.document_type?.name || '-'}</td>
                <td className='border px-2 py-2'>{doc.title || '-'}</td>
                <td className='border px-2 py-2'>{doc.original_file_name || '-'}</td>
                <td className='border px-2 py-2'>
                  <span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${doc.status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                    {doc.status || 'uploaded'}
                  </span>
                </td>
                <td className='border px-2 py-2'>
                  <a href={route('apps.document-center.documents.download', doc.id)} target='_blank' className='text-blue-600 underline'>View</a>
                </td>
              </tr>) : <tr><td colSpan={5} className='border px-2 py-4 text-center text-gray-500'>Belum ada dokumen yang terupload untuk invoice ini.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <button disabled={processing} onClick={submitUpdate} className='px-4 py-2 bg-indigo-600 text-white rounded'>Update</button>
  </div></AppLayout>;
}
