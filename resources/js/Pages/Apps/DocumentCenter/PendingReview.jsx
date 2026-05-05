import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

export default function PendingReview() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);

  const loadRows = async () => {
    setLoading(true);
    try {
      const res = await fetch('/apps/document-center/documents/pending-review/list');
      const json = await res.json();
      setRows(json || []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadRows(); }, []);

  const canAction = useMemo(() => true, []);

  const verify = async (id) => {
    if (!confirm('Are you sure you want to verify this document?')) return;
    const res = await fetch(`/apps/document-center/documents/${id}/verify`, { method: 'POST', headers: { 'Content-Type': 'application/json' } });
    if (res.ok) loadRows();
  };

  const reject = async (id) => {
    const reason = prompt('Reject reason (minimum 5 characters)');
    if (!reason || reason.length < 5) return;
    const res = await fetch(`/apps/document-center/documents/${id}/reject`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ rejected_reason: reason }) });
    if (res.ok) loadRows();
  };

  return <AppLayout><Head title='Pending Review Documents' /><div className='p-6'>
    <h1 className='text-xl font-semibold mb-4'>Document Center - Pending Review</h1>
    {loading && <div className='text-sm text-gray-500 mb-3'>Loading...</div>}
    <table className='min-w-full text-sm border'>
      <thead><tr className='bg-gray-100'>
        <th className='border p-2'>Owner Type</th><th className='border p-2'>Owner Name</th><th className='border p-2'>Document Type</th>
        <th className='border p-2'>Document Number</th><th className='border p-2'>Uploaded At</th><th className='border p-2'>Uploaded By</th>
        <th className='border p-2'>Expiry Date</th><th className='border p-2'>Status</th><th className='border p-2'>Action</th>
      </tr></thead>
      <tbody>{rows.map((r) => <tr key={r.id}>
        <td className='border p-2'>{r.owner_type}</td><td className='border p-2'>{r.owner_name || '-'}</td><td className='border p-2'>{r.type?.name || '-'}</td>
        <td className='border p-2'>{r.document_number || '-'}</td><td className='border p-2'>{formatDate(r.created_at)}</td><td className='border p-2'>{r.uploaded_by?.name || '-'}</td>
        <td className='border p-2'>{formatDate(r.expiry_date)}</td><td className='border p-2'>{r.status}</td>
        <td className='border p-2 space-x-2'>
          <a className='text-blue-600 underline' href={`/apps/document-center/documents/${r.id}/download`}>Download</a>
          {canAction && <button className='text-green-700 underline' onClick={() => verify(r.id)}>Accept</button>}
          {canAction && <button className='text-red-700 underline' onClick={() => reject(r.id)}>Reject</button>}
        </td>
      </tr>)}</tbody>
    </table>
  </div></AppLayout>;
}
