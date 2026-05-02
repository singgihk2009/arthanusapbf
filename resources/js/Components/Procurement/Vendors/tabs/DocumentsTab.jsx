import { router } from '@inertiajs/react';

export default function DocumentsTab({ vendor }) {
  const requirements = vendor?.compliance?.required_documents ?? vendor?.required_documents ?? [];
  const docs = vendor?.documents ?? [];
  const byType = new Map(docs.map((d) => [d.document_type_id, d]));

  return <div className='overflow-auto'><table className='min-w-full text-sm border'>
    <thead><tr className='bg-gray-100'>{['Required','Document Type','Document Number','Issue Date','Expiry Date','Verification Status','Compliance Status','File','Action'].map(h=><th key={h} className='px-2 py-2 border'>{h}</th>)}</tr></thead>
    <tbody>{requirements.map((r)=>{const d=byType.get(r.document_type_id); const status=!d?'Missing':(d.verification_status||'pending'); return <tr key={r.id}><td className='border px-2'>{r.is_required?'Yes':'No'}</td><td className='border px-2'>{r.document_type?.name||r.document_type?.code}</td><td className='border px-2'>{d?.document_number||'-'}</td><td className='border px-2'>{d?.issue_date||'-'}</td><td className='border px-2'>{d?.expiry_date||'-'}</td><td className='border px-2'>{d?.verification_status||'pending'}</td><td className='border px-2'>{status}</td><td className='border px-2'>{d?.original_filename||'-'}</td><td className='border px-2'>{d?'Replace / Preview / Download / Verify / Reject':'Upload'}</td></tr>})}</tbody>
  </table></div>;
}
