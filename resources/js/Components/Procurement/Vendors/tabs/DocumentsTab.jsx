import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DocumentsTab({ vendor }) {
  const docs = vendor?.documents ?? [];
  const [notice, setNotice] = useState(null);
  const [customForm, setCustomForm] = useState({ document_type: '', document_number: '', issue_date: '', expiry_date: '' });
  const customFileInput = useRef(null);

  const documentTypeLabel = (doc) => {
    if (!doc) return '-';
    if (typeof doc.document_type === 'string' && doc.document_type.trim()) return doc.document_type;
    if (doc.document_type && typeof doc.document_type === 'object') return doc.document_type.name || doc.document_type.code || '-';
    if (doc.document_type_label) return doc.document_type_label;
    if (doc.document_type_id) return `TYPE #${doc.document_type_id}`;
    return '-';
  };

  const submitUpload = (payload, fileInput) => {
    if (!fileInput?.files?.[0]) return setNotice({ type: 'error', text: 'Upload gagal: pilih file terlebih dahulu.' });

    setNotice({ type: 'info', text: 'Sedang upload dokumen...' });
    router.post(route('apps.procurement.vendors.documents.upload', vendor.id), { ...payload, file: fileInput.files[0] }, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        fileInput.value = '';
        setNotice({ type: 'success', text: 'Dokumen berhasil diupload.' });
      },
      onError: (errors) => {
        const firstError = Object.values(errors ?? {}).flat().find(Boolean);
        setNotice({ type: 'error', text: `Upload gagal${firstError ? `: ${firstError}` : '.'}` });
      },
    });
  };

  const submitCustomUpload = () => {
    if (!customForm.document_type.trim()) return setNotice({ type: 'error', text: 'Upload gagal: Document Type wajib diisi.' });
    submitUpload({ document_type: customForm.document_type, document_number: customForm.document_number || null, issue_date: customForm.issue_date || null, expiry_date: customForm.expiry_date || null }, customFileInput.current);
  };

  const verifyDocument = (documentId, verificationStatus) => {
    router.post(route('apps.procurement.vendors.documents.verify', [vendor.id, documentId]), { verification_status: verificationStatus }, {
      preserveScroll: true,
      onSuccess: () => setNotice({ type: 'success', text: 'Status verifikasi dokumen berhasil diperbarui.' }),
      onError: () => setNotice({ type: 'error', text: 'Gagal memperbarui status verifikasi dokumen.' }),
    });
  };

  const deleteDocument = (documentId) => {
    if (!window.confirm('Hapus dokumen ini?')) return;
    router.delete(route('apps.procurement.vendors.documents.delete', [vendor.id, documentId]), {
      preserveScroll: true,
      onSuccess: () => setNotice({ type: 'success', text: 'Dokumen berhasil dihapus.' }),
      onError: () => setNotice({ type: 'error', text: 'Gagal menghapus dokumen.' }),
    });
  };

  return <div className='space-y-5'>
    {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}

    <div className='rounded border p-4'>
      <div className='mb-3 text-sm font-semibold'>Tambah Dokumen Baru</div>
      <div className='grid gap-3 md:grid-cols-5'>
        <input value={customForm.document_type} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_type: e.target.value }))} placeholder='Document Type (contoh: NIB)' className='rounded border px-2 py-2' />
        <input value={customForm.document_number} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_number: e.target.value }))} placeholder='Document Number' className='rounded border px-2 py-2' />
        <input type='date' value={customForm.issue_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, issue_date: e.target.value }))} className='rounded border px-2 py-2' />
        <input type='date' value={customForm.expiry_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, expiry_date: e.target.value }))} className='rounded border px-2 py-2' />
        <div className='flex items-center gap-2'>
          <input ref={customFileInput} type='file' accept='.pdf,.jpg,.jpeg,.png' className='w-full rounded border px-2 py-2' />
          <button type='button' onClick={submitCustomUpload} className='shrink-0 rounded border border-blue-300 px-3 py-2 text-xs text-blue-700'>Upload</button>
        </div>
      </div>
    </div>

    <div className='overflow-auto rounded border p-3'>
      <table className='min-w-full text-sm border'>
        <thead>
          <tr className='bg-gray-100'><th className='px-3 py-2 border text-left' colSpan={7}>Daftar Dokumen Vendor</th></tr>
          <tr className='bg-gray-50'>
            <th className='px-3 py-2 border'>Document Type</th><th className='px-3 py-2 border'>Document Number</th><th className='px-3 py-2 border'>Issue Date</th><th className='px-3 py-2 border'>Expiry Date</th><th className='px-3 py-2 border'>Verification</th><th className='px-3 py-2 border'>File</th><th className='px-3 py-2 border'>Action</th>
          </tr>
        </thead>
        <tbody>{docs.length ? docs.map((d) => <tr key={d.id}>
          <td className='border px-3 py-2'>{documentTypeLabel(d)}</td><td className='border px-3 py-2'>{d.document_number || '-'}</td><td className='border px-3 py-2'>{d.issue_date || '-'}</td><td className='border px-3 py-2'>{d.expiry_date || '-'}</td><td className='border px-3 py-2'>{d.verification_status || 'pending'}</td><td className='border px-3 py-2'>{d.original_filename || '-'}</td>
          <td className='border px-3 py-2'>
            <div className='flex flex-wrap gap-2'>
              <a href={route('apps.procurement.vendors.documents.download', [vendor.id, d.id])} target='_blank' className='rounded border border-gray-300 px-2 py-1 text-xs'>View</a>
              <button type='button' onClick={() => deleteDocument(d.id)} className='rounded border border-red-300 px-2 py-1 text-xs text-red-700'>Delete</button>
              <label className='inline-flex items-center gap-1 text-xs'><input type='checkbox' checked={d.verification_status === 'valid'} onChange={(e) => verifyDocument(d.id, e.target.checked ? 'valid' : 'pending')} />Approve</label>
            </div>
          </td>
        </tr>) : <tr><td className='border px-2 py-3 text-center text-gray-500' colSpan={7}>Belum ada dokumen tersimpan.</td></tr>}</tbody>
      </table>
    </div>
  </div>;
}
