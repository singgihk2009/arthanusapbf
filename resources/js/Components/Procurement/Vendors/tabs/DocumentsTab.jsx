import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DocumentsTab({ vendor }) {
  const requirements = vendor?.compliance?.required_documents ?? vendor?.required_documents ?? [];
  const docs = vendor?.documents ?? [];
  const byType = new Map(docs.map((d) => [d.document_type_id, d]));
  const [forms, setForms] = useState({});
  const [customForm, setCustomForm] = useState({
    document_type: '',
    document_number: '',
    issue_date: '',
    expiry_date: '',
  });
  const fileInputs = useRef({});
  const customFileInput = useRef(null);

  const rowForm = (documentTypeId) => forms[documentTypeId] ?? {
    document_number: '',
    issue_date: '',
    expiry_date: '',
  };

  const updateForm = (documentTypeId, key, value) => {
    setForms((prev) => ({
      ...prev,
      [documentTypeId]: {
        ...rowForm(documentTypeId),
        [key]: value,
      },
    }));
  };

  const submitUpload = (payload, fileInput) => {
    if (!fileInput?.files?.[0]) {
      return;
    }

    router.post(route('apps.procurement.vendors.documents.upload', vendor.id), {
      ...payload,
      file: fileInput.files[0],
    }, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        fileInput.value = '';
      },
    });
  };

  const submitRequirementUpload = (documentTypeId) => {
    const fileInput = fileInputs.current[documentTypeId];
    const currentForm = rowForm(documentTypeId);

    submitUpload({
      document_type_id: documentTypeId,
      document_number: currentForm.document_number || null,
      issue_date: currentForm.issue_date || null,
      expiry_date: currentForm.expiry_date || null,
    }, fileInput);
  };

  const submitCustomUpload = () => {
    if (!customForm.document_type.trim()) {
      window.alert('Isi Document Type terlebih dahulu.');
      return;
    }

    submitUpload({
      document_type: customForm.document_type,
      document_number: customForm.document_number || null,
      issue_date: customForm.issue_date || null,
      expiry_date: customForm.expiry_date || null,
    }, customFileInput.current);
  };

  const verifyDocument = (documentId, verificationStatus) => {
    router.post(route('apps.procurement.vendors.documents.verify', [vendor.id, documentId]), { verification_status: verificationStatus }, { preserveScroll: true });
  };

  const deleteDocument = (documentId) => {
    if (!window.confirm('Hapus dokumen ini?')) return;

    router.delete(route('apps.procurement.vendors.documents.delete', [vendor.id, documentId]), { preserveScroll: true });
  };

  return <div className='space-y-4'>
    <div className='rounded border p-3'>
      <div className='mb-2 text-sm font-semibold'>Tambah Dokumen Baru</div>
      <div className='grid gap-2 md:grid-cols-5'>
        <input value={customForm.document_type} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_type: e.target.value }))} placeholder='Document Type (contoh: NIB)' className='rounded border px-2 py-1' />
        <input value={customForm.document_number} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_number: e.target.value }))} placeholder='Document Number' className='rounded border px-2 py-1' />
        <input type='date' value={customForm.issue_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, issue_date: e.target.value }))} className='rounded border px-2 py-1' />
        <input type='date' value={customForm.expiry_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, expiry_date: e.target.value }))} className='rounded border px-2 py-1' />
        <input ref={customFileInput} type='file' accept='.pdf,.jpg,.jpeg,.png' className='rounded border px-2 py-1' />
      </div>
      <button type='button' onClick={submitCustomUpload} className='mt-2 rounded border border-blue-300 px-2 py-1 text-xs text-blue-700'>Upload Dokumen Baru</button>
    </div>

    <div className='overflow-auto'><table className='min-w-full text-sm border'>
      <thead><tr className='bg-gray-100'>{['Required','Document Type','Document Number','Issue Date','Expiry Date','Verification Status','Compliance Status','File','Action'].map(h=><th key={h} className='px-2 py-2 border'>{h}</th>)}</tr></thead>
      <tbody>{requirements.length > 0 ? requirements.map((r)=>{
        const d=byType.get(r.document_type_id);
        const status=!d?'Missing':(d.verification_status||'pending');
        const form = rowForm(r.document_type_id);

        return <tr key={r.id}>
          <td className='border px-2'>{r.is_required?'Yes':'No'}</td>
          <td className='border px-2'>{r.document_type?.name||r.document_type?.code}</td>
          <td className='border px-2'><input value={form.document_number || d?.document_number || ''} onChange={(e) => updateForm(r.document_type_id, 'document_number', e.target.value)} className='w-full rounded border px-2 py-1' /></td>
          <td className='border px-2'><input type='date' value={form.issue_date || d?.issue_date || ''} onChange={(e) => updateForm(r.document_type_id, 'issue_date', e.target.value)} className='w-full rounded border px-2 py-1' /></td>
          <td className='border px-2'><input type='date' value={form.expiry_date || d?.expiry_date || ''} onChange={(e) => updateForm(r.document_type_id, 'expiry_date', e.target.value)} className='w-full rounded border px-2 py-1' /></td>
          <td className='border px-2'>{d?.verification_status||'pending'}</td>
          <td className='border px-2'>{status}</td>
          <td className='border px-2'><div className='space-y-1'><div>{d?.original_filename||'-'}</div><input ref={(el) => { fileInputs.current[r.document_type_id] = el; }} type='file' accept='.pdf,.jpg,.jpeg,.png' className='w-full' /></div></td>
          <td className='border px-2'><div className='flex flex-wrap gap-1'>
            <button type='button' onClick={() => submitRequirementUpload(r.document_type_id)} className='rounded border border-blue-300 px-2 py-1 text-xs text-blue-700'>{d ? 'Replace' : 'Upload'}</button>
            {d && <a href={route('apps.procurement.vendors.documents.download', [vendor.id, d.id])} target='_blank' className='rounded border border-gray-300 px-2 py-1 text-xs'>Preview/Download</a>}
            {d && <button type='button' onClick={() => verifyDocument(d.id, 'valid')} className='rounded border border-green-300 px-2 py-1 text-xs text-green-700'>Verify</button>}
            {d && <button type='button' onClick={() => verifyDocument(d.id, 'need_revision')} className='rounded border border-amber-300 px-2 py-1 text-xs text-amber-700'>Reject</button>}
            {d && <button type='button' onClick={() => deleteDocument(d.id)} className='rounded border border-red-300 px-2 py-1 text-xs text-red-700'>Delete</button>}
          </div></td>
        </tr>}) : <tr><td className='border px-2 py-4 text-center text-gray-500' colSpan={9}>Belum ada requirement dokumen. Gunakan form "Tambah Dokumen Baru" di atas untuk menambahkan dokumen.</td></tr>}</tbody>
    </table></div>
  </div>;
}
