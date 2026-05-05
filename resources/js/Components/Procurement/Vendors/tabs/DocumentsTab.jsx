import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function DocumentsTab({ vendor, documentTypes = [] }) {
  const docs = vendor?.documents ?? [];
  const [notice, setNotice] = useState(null);
  const [completion, setCompletion] = useState(null);
  const [customForm, setCustomForm] = useState({ document_type_id: '', document_number: '', issue_date: '', expiry_date: '' });
  const customFileInput = useRef(null);

  const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

  useEffect(() => {
    let cancelled = false;
    fetch(`/apps/documents/owners/vendor/${vendor.id}/completion`)
      .then((r) => r.json())
      .then((data) => { if (!cancelled) setCompletion(data); })
      .catch(() => { if (!cancelled) setCompletion(null); });
    return () => { cancelled = true; };
  }, [vendor.id]);

  const allowedTypes = useMemo(() => {
    const requirementTypes = completion?.requirements_with_status?.map((item) => item?.requirement?.document_type).filter(Boolean) ?? [];
    return requirementTypes.length ? requirementTypes : documentTypes;
  }, [completion, documentTypes]);

  const documentTypeLabel = (doc) => {
    if (!doc) return '-';
    if (doc.document_type?.name) return doc.document_type.name;
    if (doc.document_type_label) return doc.document_type_label;
    if (doc.document_type_id) return `TYPE #${doc.document_type_id}`;
    return '-';
  };

  const submitUpload = (payload, fileInput) => {
    if (!fileInput?.files?.[0]) return setNotice({ type: 'error', text: 'Upload gagal: pilih file terlebih dahulu.' });
    setNotice({ type: 'info', text: 'Sedang upload dokumen...' });
    router.post(route('apps.procurement.vendors.documents.upload', vendor.id), { ...payload, file: fileInput.files[0] }, {
      forceFormData: true, preserveScroll: true,
      onSuccess: () => { fileInput.value = ''; setNotice({ type: 'success', text: 'Dokumen berhasil diupload.' }); router.reload({ only: ['vendor'] }); },
      onError: (errors) => { const firstError = Object.values(errors ?? {}).flat().find(Boolean); setNotice({ type: 'error', text: `Upload gagal${firstError ? `: ${firstError}` : '.'}` }); },
    });
  };

  const submitCustomUpload = () => {
    if (!customForm.document_type_id) return setNotice({ type: 'error', text: 'Upload gagal: Document Type wajib dipilih.' });
    submitUpload({ document_type_id: customForm.document_type_id, document_number: customForm.document_number || null, issue_date: customForm.issue_date || null, expiry_date: customForm.expiry_date || null }, customFileInput.current);
  };

  const doVerify = (docId) => {
    if (!confirm('Are you sure you want to verify this document?')) return;
    router.post(route('apps.procurement.vendors.documents.verify', [vendor.id, docId]), {}, { preserveScroll: true, onSuccess: () => { setNotice({ type: 'success', text: 'Document verified successfully.' }); router.reload({ only: ['vendor'] }); } });
  };

  const doReject = (docId) => {
    const reason = prompt('Masukkan alasan reject (minimal 5 karakter)');
    if (!reason || reason.length < 5) return setNotice({ type: 'error', text: 'Alasan reject minimal 5 karakter.' });
    router.post(route('apps.procurement.vendors.documents.reject', [vendor.id, docId]), { rejected_reason: reason }, { preserveScroll: true, onSuccess: () => { setNotice({ type: 'success', text: 'Document rejected successfully.' }); router.reload({ only: ['vendor'] }); } });
  };

  const statusBadge = (status) => {
    const map = {
      draft: 'bg-gray-100 text-gray-700',
      pending_review: 'bg-yellow-100 text-yellow-800',
      verified: 'bg-green-100 text-green-700',
      rejected: 'bg-red-100 text-red-700',
      expired: 'bg-orange-100 text-orange-700',
      archived: 'bg-gray-300 text-gray-800',
    };
    return map[status] || 'bg-gray-100 text-gray-600';
  };

  return <div className='space-y-5'>
    {completion && <div className='rounded border bg-blue-50 p-3 text-sm'>
      <div className='font-semibold'>Completion: {completion.completion_percentage ?? 0}%</div>
      {(completion.missing_documents?.length ?? 0) > 0 && <ul className='mt-2 list-disc pl-5 text-red-700'>{completion.missing_documents.map((m) => <li key={m.id}>{m.document_type?.name || m.document_type?.code || '-'}</li>)}</ul>}
    </div>}

    {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}

    <div className='rounded border p-4'>
      <div className='mb-3 text-sm font-semibold'>Tambah Dokumen Baru</div>
      <div className='grid gap-3 md:grid-cols-5'>
        <select value={customForm.document_type_id} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_type_id: e.target.value }))} className='rounded border px-2 py-2'>
          <option value=''>Pilih Document Type</option>
          {allowedTypes.map((type) => <option key={type.id} value={type.id}>{type.name} ({type.code})</option>)}
        </select>
        <input value={customForm.document_number} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_number: e.target.value }))} placeholder='Document Number' className='rounded border px-2 py-2' />
        <input type='date' value={customForm.issue_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, issue_date: e.target.value }))} className='rounded border px-2 py-2' />
        <input type='date' value={customForm.expiry_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, expiry_date: e.target.value }))} className='rounded border px-2 py-2' />
        <div className='flex items-center gap-2'><input ref={customFileInput} type='file' accept='.pdf,.jpg,.jpeg,.png' className='w-full rounded border px-2 py-2' /><button type='button' onClick={submitCustomUpload} className='shrink-0 rounded border border-blue-300 px-3 py-2 text-xs text-blue-700'>Upload</button></div>
      </div>
    </div>

    <div className='overflow-auto rounded border p-3'>
      <table className='min-w-full text-sm border'><thead><tr className='bg-gray-100'><th className='px-3 py-2 border text-left' colSpan={8}>Daftar Dokumen Vendor</th></tr></thead>
        <tbody>{docs.length ? docs.map((d) => <tr key={d.id}><td className='border px-3 py-2'>{documentTypeLabel(d)}</td><td className='border px-3 py-2'>{d.document_number || '-'}</td><td className='border px-3 py-2'>{formatDate(d.issue_date)}</td><td className='border px-3 py-2'>{formatDate(d.expiry_date)}</td><td className='border px-3 py-2'><span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${statusBadge(d.status)}`}>{d.status || 'draft'}</span></td><td className='border px-3 py-2'>{d.rejected_reason ? <span className='text-xs text-red-700'>{d.rejected_reason}</span> : '-'}</td><td className='border px-3 py-2'><a href={route('apps.procurement.vendors.documents.download', [vendor.id, d.id])} target='_blank' className='rounded border border-gray-300 px-2 py-1 text-xs'>View</a></td><td className='border px-3 py-2 space-x-2'>{d.status === 'pending_review' && <><button type='button' onClick={() => doVerify(d.id)} className='rounded border border-green-300 px-2 py-1 text-xs text-green-700'>Accept</button><button type='button' onClick={() => doReject(d.id)} className='rounded border border-red-300 px-2 py-1 text-xs text-red-700'>Reject</button></>}</td></tr>) : <tr><td className='border px-2 py-3 text-center text-gray-500' colSpan={8}>Belum ada dokumen tersimpan.</td></tr>}</tbody>
      </table>
    </div>
  </div>;
}
