import AppLayout from '@/Layouts/AppLayout';
import Table from '@/Components/Table';
import { Head, Link } from '@inertiajs/react';

export default function Index({ goodsReceipts }) {
  return <AppLayout><Head title='Goods Receipts' /><Table.Card title='Goods Receipts'><Table><Table.Thead><tr><Table.Th>GR Number</Table.Th><Table.Th>PO Number</Table.Th><Table.Th>Vendor</Table.Th><Table.Th>Received Date</Table.Th><Table.Th>Status</Table.Th><Table.Th>Total Qty</Table.Th><Table.Th>Total Value</Table.Th><Table.Th>Action</Table.Th></tr></Table.Thead><Table.Tbody>{goodsReceipts.data.map((r)=><tr key={r.id}><Table.Td>{r.gr_number}</Table.Td><Table.Td>{r.purchase_order?.po_number}</Table.Td><Table.Td>{r.vendor?.name}</Table.Td><Table.Td>{r.received_date}</Table.Td><Table.Td>{r.status}</Table.Td><Table.Td>{r.total_qty}</Table.Td><Table.Td>{Number(r.total_value||0).toLocaleString('id-ID')}</Table.Td><Table.Td><Link className='text-indigo-600' href={route('apps.procurement.goods-receipts.show', r.id)}>View</Link></Table.Td></tr>)}</Table.Tbody></Table></Table.Card></AppLayout>;
}
