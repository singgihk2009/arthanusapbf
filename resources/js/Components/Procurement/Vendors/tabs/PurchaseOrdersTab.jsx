import PurchaseOrderTable from '@/Components/Procurement/PurchaseOrders/PurchaseOrderTable';
import { Link } from '@inertiajs/react';

export default function PurchaseOrdersTab({ data, vendor }) {
    const purchaseOrders = data?.purchase_orders ?? data?.purchase_orders?.data ?? [];
    const vendorTabUrl = `/apps/procurement/vendors/${vendor.id}?tab=purchase-orders`;

    return (
        <div className='space-y-3'>
            <div className='flex justify-end'>
                <Link href={`/apps/procurement/purchase-orders/create?vendor_id=${vendor.id}&return_to=${encodeURIComponent(vendorTabUrl)}`} className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50'>
                    + Create PO
                </Link>
            </div>

            <PurchaseOrderTable
                purchaseOrders={purchaseOrders}
                showVendor={false}
                compact={true}
                emptyMessage='Belum ada PO terkait vendor ini.'
            />
        </div>
    );
}
