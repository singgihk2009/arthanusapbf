import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff } from '@tabler/icons-react';

export default function Index() {
    const { employees, stats } = usePage().props;

    return <>
        <Head title='Employees' />

        <h1 className='mb-4 text-2xl font-semibold text-gray-900 dark:text-gray-100'>Employee</h1>

        <div className='mb-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4'>
            {Object.entries(stats ?? {}).map(([key, value]) => <div key={key} className='rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950'>
                <div className='text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'>{key.replace('_', ' ')}</div>
                <div className='mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100'>{value}</div>
            </div>)}
        </div>

        <div className='mb-5 flex justify-end'>
            <Button
                type='link'
                href='/apps/human-resource/employees/create'
                icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                variant='gray'
                label='Tambah Employee'
            />
        </div>

        <Table.Card title='Data Employee'>
            <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th className='w-10'>No</Table.Th>
                        <Table.Th>Code</Table.Th>
                        <Table.Th>Nama</Table.Th>
                        <Table.Th>NIK</Table.Th>
                        <Table.Th>Jabatan</Table.Th>
                        <Table.Th>Dept</Table.Th>
                        <Table.Th>Kontak</Table.Th>
                        <Table.Th>Status</Table.Th>
                        <Table.Th>Aksi</Table.Th>
                    </tr>
                </Table.Thead>
                <Table.Tbody>
                    {employees.data.length ? employees.data.map((employee, index) => <tr key={employee.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                        <Table.Td className='text-center'>{index + 1 + (employees.current_page - 1) * employees.per_page}</Table.Td>
                        <Table.Td>{employee.employee_code || '-'}</Table.Td>
                        <Table.Td>{employee.full_name || '-'}</Table.Td>
                        <Table.Td>{employee.nik || '-'}</Table.Td>
                        <Table.Td>{employee.position?.name || '-'}</Table.Td>
                        <Table.Td>{employee.department?.name || '-'}</Table.Td>
                        <Table.Td>{employee.email || employee.phone || '-'}</Table.Td>
                        <Table.Td>{employee.is_active ? 'Active' : 'Inactive'}</Table.Td>
                        <Table.Td>
                            <Link href={`/apps/human-resource/employees/${employee.id}`} className='text-indigo-600 hover:underline'>View</Link>
                        </Table.Td>
                    </tr>) : <Table.Empty colSpan={9} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white' /><span className='text-gray-500'>Data employee tidak ditemukan.</span></>} />}
                </Table.Tbody>
            </Table>
        </Table.Card>

        {employees.last_page !== 1 && <Pagination links={employees.links} />}
    </>;
}

Index.layout = (page) => <AppLayout children={page} />;
