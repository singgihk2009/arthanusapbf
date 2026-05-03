import { Link, router } from '@inertiajs/react';

export default function PurchaseOrdersTab({ data, vendor, onRefresh }) {
    const purchaseOrders = data?.purchase_orders?.data || [];
    const vendorTabUrl = `/apps/procurement/vendors/${vendor.id}?tab=purchase-orders`;

    const handleDelete = (po) => {
        if (!window.confirm(`Hapus PO ${po.po_number || po.number || po.id}?`)) return;
        router.delete(route('apps.procurement.purchase-orders.destroy', po.id), {
            onSuccess: () => onRefresh?.(),
        });
    };

    return (
        <div className='space-y-3'>
            <div className='flex justify-end'>
                <Link href={`/apps/procurement/purchase-orders/create?vendor_id=${vendor.id}&return_to=${encodeURIComponent(vendorTabUrl)}`} className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50'>
                    + Create PO
                </Link>
            </div>

            <div className='overflow-x-auto'>
                <table className='min-w-full text-sm'>
                    <thead>
                        <tr className='border-b text-left'>
                            <th className='px-3 py-2'>PO Number</th>
                            <th className='px-3 py-2'>PO Date</th>
                            <th className='px-3 py-2'>Expected Date</th>
                            <th className='px-3 py-2'>Grand Total</th>
                            <th className='px-3 py-2'>Status</th>
                            <th className='px-3 py-2'>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {purchaseOrders.length ? purchaseOrders.map((po) => (
                            <tr key={po.id} className='border-b'>
                                <td className='px-3 py-2'>{po.po_number ?? '-'}</td>
                                <td className='px-3 py-2'>{po.po_date ?? '-'}</td>
                                <td className='px-3 py-2'>{po.expected_delivery_date ?? '-'}</td>
                                <td className='px-3 py-2'>{Number(po.grand_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td className='px-3 py-2'>{po.status ?? '-'}</td>
                                <td className='px-3 py-2'>
                                    <div className='flex flex-wrap gap-2'>
                                        <Link href={route('apps.procurement.purchase-orders.show', po.id)} className='text-indigo-600 hover:underline'>Detail</Link>
                                        {po.status === 'draft' && <Link href={`/apps/procurement/purchase-orders/${po.id}/edit?return_to=${encodeURIComponent(vendorTabUrl)}`} className='text-amber-600 hover:underline'>Edit</Link>}
                                        {(po.status === 'draft' || po.status === 'cancelled') && <button type='button' onClick={() => handleDelete(po)} className='text-rose-600 hover:underline'>Delete</button>}
                                    </div>
                                </td>
                            </tr>
                        )) : (
                            <tr>
                                <td className='px-3 py-4 text-center text-gray-500' colSpan={6}>Belum ada PO terkait vendor ini.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
