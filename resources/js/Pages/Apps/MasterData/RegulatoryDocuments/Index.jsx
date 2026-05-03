import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { router, Head, usePage } from '@inertiajs/react';
import { IconPencilCog, IconTrash } from '@tabler/icons-react';

export default function Index() {
  const { documents } = usePage().props;

  const handleClose = () => {
    router.visit('/apps/dashboard');
  };

  return <>
    <Head title='Master Regulatory Document' />
    <div className='mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between'>
      <div className='w-full md:w-1/3'><Search url={route('apps.master-data.regulatory-documents.index')} placeholder='Cari dokumen...' /></div>
      <div className='flex gap-2'>
        <Button type='link' href={route('apps.master-data.regulatory-documents.create')} variant='gray' label='Tambah' />
        <Button type='button' onClick={handleClose} variant='roseBlack' label='Close' />
      </div>
    </div>
    <Table.Card title='Data Regulatory Document'>
      <Table><Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Code</Table.Th><Table.Th>Name</Table.Th><Table.Th>Category</Table.Th><Table.Th>Is Required</Table.Th><Table.Th /></tr></Table.Thead>
      <Table.Tbody>{documents.data.map((doc, i) => <tr key={doc.id}><Table.Td>{++i + (documents.current_page - 1) * documents.per_page}</Table.Td><Table.Td>{doc.code}</Table.Td><Table.Td>{doc.name}</Table.Td><Table.Td>{doc.category || '-'}</Table.Td><Table.Td>{doc.is_required ? 'Yes' : 'No'}</Table.Td><Table.Td><div className='flex gap-2'><Button type='edit' href={route('apps.master-data.regulatory-documents.edit', doc.id)} variant='gray' icon={<IconPencilCog size={16} strokeWidth={1.5} className='text-gray-700 dark:text-gray-200' />} /><Button type='delete' url={route('apps.master-data.regulatory-documents.destroy', doc.id)} variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} className='text-rose-600 dark:text-rose-400' />} /></div></Table.Td></tr>)}</Table.Tbody></Table>
    </Table.Card>
    {documents.last_page !== 1 && <Pagination links={documents.links} />}
  </>;
}

Index.layout = (page) => <AppLayout children={page} />;
