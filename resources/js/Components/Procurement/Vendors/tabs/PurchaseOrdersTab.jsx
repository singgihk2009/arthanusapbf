import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function PurchaseOrdersTab({ data, vendor, onRefresh }) {
    const purchaseOrders = data?.purchase_orders?.data || [];
    const vendorTabUrl = `/apps/procurement/vendors/${vendor.id}?tab=purchase-orders`;
    const [selectedDraftIds, setSelectedDraftIds] = useState([]);

    const toggleDraftSelection = (id) => {
        setSelectedDraftIds((prev) => prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]);
    };

    const approveSelectedDrafts = async () => {
        if (!selectedDraftIds.length) return;
        if (!window.confirm(`Approve ${selectedDraftIds.length} PO draft terpilih?`)) return;

        await Promise.all(selectedDraftIds.map((id) => window.axios.post(route('apps.procurement.purchase-orders.approve', id))));
        setSelectedDraftIds([]);
        onRefresh?.();
    };

    const handleDelete = (po) => {
        if (!window.confirm(`Hapus PO ${po.po_number || po.number || po.id}?`)) return;
        router.delete(route('apps.procurement.purchase-orders.destroy', po.id), {
            onSuccess: () => onRefresh?.(),
        });
    };

    return (
        <div className='space-y-3'>
            <div className='flex justify-end'>
                <button
                    type='button'
                    onClick={approveSelectedDrafts}
                    disabled={!selectedDraftIds.length}
                    className='mr-2 rounded-lg border border-blue-500 px-3 py-2 text-sm font-medium text-blue-600 enabled:hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50'
                >
                    Approve Selected ({selectedDraftIds.length})
                </button>
                <Link href={`/apps/procurement/purchase-orders/create?vendor_id=${vendor.id}&return_to=${encodeURIComponent(vendorTabUrl)}`} className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50'>
                    + Create PO
                </Link>
            </div>

            <div className='overflow-x-auto'>
                <table className='min-w-full text-sm'>
                    <thead>
                        <tr className='border-b text-left'>
                            <th className='px-3 py-2'>Approve</th>
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
                                <td className='px-3 py-2'>
                                    {String(po.status ?? '').toLowerCase() === 'draft' ? (
                                        <input type='checkbox' checked={selectedDraftIds.includes(po.id)} onChange={() => toggleDraftSelection(po.id)} />
                                    ) : '-'}
                                </td>
                                <td className='px-3 py-2'>{po.po_number ?? '-'}</td>
                                <td className='px-3 py-2'>{po.po_date ?? '-'}</td>
                                <td className='px-3 py-2'>{po.expected_delivery_date ?? '-'}</td>
                                <td className='px-3 py-2'>{Number(po.grand_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td className='px-3 py-2'>{po.status ?? '-'}</td>
                                <td className='px-3 py-2'>
                                    <div className='flex flex-wrap gap-2'>
                                        <Link href={route('apps.procurement.purchase-orders.show', po.id)} className='text-indigo-600 hover:underline'>Detail</Link>
                                        {String(po.status ?? '').toLowerCase() === 'draft' && <Link href={`/apps/procurement/purchase-orders/${po.id}/edit?return_to=${encodeURIComponent(vendorTabUrl)}`} className='text-amber-600 hover:underline'>Edit</Link>}
                                        {(String(po.status ?? '').toLowerCase() === 'draft' || String(po.status ?? '').toLowerCase() === 'cancelled') && <button type='button' onClick={() => handleDelete(po)} className='text-rose-600 hover:underline'>Delete</button>}
                                        {String(po.status ?? '').toLowerCase() === 'approved' && (
                                            <Link href={route('apps.procurement.goods-receipts.create-from-po', po.id)} className='text-emerald-600 hover:underline'>Create Goods Receiving</Link>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        )) : (
                            <tr>
                                <td className='px-3 py-4 text-center text-gray-500' colSpan={7}>Belum ada PO terkait vendor ini.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
