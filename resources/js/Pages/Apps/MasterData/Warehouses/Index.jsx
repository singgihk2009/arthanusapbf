import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import hasAnyPermission from '@/Utils/Permissions';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react';
import React from 'react';

export default function Index() {
    const { warehouses } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = warehouses.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.warehouses.destroy', ids));
    };

    return (
        <>
            <Head title="Master Warehouse" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3">
                    <Search url={route('apps.master-data.warehouses.index')} placeholder="Cari warehouse..." />
                </div>
                <div className="flex gap-2">
                    {hasAnyPermission(['master-warehouse-create']) && (
                        <Button type="link" href={route('apps.master-data.warehouses.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                    )}
                    {hasAnyPermission(['master-warehouse-delete']) && (
                        <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />
                    )}
                </div>
            </div>

            <Table.Card title={'Data Warehouse'}>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className="w-10">No</Table.Th>
                            <Table.Th>Kode</Table.Th>
                            <Table.Th>Nama</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className="w-32"></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {warehouses.data.length ? warehouses.data.map((warehouse, i) => (
                            <tr key={warehouse.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (warehouses.current_page - 1) * warehouses.per_page}</Table.Td>
                                <Table.Td>{warehouse.code}</Table.Td>
                                <Table.Td>{warehouse.name}</Table.Td>
                                <Table.Td>{warehouse.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>
                                    <div className="flex gap-2">
                                        {hasAnyPermission(['master-warehouse-update']) && <Button type="edit" href={route('apps.master-data.warehouses.edit', warehouse.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />}
                                        {hasAnyPermission(['master-warehouse-delete']) && <Button type="delete" url={route('apps.master-data.warehouses.destroy', warehouse.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />}
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : (
                            <Table.Empty colSpan={5} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data warehouse tidak ditemukan.</span></>} />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {warehouses.last_page !== 1 && <Pagination links={warehouses.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
