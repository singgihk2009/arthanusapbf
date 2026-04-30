import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react';
import React from 'react';

export default function Index() {
    const { barcodes } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = barcodes.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.barcodes.destroy', ids));
    };

    return (
        <>
            <Head title="Master Barcode" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.master-data.barcodes.index')} placeholder="Cari barcode / item..." /></div>
                <div className="flex gap-2">
                    <Button type="link" href={route('apps.master-data.barcodes.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                    <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />
                </div>
            </div>

            <Table.Card title={'Data Barcode Item'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>Barcode</Table.Th><Table.Th>Item</Table.Th><Table.Th>Catatan</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {barcodes.data.length ? barcodes.data.map((barcode, i) => (
                            <tr key={barcode.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (barcodes.current_page - 1) * barcodes.per_page}</Table.Td>
                                <Table.Td>{barcode.barcode}</Table.Td>
                                <Table.Td>{barcode.item?.sku} - {barcode.item?.name}</Table.Td>
                                <Table.Td>{barcode.note ?? '-'}</Table.Td>
                                <Table.Td><div className="flex gap-2"><Button type="edit" href={route('apps.master-data.barcodes.edit', barcode.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" /><Button type="delete" url={route('apps.master-data.barcodes.destroy', barcode.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={5} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data barcode tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {barcodes.last_page !== 1 && <Pagination links={barcodes.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
