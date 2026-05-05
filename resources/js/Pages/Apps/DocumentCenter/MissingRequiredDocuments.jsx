import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function MissingRequiredDocuments() {
  const [items, setItems] = useState([]);
  useEffect(() => { fetch('/apps/document-center/missing-required').then((r) => r.json()).then(setItems).catch(() => setItems([])); }, []);
  return <AppLayout><Head title='Missing Required Documents' /><div className='p-6'><h1 className='text-xl font-semibold mb-4'>Missing Required Documents</h1><table className='min-w-full text-sm border'><thead><tr className='bg-gray-100'><th className='border p-2'>Owner Type</th><th className='border p-2'>Owner</th><th className='border p-2'>Missing</th><th className='border p-2'>Completion</th></tr></thead><tbody>{items.map((it) => <tr key={`${it.owner_type}-${it.owner_id}`}><td className='border p-2'>{it.owner_type}</td><td className='border p-2'>{it.owner_name}</td><td className='border p-2'>{(it.missing_document_types || []).join(', ')}</td><td className='border p-2'>{it.completion_percentage}%</td></tr>)}</tbody></table></div></AppLayout>;
}
