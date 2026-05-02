import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react';
import React from 'react';

export default function Index() {
    const { vendors } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = vendors.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.procurement.vendors.destroy', ids));
    };

    return (
        <>
            <Head title="Vendor" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.procurement.vendors.index')} placeholder="Cari vendor..." /></div>
                <div className="flex gap-2">
                    <Button type="link" href={route('apps.procurement.vendors.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                    <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />
                </div>
            </div>

            <Table.Card title={'Data Vendor'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Email</Table.Th><Table.Th>Telepon</Table.Th><Table.Th>Status</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {vendors.data.length ? vendors.data.map((vendor, i) => (
                            <tr key={vendor.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (vendors.current_page - 1) * vendors.per_page}</Table.Td>
                                <Table.Td>{vendor.vendor_code}</Table.Td>
                                <Table.Td>{vendor.name}</Table.Td>
                                <Table.Td>{vendor.email ?? '-'}</Table.Td>
                                <Table.Td>{vendor.phone ?? '-'}</Table.Td>
                                <Table.Td>{vendor.status}</Table.Td>
                                <Table.Td><div className="flex gap-2"><Button type="edit" href={route('apps.procurement.vendors.edit', vendor.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" /><Button type="delete" url={route('apps.procurement.vendors.destroy', vendor.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={7} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data vendor tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {vendors.last_page !== 1 && <Pagination links={vendors.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
