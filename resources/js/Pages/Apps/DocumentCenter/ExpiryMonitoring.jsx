import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ExpiryMonitoring() {
  const [items, setItems] = useState([]);
  useEffect(() => { fetch('/apps/document-center/expiring-soon').then((r) => r.json()).then(setItems).catch(() => setItems([])); }, []);
  return <AppLayout><Head title='Expiry Monitoring' /><div className='p-6'><h1 className='text-xl font-semibold mb-4'>Expiry Monitoring</h1><table className='min-w-full text-sm border'><thead><tr className='bg-gray-100'><th className='border p-2'>Owner Type</th><th className='border p-2'>Owner ID</th><th className='border p-2'>Document</th><th className='border p-2'>Expiry Date</th></tr></thead><tbody>{items.map((it) => <tr key={it.id}><td className='border p-2'>{it.owner_type}</td><td className='border p-2'>{it.owner_id}</td><td className='border p-2'>{it.document_type?.name || it.title}</td><td className='border p-2'>{it.expiry_date}</td></tr>)}</tbody></table></div></AppLayout>;
}
