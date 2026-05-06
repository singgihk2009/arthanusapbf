import PurchaseOrderTable from '@/Components/Procurement/PurchaseOrders/PurchaseOrderTable';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function PurchaseOrdersTab({ data, vendor, onRefresh }) {
    const purchaseOrders = data?.purchase_orders ?? data?.purchase_orders?.data ?? [];
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

            <PurchaseOrderTable
                purchaseOrders={purchaseOrders}
                showVendor={false}
                compact={true}
                selectedDraftIds={selectedDraftIds}
                onToggleDraftSelection={toggleDraftSelection}
                emptyMessage='Belum ada PO terkait vendor ini.'
            />
        </div>
    );
}
