import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export default function RequirementSetup({ ownerTypes = [] }) {
  const [ownerType, setOwnerType] = useState(ownerTypes[0] || 'vendor');
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState(null);

  const canSave = useMemo(() => !!ownerType && rows.length > 0, [ownerType, rows.length]);

  const loadMatrix = async (selectedOwnerType) => {
    setLoading(true);
    setNotice(null);
    try {
      const res = await fetch(`/apps/document-center/requirements/${selectedOwnerType}/matrix`);
      const data = await res.json();
      setRows(Array.isArray(data) ? data : []);
    } catch {
      setNotice({ type: 'error', text: 'Gagal memuat matrix requirement.' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (ownerType) loadMatrix(ownerType);
  }, [ownerType]);

  const updateRow = (idx, field, value) => {
    setRows((prev) => prev.map((row, i) => {
      if (i !== idx) return row;
      const next = { ...row, [field]: value };
      if (field === 'is_active' && !value) {
        next.is_required = false;
        next.is_expirable = false;
        next.requires_verification = false;
      }
      return next;
    }));
  };

  const save = async () => {
    setLoading(true);
    setNotice(null);
    try {
      const res = await fetch('/apps/document-center/requirements/bulk-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify({ owner_type: ownerType, requirements: rows }),
      });
      if (!res.ok) throw new Error('save_failed');
      setNotice({ type: 'success', text: 'Setup requirement berhasil disimpan.' });
    } catch {
      setNotice({ type: 'error', text: 'Gagal menyimpan setup requirement.' });
    } finally {
      setLoading(false);
    }
  };

  return <AppLayout>
    <Head title='Document Requirement Setup' />
    <div className='p-6 space-y-4'>
      <div className='flex items-center justify-between'>
        <h1 className='text-xl font-semibold'>Document Requirement Setup</h1>
        <button disabled={!canSave || loading} onClick={save} className='rounded border border-blue-300 px-3 py-2 text-sm text-blue-700 disabled:opacity-50'>Save Setup</button>
      </div>
      {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{notice.text}</div>}
      <div className='max-w-xs'>
        <label className='mb-1 block text-sm font-medium'>Owner Type / Module</label>
        <select className='w-full rounded border px-2 py-2' value={ownerType} onChange={(e) => setOwnerType(e.target.value)}>
          {ownerTypes.map((v) => <option key={v} value={v}>{v}</option>)}
        </select>
      </div>
      <div className='overflow-auto rounded border'>
        <table className='min-w-full text-sm'>
          <thead className='bg-gray-100'>
            <tr><th className='p-2'>No</th><th className='p-2'>Code</th><th className='p-2'>Name</th><th className='p-2'>Category</th><th className='p-2'>Active</th><th className='p-2'>Required</th><th className='p-2'>Expirable</th><th className='p-2'>Verification</th><th className='p-2'>Reminder</th><th className='p-2'>Sort</th><th className='p-2'>Notes</th></tr>
          </thead>
          <tbody>
            {rows.map((row, idx) => <tr key={row.document_type_id} className='border-t'>
              <td className='p-2'>{idx + 1}</td><td className='p-2'>{row.code}</td><td className='p-2'>{row.name}</td><td className='p-2'>{row.category || '-'}</td>
              <td className='p-2 text-center'><input type='checkbox' checked={!!row.is_active} onChange={(e) => updateRow(idx, 'is_active', e.target.checked)} /></td>
              <td className='p-2 text-center'><input type='checkbox' disabled={!row.is_active} checked={!!row.is_required} onChange={(e) => updateRow(idx, 'is_required', e.target.checked)} /></td>
              <td className='p-2 text-center'><input type='checkbox' disabled={!row.is_active} checked={!!row.is_expirable} onChange={(e) => updateRow(idx, 'is_expirable', e.target.checked)} /></td>
              <td className='p-2 text-center'><input type='checkbox' disabled={!row.is_active} checked={!!row.requires_verification} onChange={(e) => updateRow(idx, 'requires_verification', e.target.checked)} /></td>
              <td className='p-2'><input type='number' className='w-20 rounded border px-2 py-1' disabled={!row.is_expirable} value={row.reminder_days_before_expiry ?? 30} onChange={(e) => updateRow(idx, 'reminder_days_before_expiry', Number(e.target.value || 0))} /></td>
              <td className='p-2'><input type='number' className='w-20 rounded border px-2 py-1' value={row.sort_order ?? 0} onChange={(e) => updateRow(idx, 'sort_order', Number(e.target.value || 0))} /></td>
              <td className='p-2'><input className='w-full rounded border px-2 py-1' value={row.notes || ''} onChange={(e) => updateRow(idx, 'notes', e.target.value)} /></td>
            </tr>)}
            {!rows.length && <tr><td colSpan={11} className='p-4 text-center text-gray-500'>{loading ? 'Loading...' : 'Tidak ada data.'}</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>;
}
