import AppLayout from '@/Layouts/AppLayout';
import Table from '@/Components/Table';
import { Head, usePage } from '@inertiajs/react';
import React from 'react';

export default function QualificationReport(){
  const { vendors } = usePage().props;
  return <><Head title='Vendor Qualification Report'/><Table.Card title='Vendor Qualification Report'><Table><Table.Thead><tr><Table.Th>Vendor Name</Table.Th><Table.Th>NIB</Table.Th><Table.Th>Company License</Table.Th><Table.Th>TRP</Table.Th><Table.Th>SIP Number</Table.Th><Table.Th>SIP Expiry</Table.Th><Table.Th>Qualification</Table.Th></tr></Table.Thead><Table.Tbody>{vendors.map(v=>{const sip=v.documents?.find(d=>d.document_type==='TECHNICAL_RESPONSIBLE_PERSON_SIP'); return <tr key={v.id}><Table.Td>{v.vendor_name}</Table.Td><Table.Td>{v.nib_number}</Table.Td><Table.Td>{v.company_license_number}</Table.Td><Table.Td>{v.technical_responsible_person?.name}</Table.Td><Table.Td>{v.technical_responsible_person?.license_number}</Table.Td><Table.Td>{sip?.expiry_date ?? '-'}</Table.Td><Table.Td>{v.qualification_status}</Table.Td></tr>})}</Table.Tbody></Table></Table.Card></>
}
QualificationReport.layout=(page)=><AppLayout children={page}/>;
