import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Table from '@/Components/Table';
import { Head, Link, usePage } from '@inertiajs/react';
import { IconCirclePlus } from '@tabler/icons-react';
import React from 'react';

export default function Index(){
 const { vendors } = usePage().props;
 return <>
   <Head title='Vendors'/>

   <div className='mb-5 flex justify-end'>
     <Button
       type='link'
       href={route('apps.procurement.vendors.create')}
       icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
       variant='gray'
       label='Tambah Vendor'
     />
   </div>

   <Table.Card title='Data Vendor'>
    <Table>
      <Table.Thead>
        <tr>
          <Table.Th>Vendor Code</Table.Th><Table.Th>Vendor Name</Table.Th><Table.Th>Vendor Type</Table.Th><Table.Th>City</Table.Th><Table.Th>Phone</Table.Th><Table.Th>NIB</Table.Th><Table.Th>Company License Number</Table.Th><Table.Th>Qualification Status</Table.Th><Table.Th>Status</Table.Th>
        </tr>
      </Table.Thead>
      <Table.Tbody>
        {vendors.data.map(v=><tr key={v.id}><Table.Td>{v.vendor_code}</Table.Td><Table.Td><Link href={`/apps/procurement/vendors/${v.id}?tab=overview`} className='text-indigo-600 hover:underline'>{v.vendor_name || v.name || '-'}</Link></Table.Td><Table.Td>{v.vendor_type}</Table.Td><Table.Td>{v.city}</Table.Td><Table.Td>{v.phone}</Table.Td><Table.Td>{v.nib_number}</Table.Td><Table.Td>{v.company_license_number}</Table.Td><Table.Td>{v.qualification_status}</Table.Td><Table.Td>{v.status}</Table.Td></tr>)}
      </Table.Tbody>
    </Table>
   </Table.Card>
 </>
}
Index.layout = (page)=><AppLayout children={page}/>;
