import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react';
import React from 'react';

export default function Index() {
    const { conversions } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = conversions.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.conversions.destroy', ids));
    };

    return (
        <>
            <Head title="Master Konversi UOM" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.master-data.conversions.index')} placeholder="Cari item..." /></div>
                <div className="flex gap-2">
                    <Button type="link" href={route('apps.master-data.conversions.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                    <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />
                </div>
            </div>

            <Table.Card title={'Data Konversi UOM'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>Item</Table.Th><Table.Th>Dari UOM</Table.Th><Table.Th>Ke UOM</Table.Th><Table.Th>Faktor</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {conversions.data.length ? conversions.data.map((conversion, i) => (
                            <tr key={conversion.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (conversions.current_page - 1) * conversions.per_page}</Table.Td>
                                <Table.Td>{conversion.item?.sku} - {conversion.item?.name}</Table.Td>
                                <Table.Td>{conversion.from_uom?.code ?? conversion.fromUom?.code}</Table.Td>
                                <Table.Td>{conversion.to_uom?.code ?? conversion.toUom?.code}</Table.Td>
                                <Table.Td>{conversion.factor}</Table.Td>
                                <Table.Td><div className="flex gap-2"><Button type="edit" href={route('apps.master-data.conversions.edit', conversion.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" /><Button type="delete" url={route('apps.master-data.conversions.destroy', conversion.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={6} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data konversi tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {conversions.last_page !== 1 && <Pagination links={conversions.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
