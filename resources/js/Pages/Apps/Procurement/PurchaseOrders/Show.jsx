import AppLayout from '@/Layouts/AppLayout';
import Card from '@/Components/Card';
import Table from '@/Components/Table';
import { Head, router } from '@inertiajs/react';

export default function Show({ purchaseOrder }) {
    const canCancel = purchaseOrder.items.every((i) => +i.qty_received === 0);
    const statusClass = { draft: 'bg-gray-100 text-gray-700', approved: 'bg-blue-100 text-blue-700', partially_received: 'bg-amber-100 text-amber-700', fully_received: 'bg-green-100 text-green-700', closed: 'bg-purple-100 text-purple-700', cancelled: 'bg-red-100 text-red-700' }[purchaseOrder.status] || 'bg-gray-100';


    const handleBack = () => {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        router.get(route('apps.procurement.purchase-orders.index'));
    };

    const handleCancelPo = () => {
        const confirmed = window.confirm(`Yakin ingin membatalkan PO ${purchaseOrder.po_number}? Tindakan ini tidak bisa dibatalkan.`);

        if (!confirmed) return;

        router.post(route('apps.procurement.purchase-orders.cancel', purchaseOrder.id));
    };

    return (
        <>
            <Head title={`PO ${purchaseOrder.po_number}`} />
            <Card title={`Purchase Order ${purchaseOrder.po_number}`}>
                <div className='mb-4 flex flex-wrap items-center gap-2'>
                    <span className={`rounded-full px-2 py-1 text-xs font-semibold ${statusClass}`}>{purchaseOrder.status}</span>
                    {purchaseOrder.status === 'draft' && <button type='button' onClick={() => router.post(route('apps.procurement.purchase-orders.approve', purchaseOrder.id))} className='rounded-lg border border-blue-500 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50'>Approve</button>}
                    <button type='button' onClick={handleBack} className='rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50'>Back</button>
                    {canCancel && purchaseOrder.status !== 'cancelled' && <button type='button' onClick={handleCancelPo} className='rounded-lg border border-rose-500 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50'>Cancel PO</button>}
                </div>

                <div className='mb-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2'>
                    <div><span className='font-semibold'>Vendor:</span> {purchaseOrder.vendor?.name ?? '-'}</div>
                    <div><span className='font-semibold'>PO Date:</span> {purchaseOrder.po_date}</div>
                    <div><span className='font-semibold'>Expected Delivery:</span> {purchaseOrder.expected_delivery_date ?? '-'}</div>
                    <div><span className='font-semibold'>Grand Total:</span> {Number(purchaseOrder.grand_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>

                <Table>
                    <Table.Thead><tr><Table.Th>Product</Table.Th><Table.Th>Qty Ordered</Table.Th><Table.Th>Qty Received</Table.Th><Table.Th>Remaining Qty</Table.Th><Table.Th>Line Total</Table.Th></tr></Table.Thead>
                    <Table.Tbody>{purchaseOrder.items.map((i) => <tr key={i.id}><Table.Td>{i.product?.name || i.product_name || '-'}</Table.Td><Table.Td>{i.qty_ordered}</Table.Td><Table.Td>{i.qty_received}</Table.Td><Table.Td>{(+i.qty_ordered) - (+i.qty_received)}</Table.Td><Table.Td>{Number(i.line_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</Table.Td></tr>)}</Table.Tbody>
                </Table>
            </Card>
        </>
    );
}

Show.layout = (page) => <AppLayout children={page} />;
