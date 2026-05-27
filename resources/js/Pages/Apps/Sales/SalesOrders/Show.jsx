import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));

export default function Show({ salesOrder, relatedDispatches = [] }) {
    const orderStatus = String(salesOrder?.status || '').toLowerCase();
    const hasOutstandingQty = (salesOrder.lines || []).some((line) => Number(line.qty_sold || 0) - Number(line.qty_shipped || 0) > 0);
    const canCreateShipment = ['approved', 'partially_shipped'].includes(orderStatus) && hasOutstandingQty;

    const hasSyncedDispatch = relatedDispatches.some((d) => !!d.sales_order_synced_at);
    const hasPostedPendingDispatch = relatedDispatches.some((d) => String(d.status || '').toLowerCase() === 'posted' && !d.sales_order_synced_at);

    const shipmentTimelineLabel = orderStatus === 'fully_shipped'
        ? 'Shipment (Fully Shipped)'
        : orderStatus === 'partially_shipped'
            ? 'Shipment (Partially Shipped)'
            : hasPostedPendingDispatch
                ? 'Shipment (Posted Dispatch Pending Sync)'
                : 'Shipment (Not Created)';

    const syncedLabel = (dispatch) => {
        const isSalesSource = String(dispatch.source_type || '').toLowerCase() === 'sales_order' || !!dispatch.sale_id;
        if (!isSalesSource) return 'Not Applicable';
        if (dispatch.sales_order_synced_at) return 'Synced';
        if (String(dispatch.status || '').toLowerCase() === 'posted') return 'Pending';
        return 'Pending';
    };

    return (
        <>
            <Head title={salesOrder.number} />
            <div className='space-y-4'>
                <div className='flex items-start justify-between border p-3'>
                    <div>
                        <h1 className='text-xl font-semibold'>{salesOrder.number} - {salesOrder.customer?.customer_name}</h1>
                    </div>
                    <div className='border px-2 py-1 text-sm'>{salesOrder.status_label}</div>
                </div>
                <div className='flex gap-2'>
                    {salesOrder.can_edit && <Link href={route('apps.sales-orders.edit', salesOrder.id)} className='border px-3 py-1'>Edit Draft</Link>}
                    {salesOrder.can_submit && <button className='border px-3 py-1' onClick={() => router.post(route('apps.sales-orders.submit', salesOrder.id))}>Submit</button>}
                    {salesOrder.can_approve && <button className='border px-3 py-1' onClick={() => router.post(route('apps.sales-orders.approve', salesOrder.id))}>Approve</button>}
                    {salesOrder.can_cancel && <button className='border px-3 py-1' onClick={() => router.post(route('apps.sales-orders.cancel', salesOrder.id), { cancel_reason: 'Cancelled from SO page' })}>Cancel</button>}
                    <button type='button' disabled={!canCreateShipment} className={`border px-3 py-1 ${canCreateShipment ? 'border-emerald-500 text-emerald-700' : 'opacity-50 cursor-not-allowed'}`} onClick={() => canCreateShipment && router.get(route('apps.sales-orders.dispatch.create', salesOrder.id))}>{orderStatus === 'partially_shipped' ? 'Continue Shipment' : 'Create Shipment'}</button>
                    <Link href={route('apps.customers.show', salesOrder.customer_id)} className='border px-3 py-1'>Back to Customer</Link>
                </div>

                <table className='w-full border text-sm'>
                    <thead><tr><th>Item</th><th>UoM</th><th>Avail Stock</th><th>Qty Ordered</th><th>Qty Shipped</th><th>Qty Remaining</th><th>Qty Invoiced</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Notes</th></tr></thead>
                    <tbody>{salesOrder.lines?.map((l) => <tr key={l.id}><td>{l.item?.name}</td><td>{l.uom?.name}</td><td>{l.available_stock ?? 'Unknown'}</td><td>{l.qty_sold}</td><td>{l.qty_shipped}</td><td>{Number(l.qty_sold || 0) - Number(l.qty_shipped || 0)}</td><td>{l.qty_invoiced}</td><td>{money(l.unit_price)}</td><td>{money(l.discount_amount)}</td><td>{money(l.tax_amount)}</td><td>{money(l.line_total)}</td><td>{l.notes || '-'}</td></tr>)}</tbody>
                </table>

                <div className='border p-3 text-sm'>Timeline: Created → Submitted → Approved → {shipmentTimelineLabel} → Invoice → Payment</div>

                <div className='border p-3 text-sm space-y-2'>
                    <div>Related Dispatches (Shipments): {relatedDispatches.length}</div>
                    <table className='w-full border text-xs'>
                        <thead><tr><th>No</th><th>Date</th><th>Status</th><th>Synced</th><th>Posted At</th><th>Synced At</th></tr></thead>
                        <tbody>{relatedDispatches.length ? relatedDispatches.map((d) => <tr key={d.id}><td>{d.number}</td><td>{d.document_date || '-'}</td><td>{d.status}</td><td>{syncedLabel(d)}</td><td>{d.posted_at || '-'}</td><td>{d.sales_order_synced_at || '-'}</td></tr>) : <tr><td colSpan={6} className='text-center'>No dispatches</td></tr>}</tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Show.layout = (page) => <AppLayout children={page} />;
