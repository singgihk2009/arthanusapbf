import { Link, router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

const formatCurrency = (value) => {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
};

const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

export default function Tab({ data, vendor, documentTypes = [] }) {
  const invoices = data?.invoices?.data ?? [];
  const docs = vendor?.documents ?? [];
  const [notice, setNotice] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [form, setForm] = useState({ document_type_id: '', document_number: '', issue_date: '', expiry_date: '', notes: '' });
  const fileInputRef = useRef(null);

  const supportingDocs = useMemo(
    () => docs.filter((doc) => doc?.document_type?.code !== 'VENDOR_LEGAL'),
    [docs],
  );

  const approveInvoice = (invoiceId) => {
    router.post(`/apps/procurement/vendor-invoices/${invoiceId}/approve`);
  };

  const deleteInvoice = (invoiceId) => {
    if (!window.confirm('Hapus vendor invoice ini?')) return;
    router.delete(`/apps/procurement/vendor-invoices/${invoiceId}`);
  };

  const uploadSupportingDocuments = async () => {
    const selectedFiles = Array.from(fileInputRef.current?.files ?? []);

    if (!form.document_type_id) return setNotice({ type: 'error', text: 'Document Type wajib dipilih.' });
    if (!selectedFiles.length) return setNotice({ type: 'error', text: 'Pilih minimal 1 file untuk diupload.' });

    setUploading(true);
    setNotice({ type: 'info', text: `Sedang upload ${selectedFiles.length} dokumen...` });

    try {
      for (const file of selectedFiles) {
        const payload = new FormData();
        payload.append('business_id', String(vendor.business_id ?? ''));
        payload.append('owner_type', 'vendor');
        payload.append('owner_id', String(vendor.id));
        payload.append('document_type_id', String(form.document_type_id));
        payload.append('document_number', form.document_number || '');
        payload.append('issue_date', form.issue_date || '');
        payload.append('expiry_date', form.expiry_date || '');
        payload.append('notes', form.notes || '');
        payload.append('file', file);

        const response = await fetch('/apps/document-center/documents', {
          method: 'POST',
          body: payload,
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
          credentials: 'same-origin',
        });

        if (!response.ok) {
          const error = await response.json().catch(() => ({}));
          const firstError = Object.values(error?.errors ?? {}).flat().find(Boolean);
          throw new Error(firstError || 'Gagal upload dokumen.');
        }
      }

      setNotice({ type: 'success', text: `${selectedFiles.length} dokumen berhasil diupload.` });
      if (fileInputRef.current) fileInputRef.current.value = '';
      router.reload({ only: ['vendor'], preserveScroll: true });
    } catch (err) {
      setNotice({ type: 'error', text: err?.message || 'Upload dokumen gagal.' });
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className='space-y-4'>
      {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}
      <div className='flex justify-end'>
        <Link href={`/apps/procurement/vendors/${vendor.id}/invoices/create`} className='px-3 py-2 bg-indigo-600 text-white rounded'>+ Create Vendor Invoice</Link>
      </div>
      <div className='overflow-auto border rounded'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'><tr>{['Internal Invoice No','Vendor Invoice No','Invoice Date','Due Date','Subtotal','Discount','PPN','WHT','Grand Total','Net Payable','Paid','Outstanding','Status','Action'].map(h=><th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr></thead>
          <tbody>{invoices.map((x)=><tr key={x.id} className='border-t'><td className='px-3 py-2'>{x.invoice_no_internal}</td><td className='px-3 py-2'>{x.vendor_invoice_no}</td><td className='px-3 py-2'>{x.invoice_date}</td><td className='px-3 py-2'>{x.due_date}</td><td className='px-3 py-2'>{formatCurrency(x.subtotal)}</td><td className='px-3 py-2'>{formatCurrency(x.discount_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.wht_tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.grand_total)}</td><td className='px-3 py-2'>{formatCurrency(x.net_payable_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.paid_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.outstanding_amount)}</td><td className='px-3 py-2'>{x.status}</td><td className='px-3 py-2 space-x-1'><Link href={`/apps/procurement/vendor-invoices/${x.id}`} className='px-2 py-1 bg-gray-100 rounded'>View</Link><Link href={`/apps/procurement/vendor-invoices/${x.id}/edit`} className={`px-2 py-1 bg-gray-100 rounded ${x.status!=='draft' ? 'pointer-events-none opacity-50' : ''}`}>Edit</Link><button type='button' onClick={()=>approveInvoice(x.id)} disabled={x.status!=='draft'} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Approve</button><button type='button' onClick={()=>deleteInvoice(x.id)} disabled={x.status!=='draft'} className='px-2 py-1 bg-red-50 text-red-700 rounded disabled:opacity-50'>Delete</button></td></tr>)}</tbody>
        </table>
      </div>

      <div className='rounded border p-4'>
        <div className='mb-3 text-sm font-semibold'>Upload Supporting Dokumen Invoice (Multiple)</div>
        <div className='grid gap-3 md:grid-cols-5'>
          <select value={form.document_type_id} onChange={(e) => setForm((prev) => ({ ...prev, document_type_id: e.target.value }))} className='rounded border px-2 py-2'>
            <option value=''>Pilih Document Type</option>
            {documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name} ({type.code})</option>)}
          </select>
          <input value={form.document_number} onChange={(e) => setForm((prev) => ({ ...prev, document_number: e.target.value }))} placeholder='No Dokumen (opsional)' className='rounded border px-2 py-2' />
          <input type='date' value={form.issue_date} onChange={(e) => setForm((prev) => ({ ...prev, issue_date: e.target.value }))} className='rounded border px-2 py-2' />
          <input type='date' value={form.expiry_date} onChange={(e) => setForm((prev) => ({ ...prev, expiry_date: e.target.value }))} className='rounded border px-2 py-2' />
          <input value={form.notes} onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))} placeholder='Keterangan (opsional)' className='rounded border px-2 py-2' />
        </div>
        <div className='mt-3 flex flex-col gap-2 md:flex-row md:items-center'>
          <input ref={fileInputRef} type='file' multiple accept='.pdf,.jpg,.jpeg,.png' className='w-full rounded border px-2 py-2' />
          <button type='button' onClick={uploadSupportingDocuments} disabled={uploading} className='rounded border border-blue-300 px-3 py-2 text-xs text-blue-700 disabled:cursor-not-allowed disabled:opacity-50'>
            {uploading ? 'Uploading...' : 'Upload Dokumen'}
          </button>
        </div>
      </div>

      <div className='overflow-auto rounded border p-3'>
        <table className='min-w-full text-sm border'>
          <thead>
            <tr className='bg-gray-100'><th className='px-3 py-2 border text-left' colSpan={5}>Daftar Supporting Dokumen Vendor</th></tr>
            <tr className='bg-gray-50'>
              <th className='border px-3 py-2 text-left font-medium'>Document Type</th>
              <th className='border px-3 py-2 text-left font-medium'>No Dokumen</th>
              <th className='border px-3 py-2 text-left font-medium'>Issue Date</th>
              <th className='border px-3 py-2 text-left font-medium'>Status</th>
              <th className='border px-3 py-2 text-left font-medium'>File</th>
            </tr>
          </thead>
          <tbody>
            {supportingDocs.length ? supportingDocs.map((doc) => <tr key={doc.id}>
              <td className='border px-3 py-2'>{doc.document_type?.name || '-'}</td>
              <td className='border px-3 py-2'>{doc.document_number || '-'}</td>
              <td className='border px-3 py-2'>{formatDate(doc.issue_date)}</td>
              <td className='border px-3 py-2'>{doc.status || 'draft'}</td>
              <td className='border px-3 py-2'>
                <a href={route('apps.procurement.vendors.documents.download', [vendor.id, doc.id])} target='_blank' className='rounded border border-gray-300 px-2 py-1 text-xs'>View</a>
              </td>
            </tr>) : <tr><td colSpan={5} className='border px-2 py-3 text-center text-gray-500'>Belum ada supporting dokumen.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
