import AppLayout from '@/Layouts/AppLayout';
import Table from '@/Components/Table';
import { Head, usePage } from '@inertiajs/react';
import React from 'react';

export default function Index(){
 const { vendors } = usePage().props;
 return <><Head title='Vendors'/><Table.Card title='Data Vendor'><Table><Table.Thead><tr><Table.Th>Vendor Code</Table.Th><Table.Th>Vendor Name</Table.Th><Table.Th>Vendor Type</Table.Th><Table.Th>City</Table.Th><Table.Th>Phone</Table.Th><Table.Th>NIB</Table.Th><Table.Th>Company License Number</Table.Th><Table.Th>Qualification Status</Table.Th><Table.Th>Status</Table.Th></tr></Table.Thead><Table.Tbody>{vendors.data.map(v=><tr key={v.id}><Table.Td>{v.vendor_code}</Table.Td><Table.Td>{v.vendor_name}</Table.Td><Table.Td>{v.vendor_type}</Table.Td><Table.Td>{v.city}</Table.Td><Table.Td>{v.phone}</Table.Td><Table.Td>{v.nib_number}</Table.Td><Table.Td>{v.company_license_number}</Table.Td><Table.Td>{v.qualification_status}</Table.Td><Table.Td>{v.status}</Table.Td></tr>)}</Table.Tbody></Table></Table.Card></>
}
Index.layout = (page)=><AppLayout children={page}/>;
