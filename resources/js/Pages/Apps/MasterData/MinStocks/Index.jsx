import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react';
import React from 'react';

export default function Index() {
    const { minStocks } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = minStocks.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.min-stocks.destroy', ids));
    };

    return (
        <>
            <Head title="Master Min Stock" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.master-data.min-stocks.index')} placeholder="Cari item / gudang..." /></div>
                <div className="flex gap-2">
                    <Button type="link" href={route('apps.master-data.min-stocks.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />
                    <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />
                </div>
            </div>

            <Table.Card title={'Data Min Stock per Gudang'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>Gudang</Table.Th><Table.Th>Item</Table.Th><Table.Th>Min Stock (Base)</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {minStocks.data.length ? minStocks.data.map((minStock, i) => (
                            <tr key={minStock.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (minStocks.current_page - 1) * minStocks.per_page}</Table.Td>
                                <Table.Td>{minStock.warehouse?.code} - {minStock.warehouse?.name}</Table.Td>
                                <Table.Td>{minStock.item?.sku} - {minStock.item?.name}</Table.Td>
                                <Table.Td>{minStock.min_stock_base}</Table.Td>
                                <Table.Td><div className="flex gap-2"><Button type="edit" href={route('apps.master-data.min-stocks.edit', minStock.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" /><Button type="delete" url={route('apps.master-data.min-stocks.destroy', minStock.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={5} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data min stock tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {minStocks.last_page !== 1 && <Pagination links={minStocks.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
