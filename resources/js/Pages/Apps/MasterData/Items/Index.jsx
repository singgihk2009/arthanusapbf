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
    const { items } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = items.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.items.destroy', ids));
    };

    return (
        <>
            <Head title="Master Item" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.master-data.items.index')} placeholder="Cari item..." /></div>
                <div className="flex gap-2">
                    {hasAnyPermission(['master-item-create']) && <Button type="link" href={route('apps.master-data.items.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />}
                    {hasAnyPermission(['master-item-delete']) && <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />}
                </div>
            </div>

            <Table.Card title={'Data Item'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>SKU</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Kategori</Table.Th><Table.Th>Base UOM</Table.Th><Table.Th>Status</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {items.data.length ? items.data.map((item, i) => (
                            <tr key={item.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (items.current_page - 1) * items.per_page}</Table.Td>
                                <Table.Td>{item.sku}</Table.Td>
                                <Table.Td>{item.name}</Table.Td>
                                <Table.Td>{item.category?.name ?? '-'}</Table.Td>
                                <Table.Td>{item.base_uom?.code ?? '-'}</Table.Td>
                                <Table.Td>{item.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td><div className="flex gap-2">{hasAnyPermission(['master-item-update']) && <Button type="edit" href={route('apps.master-data.items.edit', item.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />}{hasAnyPermission(['master-item-delete']) && <Button type="delete" url={route('apps.master-data.items.destroy', item.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />}</div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={7} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data item tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {items.last_page !== 1 && <Pagination links={items.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
