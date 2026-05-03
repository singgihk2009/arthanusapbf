import { router } from '@inertiajs/react';

export default function ContactsTab({ data, vendor }) {
  const contacts = data?.contacts || [];

  return <div className='space-y-3'>
    <div className='flex justify-between items-center'>
      <h3 className='font-semibold'>Vendor Contacts</h3>
      <button className='px-3 py-1 rounded bg-indigo-600 text-white text-sm' onClick={() => alert('Form Add/Edit Contact akan dihubungkan ke endpoint party-contacts.')}>Add Contact</button>
    </div>
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
