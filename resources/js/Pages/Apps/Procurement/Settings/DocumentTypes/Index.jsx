import AppLayout from '@/Layouts/AppLayout';
import { usePage } from '@inertiajs/react';
export default function Index(){const {documentTypes}=usePage().props; return <AppLayout><div className='p-6'><h1 className='text-xl font-semibold mb-3'>Document Types</h1><pre className='text-xs bg-gray-50 p-3 rounded'>{JSON.stringify(documentTypes, null, 2)}</pre></div></AppLayout>}
