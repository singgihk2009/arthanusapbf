import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
export default function Index({auth,sources}){return <AuthenticatedLayout user={auth.user}><div className='p-6'><h1 className='text-xl font-bold'>Regulatory Sources</h1><ul>{sources.data.map(s=><li key={s.id}>{s.source_name}</li>)}</ul></div></AuthenticatedLayout>}
