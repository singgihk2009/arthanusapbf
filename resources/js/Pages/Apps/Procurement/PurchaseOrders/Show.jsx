import AppLayout from '@/Layouts/AppLayout';
import Card from '@/Components/Card';
import Table from '@/Components/Table';
import { Head, router } from '@inertiajs/react';

export default function Show({ purchaseOrder }) {
    const canCancel = purchaseOrder.items.every((i) => +i.qty_received === 0);
    const poStatus = String(purchaseOrder.status || '').toLowerCase();
    const approvalStatusClass = { draft: 'bg-gray-100 text-gray-700', approved: 'bg-blue-100 text-blue-700', pending_approval: 'bg-amber-100 text-amber-700', rejected: 'bg-rose-100 text-rose-700', closed: 'bg-purple-100 text-purple-700', cancelled: 'bg-red-100 text-red-700' }[poStatus] || 'bg-gray-100';
    const fulfillmentStatus = String(purchaseOrder.fulfillment_status || 'not_received').toLowerCase();
    const fulfillmentStatusClass = { not_received: 'bg-gray-100 text-gray-700', partially_received: 'bg-amber-100 text-amber-700', fully_received: 'bg-green-100 text-green-700', closed: 'bg-purple-100 text-purple-700' }[fulfillmentStatus] || 'bg-gray-100';
    const fulfillmentLabel = { not_received: 'Not Received', partially_received: 'Partial Receipt', fully_received: 'Fully Received', closed: 'Closed' }[fulfillmentStatus] || fulfillmentStatus;

    const hasOutstanding = purchaseOrder.items.some((i) => Number(i.remaining_qty ?? (Number(i.qty_ordered) - Number(i.received_qty ?? i.qty_received ?? 0))) > 0);
    const canCreateGoodsReceiving = poStatus === 'approved' && !['fully_received'].includes(fulfillmentStatus) && !['cancelled', 'closed'].includes(poStatus) && hasOutstanding;

    const formatNumber = (value, fraction = 2) => Number(value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: fraction, maximumFractionDigits: fraction });
    const escapeHtml = (text) => String(text ?? '-')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const handlePrintPo = () => {
        const linesHtml = (purchaseOrder.items || []).map((item, index) => {
            const productName = escapeHtml(item.product?.name || item.product_name || '-');
            const qty = formatNumber(item.qty_ordered, 0);
            const unitPrice = formatNumber(item.unit_price ?? item.price ?? 0, 2);
            const lineTotal = formatNumber(item.line_total ?? 0, 2);

            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${productName}</td>
                    <td class="right">${qty}</td>
                    <td class="right">${unitPrice}</td>
                    <td class="right">${lineTotal}</td>
                </tr>
            `;
        }).join('');

        const printWindow = window.open('', '_blank');
        if (!printWindow) return;

        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="utf-8" />
                    <title>Purchase Order ${escapeHtml(purchaseOrder.po_number)}</title>
                    <style>
                        @page { size: A4 portrait; margin: 32mm 12mm 14mm 12mm; }
                        * { box-sizing: border-box; }
                        body { font-family: Arial, sans-serif; color: #111827; font-size: 12px; }
                        .header-space { height: 16mm; }
                        h1 { margin: 0 0 10px; font-size: 20px; letter-spacing: 0.5px; }
                        .po-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 12px; }
                        .po-meta .label { color: #4b5563; width: 120px; display: inline-block; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
                        th { background: #f3f4f6; text-align: left; font-weight: 700; }
                        .right { text-align: right; }
                        .summary { margin-top: 10px; width: 45%; margin-left: auto; }
                        .summary td { border: 1px solid #d1d5db; }
                        .notes { margin-top: 14px; }
                        .sign { margin-top: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        .sign-box { text-align: center; }
                        .sign-space { height: 60px; }
                    </style>
                </head>
                <body>
                    <div class="header-space"></div>
                    <h1>PURCHASE ORDER</h1>

                    <div class="po-meta">
                        <div><span class="label">PO Number</span>: ${escapeHtml(purchaseOrder.po_number)}</div>
                        <div><span class="label">PO Date</span>: ${escapeHtml(purchaseOrder.po_date)}</div>
                        <div><span class="label">Vendor</span>: ${escapeHtml(purchaseOrder.vendor?.name || '-')}</div>
                        <div><span class="label">Expected Delivery</span>: ${escapeHtml(purchaseOrder.expected_delivery_date || '-')}</div>
                        <div><span class="label">Currency</span>: IDR</div>
                        <div><span class="label">Status</span>: ${escapeHtml(poStatus.toUpperCase())}</div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 43%;">Item</th>
                                <th style="width: 12%;" class="right">Qty</th>
                                <th style="width: 20%;" class="right">Unit Price</th>
                                <th style="width: 20%;" class="right">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${linesHtml}
                        </tbody>
                    </table>

                    <table class="summary">
                        <tbody>
                            <tr>
                                <td><strong>Grand Total</strong></td>
                                <td class="right"><strong>${formatNumber(purchaseOrder.grand_total ?? 0, 2)}</strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="notes">
                        <strong>Catatan:</strong><br/>
                        Mohon mengacu pada nomor PO ini saat pengiriman barang dan penagihan invoice.
                    </div>

                    <div class="sign">
                        <div class="sign-box">
                            <div>Diterima oleh Vendor</div>
                            <div class="sign-space"></div>
                            <div>(____________________)</div>
                        </div>
                        <div class="sign-box">
                            <div>Disetujui Pembeli</div>
                            <div class="sign-space"></div>
                            <div>(____________________)</div>
                        </div>
                    </div>
                </body>
            </html>
        `;

        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    };

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
                    <span className={`rounded-full px-2 py-1 text-xs font-semibold ${approvalStatusClass}`}>Approval: {poStatus}</span>
                    <span className={`rounded-full px-2 py-1 text-xs font-semibold ${fulfillmentStatusClass}`}>Receiving: {fulfillmentLabel}</span>
                    {poStatus === 'draft' && <button type='button' onClick={() => router.post(route('apps.procurement.purchase-orders.approve', purchaseOrder.id))} className='rounded-lg border border-blue-500 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50'>Approve</button>}

                    {canCreateGoodsReceiving && (
                        <button type='button' onClick={() => router.get(`${route('apps.inbound.receiving.create')}?po_id=${purchaseOrder.id}`)} className='rounded-lg border border-emerald-500 px-3 py-1.5 text-sm text-emerald-600 hover:bg-emerald-50'>{fulfillmentStatus === 'partially_received' ? 'Continue Receiving' : 'Create Goods Receiving'}</button>
                    )}
                    <button type='button' onClick={handlePrintPo} className='rounded-lg border border-indigo-500 px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50'>Print PO (PDF)</button>
                    <button type='button' onClick={handleBack} className='rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50'>Back</button>
                    {canCancel && poStatus !== 'cancelled' && <button type='button' onClick={handleCancelPo} className='rounded-lg border border-rose-500 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50'>Cancel PO</button>}
                </div>

                <div className='mb-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2'>
                    <div><span className='font-semibold'>Vendor:</span> {purchaseOrder.vendor?.name ?? '-'}</div>
                    <div><span className='font-semibold'>PO Date:</span> {purchaseOrder.po_date}</div>
                    <div><span className='font-semibold'>Expected Delivery:</span> {purchaseOrder.expected_delivery_date ?? '-'}</div>
                    <div><span className='font-semibold'>Approval Status:</span> {poStatus || '-'} </div>
                    <div><span className='font-semibold'>Receiving Status:</span> {fulfillmentLabel || '-'} </div>
                    <div><span className='font-semibold'>Total Ordered Qty:</span> {purchaseOrder.items.reduce((sum, i) => sum + Number(i.qty_ordered || 0), 0)}</div>
                    <div><span className='font-semibold'>Total Received Qty:</span> {purchaseOrder.items.reduce((sum, i) => sum + Number(i.received_qty ?? i.qty_received ?? 0), 0)}</div>
                    <div><span className='font-semibold'>Total Remaining Qty:</span> {purchaseOrder.items.reduce((sum, i) => sum + Number(i.remaining_qty ?? (Number(i.qty_ordered || 0) - Number(i.received_qty ?? i.qty_received ?? 0))), 0)}</div>
                    <div><span className='font-semibold'>Grand Total:</span> {Number(purchaseOrder.grand_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>


                <h3 className='mt-6 mb-2 text-sm font-semibold'>Receiving History</h3>
                <Table>
                    <Table.Thead><tr><Table.Th>Reference No</Table.Th><Table.Th>Tanggal Penerimaan</Table.Th><Table.Th>Status</Table.Th><Table.Th>Received Qty</Table.Th><Table.Th>Total Value</Table.Th></tr></Table.Thead>
                    <Table.Tbody>{(purchaseOrder.goods_receipts || []).length ? (purchaseOrder.goods_receipts || []).map((gr) => <tr key={gr.id}><Table.Td>{gr.gr_number || gr.number || '-'}</Table.Td><Table.Td>{gr.received_date || gr.document_date || '-'}</Table.Td><Table.Td>{gr.status}</Table.Td><Table.Td>{gr.total_qty ?? '-'}</Table.Td><Table.Td>{Number(gr.total_value ?? 0).toLocaleString('id-ID')}</Table.Td></tr>) : <tr><Table.Td colSpan={5} className='text-center text-gray-500'>Belum ada receiving history.</Table.Td></tr>}</Table.Tbody>
                </Table>

                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>Product</Table.Th>
                            <Table.Th>Fasilitas</Table.Th>
                            <Table.Th>Nomor Fasilitas</Table.Th>
                            <Table.Th>Batch Number</Table.Th>
                            <Table.Th>Expired Date</Table.Th>
                            <Table.Th>Qty Ordered</Table.Th>
                            <Table.Th>Qty Received</Table.Th>
                            <Table.Th>Remaining Qty</Table.Th>
                            <Table.Th>Line Total</Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {purchaseOrder.items.map((i) => (
                            <tr key={i.id}>
                                <Table.Td>{i.product?.name || i.product_name || '-'}</Table.Td>
                                <Table.Td>{i.facility_name || i.facility_type || i.facility_scheme_name || '-'}</Table.Td>
                                <Table.Td>{i.facility_reference_no || i.facility_number || '-'}</Table.Td>
                                <Table.Td>{i.batch_number || i.batch_no || '-'}</Table.Td>
                                <Table.Td>{i.expired_date || i.expiry_date || '-'}</Table.Td>
                                <Table.Td>{i.qty_ordered}</Table.Td>
                                <Table.Td>{i.received_qty ?? i.qty_received}</Table.Td>
                                <Table.Td>{i.remaining_qty ?? ((+i.qty_ordered) - (+(i.received_qty ?? i.qty_received)))}</Table.Td>
                                <Table.Td>{Number(i.line_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</Table.Td>
                            </tr>
                        ))}
                    </Table.Tbody>
                </Table>
            </Card>
        </>
    );
}

Show.layout = (page) => <AppLayout children={page} />;
