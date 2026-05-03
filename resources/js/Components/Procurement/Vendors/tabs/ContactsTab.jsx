import { useState } from 'react';
import { router } from '@inertiajs/react';

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

export default function ContactsTab({ data, vendor }) {
  const contacts = data?.contacts || [];
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(initialForm);

  const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const submit = (e) => {
    e.preventDefault();
    router.post(`/apps/procurement/vendors/${vendor.id}/party-contacts`, form, {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setForm(initialForm);
      },
    });
  };

  return <div className='space-y-3'>
    <div className='flex justify-between items-center'>
      <h3 className='font-semibold'>Vendor Contacts</h3>
      <button className='px-3 py-1 rounded bg-indigo-600 text-white text-sm' onClick={() => setShowForm((v) => !v)}>
        {showForm ? 'Cancel' : 'Add Contact'}
      </button>
    </div>

    {showForm && (
      <form onSubmit={submit} className='border rounded p-3 bg-gray-50 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm'>
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
        <div className='md:col-span-2'>
          <button type='submit' className='px-3 py-1 rounded bg-indigo-600 text-white text-sm'>Save Contact</button>
        </div>
      </form>
    )}

    <div className='overflow-auto'>
      <table className='min-w-full text-sm border'>
        <thead className='bg-gray-50'><tr>
          {['Full Name','Position','Department','Email','Mobile/Phone','Contact Role','Primary','Can Login','Status','Action'].map(h => <th key={h} className='text-left p-2 border-b'>{h}</th>)}
        </tr></thead>
        <tbody>
        {contacts.length === 0 && <tr><td colSpan={10} className='p-3 text-gray-500'>No contacts found.</td></tr>}
        {contacts.map(pc => <tr key={pc.id} className='border-b'>
          <td className='p-2'>{pc.contact?.full_name || '-'}</td><td className='p-2'>{pc.contact?.position_title || '-'}</td><td className='p-2'>{pc.contact?.department || '-'}</td><td className='p-2'>{pc.contact?.email || '-'}</td><td className='p-2'>{pc.contact?.mobile || pc.contact?.phone || '-'}</td><td className='p-2'>{pc.contact_role || '-'}</td><td className='p-2'>{pc.is_primary ? 'Yes' : 'No'}</td><td className='p-2'>{pc.can_login ? 'Yes' : 'No'}</td><td className='p-2'>{pc.status}</td>
          <td className='p-2 space-x-2'>
            <button onClick={() => router.post(`/apps/procurement/vendors/${vendor.id}/party-contacts/${pc.id}/set-primary`)} className='text-xs text-indigo-600'>Set Primary</button>
            <button onClick={() => router.post(`/apps/procurement/vendors/${vendor.id}/party-contacts/${pc.id}/toggle-status`)} className='text-xs text-amber-600'>Enable/Disable</button>
            <button onClick={() => router.post(`/apps/procurement/vendors/${vendor.id}/party-contacts/${pc.id}/toggle-can-login`)} className='text-xs text-blue-600'>Toggle Login</button>
            <button onClick={() => router.delete(`/apps/procurement/vendors/${vendor.id}/party-contacts/${pc.id}`)} className='text-xs text-red-600'>Remove</button>
          </td>
        </tr>)}
        </tbody>
      </table>
    </div>
  </div>;
}
