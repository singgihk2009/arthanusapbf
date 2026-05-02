import { Link } from '@inertiajs/react';

const map={compliant:'Compliant',warning:'Warning',expiring_soon:'Expiring Soon',expired:'Expired',blocked:'Blocked'};

export default function VendorHeader({ vendor }) {
  const compliance = vendor?.compliance?.compliance_status;
  return <div className='sticky top-0 z-10 bg-white border rounded-lg p-4 shadow-sm'>
    <div className='flex flex-wrap items-start justify-between gap-4'>
      <div>
        <h1 className='text-2xl font-bold'>{vendor.vendor_name}</h1>
        <p className='text-sm text-gray-600'>{vendor.vendor_code} • {vendor.vendor_type}</p>
      </div>
      <div className='flex flex-col items-end gap-2'>
        <Link href='/apps/procurement/vendors' className='px-3 py-2 text-sm rounded bg-white border'>Back to List</Link>
        {compliance && <span className='px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700'>{map[compliance]||'Missing Document'}</span>}
      </div>
    </div>
    {compliance==='blocked' && <div className='mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-2'>Vendor memiliki dokumen kritikal yang belum lengkap/expired sehingga tidak bisa digunakan untuk PO baru.</div>}
  </div>
}
