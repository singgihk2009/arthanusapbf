import { useState } from 'react';
import { router } from '@inertiajs/react';
import toast from 'react-hot-toast';

const initialForm = {
  full_name: '',
  email: '',
  phone: '',
  mobile: '',
  position_title: '',
  department: '',
  contact_role: '',
  notes: '',
  is_primary: false,
  can_login: false,
  status: 'active',
};

export default function ContactsTab({ data, vendor, onRefresh }) {
  const contacts = data?.contacts || [];
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(initialForm);
  const [editingContactId, setEditingContactId] = useState(null);

  const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const submit = (e) => {
    e.preventDefault();
    const endpoint = editingContactId
      ? `/apps/procurement/vendors/${vendor.id}/party-contacts/${editingContactId}`
      : `/apps/procurement/vendors/${vendor.id}/party-contacts`;
    const method = editingContactId ? router.put : router.post;

    method(endpoint, form, {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setForm(initialForm);
        setEditingContactId(null);
        onRefresh?.();
        toast.success(editingContactId ? 'Contact berhasil diperbarui.' : 'Contact berhasil ditambahkan.');
      },
      onError: (errors) => {
        const firstError = Object.values(errors || {})[0];
        toast.error(firstError || 'Gagal menyimpan contact. Periksa data yang diisi.');
      },
    });
  };

  const startEdit = (pc) => {
    setEditingContactId(pc.id);
    setShowForm(true);
    setForm({
      full_name: pc.contact?.full_name || '',
      email: pc.contact?.email || '',
      phone: pc.contact?.phone || '',
      mobile: pc.contact?.mobile || '',
      position_title: pc.contact?.position_title || '',
      department: pc.contact?.department || '',
      contact_role: pc.contact_role || '',
      notes: pc.notes || '',
      is_primary: !!pc.is_primary,
      can_login: !!pc.can_login,
      status: pc.status || 'active',
    });
  };

  return <div className='space-y-3'>
    <div className='flex justify-between items-center'>
      <h3 className='font-semibold'>Vendor Contacts</h3>
      <button type='button' className='px-3 py-1 rounded bg-indigo-600 text-white text-sm' onClick={() => { if (showForm) { setEditingContactId(null); setForm(initialForm); } setShowForm((v) => !v); }}>
        {showForm ? 'Cancel' : 'Add Contact'}
      </button>
    </div>

    {showForm && (
      <form onSubmit={submit} className='border rounded p-3 bg-gray-50 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-4'>
        <input className='border rounded p-2' placeholder='Full name *' value={form.full_name} onChange={(e) => setField('full_name', e.target.value)} required />
        <input className='border rounded p-2' placeholder='Email' type='email' value={form.email} onChange={(e) => setField('email', e.target.value)} />
        <input className='border rounded p-2' placeholder='Mobile' value={form.mobile} onChange={(e) => setField('mobile', e.target.value)} />
        <input className='border rounded p-2' placeholder='Phone' value={form.phone} onChange={(e) => setField('phone', e.target.value)} />
        <input className='border rounded p-2' placeholder='Position' value={form.position_title} onChange={(e) => setField('position_title', e.target.value)} />
        <input className='border rounded p-2' placeholder='Department' value={form.department} onChange={(e) => setField('department', e.target.value)} />
        <input className='border rounded p-2' placeholder='Contact role' value={form.contact_role} onChange={(e) => setField('contact_role', e.target.value)} />
        <select className='border rounded p-2' value={form.status} onChange={(e) => setField('status', e.target.value)}>
          <option value='active'>Active</option>
          <option value='inactive'>Inactive</option>
        </select>
        <textarea className='border rounded p-2 md:col-span-2' placeholder='Notes' value={form.notes} onChange={(e) => setField('notes', e.target.value)} />
        <label className='inline-flex items-center gap-2'>
          <input type='checkbox' checked={form.is_primary} onChange={(e) => setField('is_primary', e.target.checked)} />
          <span>Primary contact</span>
        </label>
        <label className='inline-flex items-center gap-2'>
          <input type='checkbox' checked={form.can_login} onChange={(e) => setField('can_login', e.target.checked)} />
          <span>Can login</span>
        </label>
        <div className='md:col-span-2 flex items-center gap-2'>
          <button type='submit' className='px-3 py-1 rounded bg-indigo-600 text-white text-sm'>{editingContactId ? 'Update Contact' : 'Save Contact'}</button>
          <button type='button' className='px-3 py-1 rounded border text-sm' onClick={() => { setEditingContactId(null); setForm(initialForm); setShowForm(false); }}>Cancel</button>
        </div>
      </form>
    )}

    <div className='overflow-auto mt-2'>
      <table className='min-w-full text-sm border'>
        <thead className='bg-gray-50'><tr>
          {['Full Name','Position','Email','Mobile/Phone','Status','Action'].map(h => <th key={h} className='text-left p-2 border-b'>{h}</th>)}
        </tr></thead>
        <tbody>
        {contacts.length === 0 && <tr><td colSpan={6} className='p-3 text-gray-500'>No contacts found.</td></tr>}
        {contacts.map(pc => <tr key={pc.id} className='border-b'>
          <td className='p-2'>{pc.contact?.full_name || '-'}</td><td className='p-2'>{pc.contact?.position_title || '-'}</td><td className='p-2'>{pc.contact?.email || '-'}</td><td className='p-2'>{pc.contact?.mobile || pc.contact?.phone || '-'}</td><td className='p-2'>{pc.status}</td>
          <td className='p-2 space-x-2'>
            <button type='button' onClick={() => startEdit(pc)} className='text-xs text-indigo-600'>Edit</button>
            <button type='button' onClick={() => router.delete(`/apps/procurement/vendors/${vendor.id}/party-contacts/${pc.id}`, {
              preserveScroll: true,
              onSuccess: () => {
                onRefresh?.();
                toast.success('Contact berhasil dihapus.');
              },
              onError: (errors) => {
                const firstError = Object.values(errors || {})[0];
                toast.error(firstError || 'Gagal menghapus contact.');
              },
            })} className='text-xs text-red-600'>Remove</button>
          </td>
        </tr>)}
        </tbody>
      </table>
    </div>
  </div>;
}
