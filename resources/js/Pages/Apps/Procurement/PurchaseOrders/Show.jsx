import AppLayout from '@/Layouts/AppLayout';
import Card from '@/Components/Card';
import Table from '@/Components/Table';
import { Head, router } from '@inertiajs/react';
import { getPurchaseOrderPrintTemplate, getSignerDisplay } from './PrintTemplates';

export default function Show({ purchaseOrder, company = null }) {
    const canCancel = purchaseOrder.items.every((i) => +i.qty_received === 0);
    const poStatus = String(purchaseOrder.status || '').toLowerCase();
    const approvalStatusClass = { draft: 'bg-gray-100 text-gray-700', approved: 'bg-blue-100 text-blue-700', pending_approval: 'bg-amber-100 text-amber-700', rejected: 'bg-rose-100 text-rose-700', closed: 'bg-purple-100 text-purple-700', cancelled: 'bg-red-100 text-red-700' }[poStatus] || 'bg-gray-100';
    const fulfillmentStatus = String(purchaseOrder.fulfillment_status || 'not_received').toLowerCase();
    const fulfillmentStatusClass = { not_received: 'bg-gray-100 text-gray-700', partially_received: 'bg-amber-100 text-amber-700', fully_received: 'bg-green-100 text-green-700', closed: 'bg-purple-100 text-purple-700' }[fulfillmentStatus] || 'bg-gray-100';
    const fulfillmentLabel = { not_received: 'Not Received', partially_received: 'Partial Receipt', fully_received: 'Fully Received', closed: 'Closed' }[fulfillmentStatus] || fulfillmentStatus;
    const poTypeLabel = { regular: 'PO Reguler', precursor: 'PO Prekursor', oot: 'PO OOT', alkes: 'PO Alkes' }[purchaseOrder.po_type] || 'PO Reguler';
    const printTemplate = getPurchaseOrderPrintTemplate(purchaseOrder.po_type);
    const requesterSigner = getSignerDisplay(purchaseOrder.signer_profile, 'requester');
    const approverSigner = getSignerDisplay(purchaseOrder.signer_profile, 'approver');

    const hasOutstanding = purchaseOrder.items.some((i) => Number(i.remaining_qty ?? (Number(i.qty_ordered) - Number(i.received_qty ?? i.qty_received ?? 0))) > 0);
    const canCreateGoodsReceiving = poStatus === 'approved' && !['fully_received'].includes(fulfillmentStatus) && !['cancelled', 'closed'].includes(poStatus) && hasOutstanding;

    const formatNumber = (value, fraction = 2) => Number(value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: fraction, maximumFractionDigits: fraction });
    const escapeHtml = (text) => String(text ?? '-')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatDisplayDate = (value) => {
        if (!value) return '-';

        const parsedDate = new Date(value);
        if (Number.isNaN(parsedDate.getTime())) return value;

        return parsedDate.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const createdBy = purchaseOrder.created_by_name
        || purchaseOrder.created_by?.name
        || purchaseOrder.creator?.name
        || purchaseOrder.createdBy?.name
        || purchaseOrder.created_by
        || '-';

    const approvedBy = purchaseOrder.approved_by_name
        || purchaseOrder.approved_by?.name
        || purchaseOrder.approver?.name
        || purchaseOrder.approvedBy?.name
        || purchaseOrder.approved_by
        || '-';

    const receivedBy = (purchaseOrder.goods_receipts || [])
        .map((gr) => gr.received_by_name || gr.received_by?.name || gr.receiver?.name || gr.receivedBy?.name || gr.received_by || gr.created_by_name || gr.created_by?.name)
        .filter(Boolean)
        .filter((value, index, arr) => arr.indexOf(value) === index)
        .join(', ') || '-';

    const warehouseInfo = (purchaseOrder.goods_receipts || [])
        .map((gr) => gr.warehouse_name || gr.warehouse?.name || gr.warehouse_code || gr.warehouse_id)
        .filter(Boolean)
        .filter((value, index, arr) => arr.indexOf(value) === index)
        .join(', ') || '-';
    const poDocuments = purchaseOrder.documents || [];
    const receivingDocuments = (purchaseOrder.goods_receipts || [])
        .flatMap((gr) => (gr.documents || []).map((doc) => ({
            ...doc,
            receiving_number: gr.gr_number || gr.number || '-',
            receiving_date: gr.received_date || gr.document_date || null,
        })));

    const renderUploadedDocumentsTable = (documents, emptyMessage, showReceivingInfo = false) => (
        <div className='rounded-lg border border-gray-200'>
            <div className='border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700'>
                Daftar Dokumen Terupload ({documents.length})
            </div>
            <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th>Document Type</Table.Th>
                        <Table.Th>Judul</Table.Th>
                        <Table.Th>No Dokumen</Table.Th>
                        <Table.Th>Nama File</Table.Th>
                        <Table.Th>Status Upload</Table.Th>
                        <Table.Th>Aksi</Table.Th>
                    </tr>
                </Table.Thead>
                <Table.Tbody>
                    {documents.length ? documents.map((doc) => (
                        <tr key={`${showReceivingInfo ? `gr-${doc.receiving_number}-` : 'po-'}${doc.id}`}>
                            <Table.Td>{doc.document_type?.name || doc.document_type?.code || doc.type || '-'}</Table.Td>
                            <Table.Td>{doc.title || '-'}</Table.Td>
                            <Table.Td>{doc.document_number || doc.number || '-'}</Table.Td>
                            <Table.Td>{doc.original_file_name || doc.file_name || '-'}</Table.Td>
                            <Table.Td>{doc.status || doc.upload_status || '-'}</Table.Td>
                            <Table.Td>
                                {doc.id ? (
                                    <a href={route('apps.document-center.documents.download', doc.id)} target='_blank' rel='noopener noreferrer' className='inline-flex rounded border border-lime-500 bg-lime-300 px-2 py-1 text-sm font-semibold text-gray-900 hover:bg-lime-400'>
                                        View
                                    </a>
                                ) : '-'}
                            </Table.Td>
                        </tr>
                    )) : (
                        <tr>
                            <Table.Td colSpan={6} className='text-center text-gray-500'>
                                {emptyMessage}
                            </Table.Td>
                        </tr>
                    )}
                </Table.Tbody>
            </Table>
        </div>
    );

    const handlePrintPo = () => {
        const getUnitName = (item) => item.uom?.name || item.uom_name || item.unit || item.unit_name || 'BOX';
        const printLines = purchaseOrder.items || [];
        const blankRows = Array.from({ length: Math.max(0, 10 - printLines.length) });
        const vendorAddress = [purchaseOrder.vendor?.address, purchaseOrder.vendor?.city, purchaseOrder.vendor?.province].filter(Boolean).join(', ');
        const companyAddress = [company?.address, company?.city, company?.province].filter(Boolean).join(', ');
        const logoMarkup = company?.logo_path
            ? `<img src="/storage/${escapeHtml(company.logo_path)}" alt="Logo" class="company-logo" />`
            : `<div class="company-logo-text"><span class="logo-symbol">✚</span><span><strong>artha</strong><br/>nusa</span></div>`;

        const linesHtml = printLines.map((item, index) => {
            const productName = escapeHtml(item.product?.name || item.product_name || '-');
            const qty = formatNumber(item.qty_ordered, 0);
            const unit = escapeHtml(getUnitName(item));

            return `
                <tr>
                    <td class="center">${index + 1}</td>
                    <td>${productName}${item.active_ingredient ? `<br/><small>Zat aktif: ${escapeHtml(item.active_ingredient)}</small>` : ''}${item.dosage_form_strength ? `<br/><small>Bentuk/kekuatan: ${escapeHtml(item.dosage_form_strength)}</small>` : ''}</td>
                    <td class="center">${qty}</td>
                    <td class="center">${unit}</td>
                    <td class="center">${qty} ${unit}</td>
                </tr>
            `;
        }).join('');

        const blankRowsHtml = blankRows.map((_, index) => `
            <tr class="blank-row">
                <td class="center">${printLines.length + index + 1}</td>
                <td>&nbsp;</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        `).join('');

        const printWindow = window.open('', '_blank');
        if (!printWindow) return;

        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="utf-8" />
                    <title>${escapeHtml(poTypeLabel)} ${escapeHtml(purchaseOrder.po_number)}</title>
                    <style>
                        @page { size: A4 portrait; margin: 10mm 9mm 12mm; }
                        * { box-sizing: border-box; }
                        body { margin: 0; color: #111; background: #fff; font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; }
                        .sheet { width: 100%; min-height: 277mm; }
                        .header { display: grid; grid-template-columns: 150px 1fr 210px; align-items: start; column-gap: 10px; }
                        .company-logo { max-width: 130px; max-height: 54px; object-fit: contain; }
                        .company-logo-text { display: flex; align-items: center; gap: 5px; color: #42a9e9; font-family: Arial, sans-serif; font-size: 22px; letter-spacing: 2px; line-height: .72; }
                        .company-logo-text strong { color: #2f80da; font-size: 26px; font-weight: 700; letter-spacing: 0; }
                        .logo-symbol { display: inline-flex; width: 24px; height: 24px; align-items: center; justify-content: center; border-radius: 50%; background: #42a9e9; color: #fff; font-size: 14px; }
                        .company-main { text-align: center; }
                        .company-name { font-family: "Times New Roman", serif; font-size: 17px; font-weight: 800; letter-spacing: 1px; }
                        .company-subtitle { font-size: 10px; font-weight: 700; }
                        .company-contact { margin-top: 38px; }
                        .license-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 22px; margin-top: 3px; }
                        .rule { margin: 4px 0 10px; border-top: 1px dashed #777; }
                        .title { margin: 13px 0 16px; text-align: center; font-size: 17px; letter-spacing: 11px; }
                        .meta { display: grid; grid-template-columns: 1fr 220px; gap: 20px; margin-bottom: 22px; }
                        .label { display: inline-block; min-width: 62px; }
                        .vendor-name { text-transform: uppercase; font-weight: 700; }
                        table { width: 100%; border-collapse: collapse; }
                        .items { border-top: 1px dashed #777; border-bottom: 1px dashed #777; }
                        .items th { border-bottom: 1px dashed #777; font-weight: 400; text-align: center; }
                        .items th, .items td { padding: 1px 4px; height: 15px; vertical-align: top; }
                        .items th:nth-child(1), .items td:nth-child(1) { width: 30px; }
                        .items th:nth-child(2), .items td:nth-child(2) { width: auto; }
                        .items th:nth-child(3), .items td:nth-child(3) { width: 90px; }
                        .items th:nth-child(4), .items td:nth-child(4) { width: 110px; }
                        .items th:nth-child(5), .items td:nth-child(5) { width: 125px; }
                        .center { text-align: center; }
                        .blank-row td { height: 16px; }
                        .footer-rule { margin-top: 3px; border-top: 1px dashed #777; }
                        .signatures { display: grid; grid-template-columns: 1fr 150px 1fr; align-items: end; gap: 36px; margin-top: 32px; }
                        .sign-box { text-align: center; min-height: 118px; }
                        .sign-title { margin-bottom: 56px; }
                        .sign-line { border-top: 1px solid #444; height: 1px; margin-top: 48px; }
                        .sign-name-space { min-height: 28px; }
                        .middle-logo { display: flex; align-items: center; justify-content: center; color: #2f80da; font-family: Arial, sans-serif; font-size: 30px; line-height: .75; letter-spacing: 2px; padding-bottom: 28px; }
                        .middle-logo strong { font-size: 34px; letter-spacing: 0; }
                    </style>
                </head>
                <body>
                    <main class="sheet">
                        <header class="header">
                            <div>${logoMarkup}</div>
                            <div class="company-main">
                                <div class="company-name">${escapeHtml(company?.legal_name || 'PT. ARUTALA MAHA NUSANTARA')}</div>
                                <div class="company-subtitle">PEDAGANG BESAR FARMASI DAN ALAT KESEHATAN</div>
                                <div>${escapeHtml(companyAddress || 'Jl. Pasar Baru Ruko No. 75-76')}</div>
                                <div>${escapeHtml(company?.city || 'Muka')}${company?.postal_code ? `, ${escapeHtml(company.postal_code)}` : ''}</div>
                                <div>${escapeHtml(company?.country || 'Indonesia')}</div>
                            </div>
                            <div class="company-contact">
                                <div>Phone : ${escapeHtml(company?.phone || '-')}</div>
                            </div>
                        </header>

                        <div class="license-grid">
                            <div>Mobile : ${escapeHtml(company?.mobile || company?.phone || '-')}</div>
                            <div>No. Izin CDOB Obat Lain : ${escapeHtml(company?.cdob_other_license_number || '-')}</div>
                            <div>NPWP: ${escapeHtml(company?.tax_id || '-')}</div>
                            <div>No. Izin CDOB CCP : ${escapeHtml(company?.cdob_ccp_license_number || '-')}</div>
                            <div>No. Izin PBF : ${escapeHtml(company?.pbf_license_number || '-')}</div>
                            <div></div>
                            <div>No.Izin IDAK : ${escapeHtml(company?.idak_license_number || '-')}</div>
                            <div></div>
                        </div>
                        <div class="rule"></div>

                        <h1 class="title">${escapeHtml(printTemplate.title)}</h1>

                        <section class="meta">
                            <div>
                                <div>Pemasok :</div>
                                <div>Yth. <span class="vendor-name">${escapeHtml(purchaseOrder.vendor?.name || '-')}</span></div>
                                <div>${escapeHtml(vendorAddress || 'di Tempat')}</div>
                            </div>
                            <div>
                                <div><span class="label">Jenis</span>: ${escapeHtml(poTypeLabel)}</div>
                                <div><span class="label">Nomor</span>: ${escapeHtml(purchaseOrder.po_number)}</div>
                                <div><span class="label">Tanggal</span>: ${escapeHtml(formatDisplayDate(purchaseOrder.po_date))}</div>
                            </div>
                        </section>

                        <table class="items">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Barang</th>
                                    <th>QTY</th>
                                    <th>Satuan</th>
                                    <th>Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${linesHtml}
                                ${blankRowsHtml}
                            </tbody>
                        </table>
                        <div class="footer-rule"></div>

                        <section class="signatures">
                            <div class="sign-box">
                                <div class="sign-title">${escapeHtml(printTemplate.requesterLabel)},</div>
                                <div class="sign-line"></div>
                                <div class="sign-name-space">${escapeHtml(requesterSigner.name || '-')}<br/>${escapeHtml(requesterSigner.title || '')}<br/>${escapeHtml(requesterSigner.licenseNo || '')}</div>
                            </div>
                            <div class="middle-logo"><span class="logo-symbol">✚</span><span><strong>artha</strong><br/>nusa</span></div>
                            <div class="sign-box">
                                <div class="sign-title">${escapeHtml(printTemplate.approverLabel)},</div>
                                <div class="sign-line"></div>
                                <div class="sign-name-space">${escapeHtml(approverSigner.name || '-')}<br/>${escapeHtml(approverSigner.title || '')}<br/>${escapeHtml(approverSigner.licenseNo || '')}</div>
                            </div>
                        </section>
                    </main>
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
            <Card title={`${poTypeLabel} ${purchaseOrder.po_number}`}>
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
                    <div><span className='font-semibold'>PO Date:</span> {formatDisplayDate(purchaseOrder.po_date)}</div>
                    <div><span className='font-semibold'>Expected Delivery:</span> {formatDisplayDate(purchaseOrder.expected_delivery_date)}</div>
                    <div><span className='font-semibold'>Approval Status:</span> {poStatus || '-'} </div>
                    <div><span className='font-semibold'>Receiving Status:</span> {fulfillmentLabel || '-'} </div>
                    <div><span className='font-semibold'>Total Ordered Qty:</span> {purchaseOrder.items.reduce((sum, i) => sum + Number(i.qty_ordered || 0), 0)}</div>
                    <div><span className='font-semibold'>Total Received Qty:</span> {purchaseOrder.items.reduce((sum, i) => sum + Number(i.received_qty ?? i.qty_received ?? 0), 0)}</div>
                    <div><span className='font-semibold'>Total Remaining Qty:</span> {purchaseOrder.items.reduce((sum, i) => sum + Number(i.remaining_qty ?? (Number(i.qty_ordered || 0) - Number(i.received_qty ?? i.qty_received ?? 0))), 0)}</div>
                    <div><span className='font-semibold'>Grand Total:</span> {Number(purchaseOrder.grand_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <div><span className='font-semibold'>PO Created By:</span> {createdBy}</div>
                    <div><span className='font-semibold'>PO Approved By:</span> {approvedBy}</div>
                    <div><span className='font-semibold'>Received By:</span> {receivedBy}</div>
                    <div><span className='font-semibold'>Warehouse:</span> {warehouseInfo}</div>
                    <div><span className='font-semibold'>Signer Pemohon:</span> {requesterSigner.name || '-'}</div>
                    <div><span className='font-semibold'>Signer Persetujuan:</span> {approverSigner.name || '-'}</div>
                    {purchaseOrder.usage_purpose && <div><span className='font-semibold'>Tujuan Penggunaan:</span> {purchaseOrder.usage_purpose}</div>}
                    {purchaseOrder.warehouse_address && <div><span className='font-semibold'>Alamat Gudang/Kebutuhan:</span> {purchaseOrder.warehouse_address}</div>}
                </div>

                <h3 className='mt-6 mb-2 text-sm font-semibold'>Detail PO</h3>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>Product</Table.Th>
                            <Table.Th>Zat Aktif</Table.Th>
                            <Table.Th>Bentuk/Kekuatan</Table.Th>
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
                                <Table.Td>{i.active_ingredient || '-'}</Table.Td>
                                <Table.Td>{i.dosage_form_strength || '-'}</Table.Td>
                                <Table.Td>{i.facility_name || i.facility_type || i.facility_scheme_name || '-'}</Table.Td>
                                <Table.Td>{i.facility_reference_no || i.facility_number || '-'}</Table.Td>
                                <Table.Td>{i.batch_number || i.batch_no || '-'}</Table.Td>
                                <Table.Td>{formatDisplayDate(i.expired_date || i.expiry_date)}</Table.Td>
                                <Table.Td>{i.qty_ordered}</Table.Td>
                                <Table.Td>{formatNumber(i.received_qty ?? i.qty_received, 2)}</Table.Td>
                                <Table.Td>{formatNumber(i.remaining_qty ?? ((+i.qty_ordered) - (+(i.received_qty ?? i.qty_received))), 2)}</Table.Td>
                                <Table.Td>{Number(i.line_total ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</Table.Td>
                            </tr>
                        ))}
                    </Table.Tbody>
                </Table>

                <h3 className='mt-6 mb-2 text-sm font-semibold'>Dokumen PO</h3>
                {renderUploadedDocumentsTable(poDocuments, 'Belum ada dokumen PO.')}

                <h3 className='mt-6 mb-2 text-sm font-semibold'>Receiving</h3>
                <Table>
                    <Table.Thead><tr><Table.Th>Reference No</Table.Th><Table.Th>Tanggal Penerimaan</Table.Th><Table.Th>Warehouse</Table.Th><Table.Th>Status</Table.Th><Table.Th>Received Qty</Table.Th><Table.Th>Total Value</Table.Th></tr></Table.Thead>
                    <Table.Tbody>{(purchaseOrder.goods_receipts || []).length ? (purchaseOrder.goods_receipts || []).map((gr) => <tr key={gr.id}><Table.Td>{gr.gr_number || gr.number || '-'}</Table.Td><Table.Td>{formatDisplayDate(gr.received_date || gr.document_date)}</Table.Td><Table.Td>{gr.warehouse_name || gr.warehouse?.name || gr.warehouse_code || gr.warehouse_id || '-'}</Table.Td><Table.Td>{gr.status}</Table.Td><Table.Td>{formatNumber(gr.total_qty ?? 0, 2)}</Table.Td><Table.Td>{Number(gr.total_value ?? 0).toLocaleString('id-ID')}</Table.Td></tr>) : <tr><Table.Td colSpan={6} className='text-center text-gray-500'>Belum ada receiving history.</Table.Td></tr>}</Table.Tbody>
                </Table>

                <h3 className='mt-6 mb-2 text-sm font-semibold'>Dokumen Receiving</h3>
                {renderUploadedDocumentsTable(receivingDocuments, 'Belum ada dokumen receiving.', true)}
            </Card>
        </>
    );
}

Show.layout = (page) => <AppLayout children={page} />;
