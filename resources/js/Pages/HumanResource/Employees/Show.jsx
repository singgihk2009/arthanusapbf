import AppLayout from '@/Layouts/AppLayout';
import Table from '@/Components/Table';
import { Head, usePage } from '@inertiajs/react';

export default function Show() {
    const { employee } = usePage().props;

    const infoRows = [
        ['Employee Code', employee.employee_code],
        ['NIK', employee.nik],
        ['Email', employee.email],
        ['Phone', employee.phone],
        ['Address', employee.address],
        ['Position', employee.position?.name],
        ['Department', employee.department?.name],
        ['Status', employee.is_active ? 'Active' : 'Inactive'],
    ];

    return <>
        <Head title='Employee Card' />

        <div className='space-y-4'>
            <h1 className='text-2xl font-semibold text-gray-900 dark:text-gray-100'>Employee Card</h1>

            <div className='grid gap-3 md:grid-cols-3'>
                <div className='rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950 md:col-span-2'>
                    <div className='text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'>Employee Name</div>
                    <div className='mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100'>{employee.full_name || '-'}</div>
                    <div className='mt-1 text-sm text-gray-600 dark:text-gray-300'>
                        {employee.position?.name || '-'} • {employee.department?.name || '-'}
                    </div>
                </div>

                <div className='rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950'>
                    <div className='text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'>Licenses</div>
                    <div className='mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100'>{employee.licenses?.length ?? 0}</div>
                </div>
            </div>

            <Table.Card title='Employee Details'>
                <div className='grid gap-3 md:grid-cols-2'>
                    {infoRows.map(([label, value]) => <div key={label} className='rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900'>
                        <div className='text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'>{label}</div>
                        <div className='mt-1 text-sm font-medium text-gray-900 dark:text-gray-100'>{value || '-'}</div>
                    </div>)}
                </div>
            </Table.Card>
        </div>
    </>;
}

Show.layout = (page) => <AppLayout children={page} />;
