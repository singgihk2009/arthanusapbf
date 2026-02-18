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
    const { categories } = usePage().props;
    const { delete: destroy } = useForm();

    const bulkDelete = () => {
        const ids = categories.data.map((item) => item.id).join(',');
        if (!ids) return;
        destroy(route('apps.master-data.categories.destroy', ids));
    };

    return (
        <>
            <Head title="Master Category" />
            <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="w-full md:w-1/3"><Search url={route('apps.master-data.categories.index')} placeholder="Cari kategori..." /></div>
                <div className="flex gap-2">
                    {hasAnyPermission(['master-category-create']) && <Button type="link" href={route('apps.master-data.categories.create')} icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah" />}
                    {hasAnyPermission(['master-category-delete']) && <Button type="bulk" onClick={bulkDelete} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" label="Hapus Halaman" />}
                </div>
            </div>

            <Table.Card title={'Data Category'}>
                <Table>
                    <Table.Thead><tr><Table.Th className="w-10">No</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Parent</Table.Th><Table.Th className="w-32"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {categories.data.length ? categories.data.map((category, i) => (
                            <tr key={category.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td className="text-center">{++i + (categories.current_page - 1) * categories.per_page}</Table.Td>
                                <Table.Td>{category.name}</Table.Td>
                                <Table.Td>{category.parent?.name ?? '-'}</Table.Td>
                                <Table.Td><div className="flex gap-2">{hasAnyPermission(['master-category-update']) && <Button type="edit" href={route('apps.master-data.categories.edit', category.id)} icon={<IconPencilCog size={16} strokeWidth={1.5} />} variant="orange" />}{hasAnyPermission(['master-category-delete']) && <Button type="delete" url={route('apps.master-data.categories.destroy', category.id)} icon={<IconTrash size={16} strokeWidth={1.5} />} variant="rose" />}</div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={4} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto text-gray-500 dark:text-white mb-2'/><span className='text-gray-500'>Data kategori tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {categories.last_page !== 1 && <Pagination links={categories.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
