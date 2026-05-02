import { Link } from '@inertiajs/react';

export default function VendorHeader({ vendor }) {
  const badges = [
    vendor.status === 'active' && 'Active',
    vendor.qualification_status === 'qualified' && 'Qualified',
    vendor.status === 'blacklist' && 'Blacklist',
  ].filter(Boolean);

  return <div className='sticky top-0 z-10 bg-white border rounded-lg p-4 shadow-sm'>
    <div className='flex flex-wrap items-start justify-between gap-4'>
      <div>
        <h1 className='text-2xl font-bold'>{vendor.vendor_name}</h1>
        <p className='text-sm text-gray-600'>{vendor.vendor_code} • {vendor.vendor_type}</p>
        <p className='text-sm mt-1'>NPWP: {vendor.npwp_number || '-'} • NIB: {vendor.nib_number || '-'} • {vendor.city || '-'} • {vendor.phone || '-'}</p>
      </div>
      <div className='flex flex-col items-end gap-2'>
        <Link href={route('apps.procurement.vendors.index')} className='px-3 py-2 text-sm rounded bg-white border'>Back to List</Link>
        <div className='flex flex-wrap justify-end gap-2'>
        {badges.map((b) => <span key={b} className='px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700'>{b}</span>)}
        {vendor.qualification_status !== 'qualified' && <span className='px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700'>Vendor belum qualified</span>}
        </div>
      </div>
    </div>
  </div>
}
