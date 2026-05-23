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

const normalizeStatus = (value) => String(value ?? '').toLowerCase();

const resolvePaymentStatus = (invoice) => {
  const netPayable = Number(invoice?.net_payable_amount ?? invoice?.grand_total ?? 0);
  const paidAmount = Number(invoice?.paid_amount ?? 0);
  const outstandingAmount = Number(invoice?.outstanding_amount ?? Math.max(0, netPayable - paidAmount));

  if (netPayable <= 0) return { key: 'unpaid', label: 'Belum Bayar', className: 'border-gray-300 bg-gray-100 text-gray-700' };
  if (outstandingAmount <= 0) return { key: 'paid', label: 'Lunas', className: 'border-emerald-300 bg-emerald-100 text-emerald-700' };
  if (paidAmount > 0 && outstandingAmount > 0) return { key: 'partial', label: 'Partial', className: 'border-amber-300 bg-amber-100 text-amber-700' };

  return { key: 'unpaid', label: 'Belum Bayar', className: 'border-rose-300 bg-rose-100 text-rose-700' };
};

export default function Tab({ data, vendor, documentTypes = [] }) {
  const invoices = data?.invoices?.data ?? [];
  const tableGrandTotal = invoices.reduce((total, invoice) => total + Number(invoice?.grand_total ?? 0), 0);
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
      <div className='grid grid-cols-1 gap-3 text-sm md:grid-cols-3'>
        <Card label='Received Not Invoiced' value={formatCurrency(data?.monitoring?.received_not_invoiced || 0)} />
        <Card label='Total Received' value={formatCurrency(data?.monitoring?.total_received || 0)} />
        <Card label='Total Invoiced' value={formatCurrency(tableGrandTotal)} />
      </div>
      <div className='overflow-auto border rounded'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-50'><tr>{['Internal Invoice No','Vendor Invoice No','Invoice Date','Due Date','Subtotal','Discount','PPN','WHT','Grand Total','Net Payable','Paid','Outstanding','Status Invoice','Status Dokumen','Action'].map(h=><th key={h} className='px-3 py-2 text-left'>{h}</th>)}</tr></thead>
          <tbody>{invoices.map((x)=>{ const paymentStatus = resolvePaymentStatus(x); const documentStatus = normalizeStatus(x.status); const isDraft = documentStatus === 'draft'; return <tr key={x.id} className='border-t'><td className='px-3 py-2'>{x.invoice_no_internal}</td><td className='px-3 py-2'>{x.vendor_invoice_no}</td><td className='px-3 py-2'>{formatDate(x.invoice_date)}</td><td className='px-3 py-2'>{formatDate(x.due_date)}</td><td className='px-3 py-2'>{formatCurrency(x.subtotal)}</td><td className='px-3 py-2'>{formatCurrency(x.discount_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.wht_tax_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.grand_total)}</td><td className='px-3 py-2'>{formatCurrency(x.net_payable_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.paid_amount)}</td><td className='px-3 py-2'>{formatCurrency(x.outstanding_amount)}</td><td className='px-3 py-2'><span className={`inline-flex rounded-full border px-2 py-1 text-xs font-semibold ${paymentStatus.className}`}>{paymentStatus.label}</span></td><td className='px-3 py-2 uppercase'>{x.status}</td><td className='px-3 py-2 space-x-1'><Link href={`/apps/procurement/vendor-invoices/${x.id}`} className='px-2 py-1 bg-gray-100 rounded'>View</Link><Link href={`/apps/procurement/vendor-invoices/${x.id}/edit`} className={`px-2 py-1 bg-gray-100 rounded ${!isDraft ? 'pointer-events-none opacity-50' : ''}`}>Edit</Link><button type='button' onClick={()=>approveInvoice(x.id)} disabled={!isDraft} className='px-2 py-1 bg-gray-100 rounded disabled:opacity-50'>Approve</button><button type='button' onClick={()=>deleteInvoice(x.id)} disabled={!isDraft} className='px-2 py-1 bg-red-50 text-red-700 rounded disabled:opacity-50'>Delete</button></td></tr>;})}</tbody>
        </table>
      </div>

    </div>
  );
}

const Card = ({ label, value }) => (
  <div className='rounded border bg-white p-3'>
    <div className='text-gray-500'>{label}</div>
    <div className='font-semibold'>{value}</div>
  </div>
);
