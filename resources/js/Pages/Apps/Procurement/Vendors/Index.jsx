import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Table from '@/Components/Table';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { IconCircleCheck, IconCirclePlus } from '@tabler/icons-react';
import React from 'react';

export default function Index(){
 const { vendors } = usePage().props;
 const { post, delete: destroy } = useForm();

 const deleteVendor = (vendorId) => {
   if (!window.confirm('Apakah kamu yakin ingin menghapus data ini?')) return;
   destroy(`/apps/procurement/vendors/${vendorId}`);
 };

 const qualifyVendor = (vendorId) => {
   if (!window.confirm('Ubah status vendor menjadi Qualified?')) return;
   post(`/apps/procurement/vendors/${vendorId}/approve-qualification`);
 };

 return <>
   <Head title='Vendors'/>

   <div className='mb-5 flex justify-end'>
     <Button
       type='link'
       href='/apps/procurement/vendors/create'
       icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
       variant='gray'
       label='Tambah Vendor'
     />
   </div>

   <Table.Card title='Data Vendor'>
    <Table>
      <Table.Thead>
        <tr>
          <Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Type Vendor</Table.Th><Table.Th>Alamat</Table.Th><Table.Th>Provinsi</Table.Th><Table.Th>Status Qualification</Table.Th><Table.Th>Action</Table.Th>
        </tr>
      </Table.Thead>
      <Table.Tbody>
        {vendors.data.map(v=><tr key={v.id}><Table.Td>{v.vendor_code}</Table.Td><Table.Td><Link href={`/apps/procurement/vendors/${v.id}?tab=overview`} className='text-indigo-600 hover:underline'>{v.vendor_name || v.name || '-'}</Link></Table.Td><Table.Td>{v.vendor_type || '-'}</Table.Td><Table.Td>{v.address || '-'}</Table.Td><Table.Td>{v.province || '-'}</Table.Td><Table.Td>{v.qualification_status || '-'}</Table.Td><Table.Td><div className='flex gap-2'>
          <button
            type='button'
            onClick={() => qualifyVendor(v.id)}
            disabled={v.qualification_status === 'qualified'}
            className='inline-flex items-center gap-1 rounded-md border border-emerald-300 px-3 py-1 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/30'
          >
            <IconCircleCheck size={16} strokeWidth={1.75} /> Checklist
          </button>
          <button type='button' onClick={() => deleteVendor(v.id)} className='rounded-md border border-red-300 px-3 py-1 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/30'>Delete</button></div></Table.Td></tr>)}
      </Table.Tbody>
    </Table>
   </Table.Card>
 </>
}
Index.layout = (page)=><AppLayout children={page}/>;
