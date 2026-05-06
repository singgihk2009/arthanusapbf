import Table from '@/Components/Table';
import { Link, router } from '@inertiajs/react';
import { IconDatabaseOff } from '@tabler/icons-react';
import { cloneElement, isValidElement, useMemo, useState } from 'react';

const APPROVAL_STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    approved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    partially_received: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    fully_received: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    closed: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
};

const FULFILLMENT_STATUS_STYLES = {
    not_received: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    partially_received: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    fully_received: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    closed: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

const formatFulfillmentStatus = (status) => ({ not_received: 'Not Received', partially_received: 'Partial Receipt', fully_received: 'Fully Received', closed: 'Closed' }[status] || status || '-');

const toRows = (purchaseOrders) => {
    if (Array.isArray(purchaseOrders)) return purchaseOrders;
    if (Array.isArray(purchaseOrders?.data)) return purchaseOrders.data;
    return [];
};

const formatCurrency = (value) => Number(value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
};

export default function PurchaseOrderTable({ purchaseOrders, showVendor = true, compact = false, loading = false, emptyMessage = 'No purchase orders found', showApproveAction = true, onApproved = null, topActions = null }) {
    const rows = toRows(purchaseOrders);
    const [selectedIds, setSelectedIds] = useState([]);

    const draftRows = useMemo(() => rows.filter((po) => String(po.status ?? '').toLowerCase() === 'draft').map((po) => po.id), [rows]);

    const toggleDraftSelection = (id) => {
        setSelectedIds((prev) => prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]);
    };

    const approveSelectedDrafts = async () => {
        if (!selectedIds.length) return;
        if (!window.confirm(`Approve ${selectedIds.length} PO draft terpilih?`)) return;
        await Promise.all(selectedIds.map((id) => window.axios.post(route('apps.procurement.purchase-orders.approve', id))));
        setSelectedIds([]);
        onApproved?.();
    };

    const handleDeleteDraft = (id) => {
        if (!window.confirm('Hapus PO draft ini?')) return;
        router.delete(route('apps.procurement.purchase-orders.destroy', id));
    };

    return (
        <div className='space-y-3'>
            {(showApproveAction || topActions) && (
                <div className='flex flex-wrap items-center justify-end gap-2'>
                    {isValidElement(topActions) ? cloneElement(topActions, { className: `${topActions.props.className ?? ''}`.trim() }) : topActions}
                    {showApproveAction && (
                        <button
                            type='button'
                            onClick={approveSelectedDrafts}
                            disabled={!selectedIds.length || !draftRows.length}
                            className='rounded-lg border border-blue-500 px-3 py-2 text-sm font-medium text-blue-600 enabled:hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50'
                        >
                            Approve Selected ({selectedIds.length})
                        </button>
                    )}
                </div>
            )}

            <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th className={compact ? 'text-xs' : ''}>Approve</Table.Th>
                        <Table.Th className={compact ? 'text-xs' : ''}>PO Number</Table.Th>
                        {showVendor && <Table.Th className={compact ? 'text-xs' : ''}>Vendor</Table.Th>}
                        <Table.Th className={compact ? 'text-xs' : ''}>PO Date</Table.Th>
                        <Table.Th className={compact ? 'text-xs' : ''}>Expected Date</Table.Th>
                        <Table.Th className={compact ? 'text-xs' : ''}>Grand Total</Table.Th>
                        <Table.Th className={compact ? 'text-xs' : ''}>Approval</Table.Th>
                        <Table.Th className={compact ? 'text-xs' : ''}>Receiving</Table.Th>
                        <Table.Th className={compact ? 'text-xs' : ''}>Action</Table.Th>
                    </tr>
                </Table.Thead>
                <Table.Tbody>
                    {loading ? (
                        <tr><Table.Td colSpan={showVendor ? 9 : 8}>Loading...</Table.Td></tr>
                    ) : rows.length ? rows.map((po) => {
                        const status = String(po.status ?? '').toLowerCase();
                        const fulfillmentStatus = String(po.fulfillment_status ?? 'not_received').toLowerCase();
                        const approvalBadgeClass = APPROVAL_STATUS_STYLES[status] || APPROVAL_STATUS_STYLES.draft;
                        const fulfillmentBadgeClass = FULFILLMENT_STATUS_STYLES[fulfillmentStatus] || FULFILLMENT_STATUS_STYLES.not_received;
                        const vendorName = po.vendor?.name ?? po.vendor_name ?? '-';
                        const isDraft = status === 'draft';
                        const outstandingItemsCount = Number(po.outstanding_items_count ?? 0);
                        const canCreateGoodsReceiving = status === 'approved' && fulfillmentStatus !== 'fully_received' && !['cancelled','closed'].includes(status) && outstandingItemsCount > 0;

                        return (
                            <tr key={po.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}>{isDraft ? <input type='checkbox' checked={selectedIds.includes(po.id)} onChange={() => toggleDraftSelection(po.id)} /> : '-'}</Table.Td>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}>{po.po_number ?? po.number ?? '-'}</Table.Td>
                                {showVendor && <Table.Td className={compact ? 'p-3 text-xs' : ''}>{po.vendor_id ? <Link href={`/apps/procurement/vendors/${po.vendor_id}?tab=overview`} className='text-indigo-600 hover:underline'>{vendorName}</Link> : vendorName}</Table.Td>}
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}>{formatDate(po?.po_date ?? po?.document_date)}</Table.Td>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}>{formatDate(po.expected_delivery_date ?? po.expected_date)}</Table.Td>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}>{formatCurrency(po.grand_total)}</Table.Td>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}><span className={`rounded-full px-2 py-1 text-xs font-medium ${approvalBadgeClass}`}>{po.status ?? '-'}</span></Table.Td>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}><span className={`rounded-full px-2 py-1 text-xs font-medium ${fulfillmentBadgeClass}`}>{formatFulfillmentStatus(fulfillmentStatus)}</span></Table.Td>
                                <Table.Td className={compact ? 'p-3 text-xs' : ''}>
                                    <div className='flex flex-wrap gap-2'>
                                        <Link className='rounded-lg border border-indigo-500 px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50' href={route('apps.procurement.purchase-orders.show', po.id)}>Detail</Link>
                                        {status === 'draft' && <Link className='rounded-lg border border-amber-500 px-2.5 py-1.5 text-xs font-medium text-amber-600 hover:bg-amber-50' href={route('apps.procurement.purchase-orders.edit', po.id)}>Edit</Link>}
                                        {(status === 'draft' || status === 'cancelled') && <button type='button' onClick={() => handleDeleteDraft(po.id)} className='rounded-lg border border-rose-500 px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50'>Delete</button>}
                                        {canCreateGoodsReceiving && <Link className='rounded-lg border border-emerald-500 px-2.5 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50' href={`${route('apps.inbound.receiving.create')}?po_id=${po.id}`}>{fulfillmentStatus === 'partially_received' ? 'Continue Receiving' : 'Create Goods Receiving'}</Link>}
                                    </div>
                                </Table.Td>
                            </tr>
                        );
                    }) : <Table.Empty colSpan={showVendor ? 9 : 8} message={<><IconDatabaseOff size={24} strokeWidth={1.5} className='mx-auto mb-2 text-gray-500 dark:text-white' /><span className='text-gray-500'>{emptyMessage}</span></>} />}
                </Table.Tbody>
            </Table>
        </div>
    );
}
