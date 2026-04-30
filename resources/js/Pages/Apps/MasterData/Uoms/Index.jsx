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
    const { uoms } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = uoms.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.uoms.destroy', ids));
    };

    return (
        <>
            <Head title="Master UOM" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.master-data.uoms.index')} placeholder="Cari uom..." /></div>
                <div className="flex gap-2">
                    {hasAnyPermission(['master-uom-create']) && <Button type="link" href={route('apps.master-data.uoms.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />}
                    {hasAnyPermission(['master-uom-delete']) && <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />}
                </div>
            </div>

            <Table.Card title={'Data UOM'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {uoms.data.length ? uoms.data.map((uom, i) => (
                            <tr key={uom.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (uoms.current_page - 1) * uoms.per_page}</Table.Td>
                                <Table.Td>{uom.code}</Table.Td>
                                <Table.Td>{uom.name}</Table.Td>
                                <Table.Td><div className="flex gap-2">{hasAnyPermission(['master-uom-update']) && <Button type="edit" href={route('apps.master-data.uoms.edit', uom.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />}{hasAnyPermission(['master-uom-delete']) && <Button type="delete" url={route('apps.master-data.uoms.destroy', uom.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />}</div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={4} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data UOM tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {uoms.last_page !== 1 && <Pagination links={uoms.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
