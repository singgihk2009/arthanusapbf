import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
export default function Page(props){return <AppLayout><div className='p-6'><h1 className='text-xl font-semibold'>Sales Module</h1><pre className='text-xs mt-2 bg-gray-100 p-3 rounded'>{JSON.stringify(props,null,2)}</pre></div></AppLayout>}
