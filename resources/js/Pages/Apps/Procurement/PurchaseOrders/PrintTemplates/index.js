export const PO_PRINT_TEMPLATES = {
    regular: { title: 'PURCHASE ORDER', requesterLabel: 'Pemohon', approverLabel: 'Persetujuan' },
    precursor: { title: 'SURAT PESANAN PREKURSOR', requesterLabel: 'Hormat saya', approverLabel: 'Mengetahui' },
    psychotropic: { title: 'SURAT PESANAN PSIKOTROPIKA', requesterLabel: 'Hormat saya', approverLabel: 'Mengetahui' },
    oot: { title: 'PURCHASE ORDER OOT', requesterLabel: 'Pemohon', approverLabel: 'Persetujuan' },
    alkes: { title: 'PURCHASE ORDER ALKES', requesterLabel: 'Pemohon', approverLabel: 'Persetujuan' },
};

export const getPurchaseOrderPrintTemplate = (poType = 'regular') => PO_PRINT_TEMPLATES[poType] || PO_PRINT_TEMPLATES.regular;

export const getSignerDisplay = (profile, side) => {
    const employee = side === 'requester' ? profile?.requester_employee : profile?.approver_employee;
    return {
        name: employee?.full_name || profile?.[`${side}_name`] || '',
        title: employee?.position?.name || profile?.[`${side}_title`] || '',
        licenseNo: profile?.[`${side}_license_no`] || '',
    };
};

const escapeHtml = (value) => String(value ?? '-')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const displayDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? escapeHtml(value) : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

export const buildPrecursorPrintDocument = ({ purchaseOrder, company, requesterSigner, approverSigner }) => {
    const items = purchaseOrder.items || [];
    const companyAddress = [company?.address, company?.city, company?.province, company?.postal_code].filter(Boolean).join(', ');
    const vendorAddress = [purchaseOrder.vendor?.address, purchaseOrder.vendor?.city, purchaseOrder.vendor?.province].filter(Boolean).join(', ');
    const logo = company?.logo_path
        ? `<img src="/storage/${escapeHtml(company.logo_path)}" alt="Logo perusahaan" />`
        : '<div class="wordmark"><b>AN</b><span>artha<br/>nusa</span></div>';
    const rows = items.map((item, index) => `
        <tr>
            <td>${index + 1}.</td>
            <td>${escapeHtml(item.product?.name || item.product_name)}</td>
            <td>${escapeHtml(item.active_ingredient)}</td>
            <td>${escapeHtml(item.dosage_form_strength)}</td>
            <td>${escapeHtml(item.uom?.name || item.uom_name || item.unit || 'FLS')}</td>
            <td>${Number(item.qty_ordered || 0).toLocaleString('id-ID')}</td>
            <td>${escapeHtml(item.regulatory_note || item.notes || '')}</td>
        </tr>`).join('');

    return `<!DOCTYPE html>
<html><head><meta charset="utf-8"/><title>Surat Pesanan Prekursor ${escapeHtml(purchaseOrder.po_number)}</title>
<style>
@page{size:A4 portrait;margin:12mm 16mm}*{box-sizing:border-box}body{margin:0;color:#111;background:#fff;font-family:Arial,sans-serif;font-size:10.5px;line-height:1.32}.sheet{width:100%;max-width:178mm;margin:auto}.letterhead{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:4px solid #50aae1;padding:0 8px 5px}.letterhead img{width:135px;max-height:58px;object-fit:contain}.wordmark{display:flex;align-items:center;color:#59b8e9;font-size:23px;line-height:.8}.wordmark b{font-size:27px;margin-right:5px}.company-address{max-width:250px;text-align:right;font-size:8px}.date{text-align:right;margin:8px 0 3px}.meta{border-collapse:collapse;width:auto;margin-bottom:8px}.meta td{padding:0 5px 0 0;vertical-align:top}.meta td:first-child{width:58px}.subject{font-weight:700;text-decoration:underline}.recipient{margin:8px 0 10px}.salutation{margin-bottom:5px}.indent{margin-left:18px}.identity{border-collapse:collapse;margin:2px 0 5px 18px}.identity td{padding:0 5px 0 0;vertical-align:top}.identity td:first-child{width:112px}.identity td:nth-child(2){width:8px}.order-table{width:100%;border-collapse:collapse;margin:5px 0}.order-table th,.order-table td{border:1px solid #777;padding:3px;text-align:center;vertical-align:top}.order-table th{font-weight:400}.order-table th:nth-child(1){width:7%}.order-table th:nth-child(2){width:29%}.order-table th:nth-child(3){width:19%}.order-table th:nth-child(4){width:15%}.order-table th:nth-child(5){width:9%}.order-table th:nth-child(6){width:9%}.order-table td:nth-child(2),.order-table td:nth-child(3){text-align:left}.closing{margin-top:7px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:70px;margin-top:12px;text-align:center}.sign-space{height:52px}.sign-name{font-weight:700;text-decoration:underline}.sign-license{font-size:8px}.stamp{color:#79c7e8;font-size:22px;opacity:.55}.stamp img{max-width:110px;max-height:45px;object-fit:contain}
</style></head><body><main class="sheet">
<header class="letterhead"><div>${logo}</div><div class="company-address"><b>${escapeHtml(company?.legal_name || company?.name || 'PT. ARUTALA MAHA NUSANTARA')}</b><br/>${escapeHtml(companyAddress)}<br/>Telp. ${escapeHtml(company?.phone || company?.mobile || '-')}</div></header>
<div class="date">${escapeHtml(company?.city || 'Cianjur')}, ${displayDate(purchaseOrder.po_date)}</div>
<table class="meta"><tbody><tr><td>Nomor</td><td>:</td><td>${escapeHtml(purchaseOrder.po_number)}</td></tr><tr><td>Perihal</td><td>:</td><td class="subject">SURAT PESANAN PREKURSOR</td></tr><tr><td>Lampiran</td><td>:</td><td>-</td></tr></tbody></table>
<div class="recipient">Yth.<br/><b>${escapeHtml(purchaseOrder.vendor?.name)}</b><br/>${escapeHtml(vendorAddress || 'di Tempat')}</div>
<div class="salutation">Dengan hormat,</div><div class="indent">Melalui surat ini saya:</div>
<table class="identity"><tbody><tr><td>Nama</td><td>:</td><td>${escapeHtml(requesterSigner.name)}</td></tr><tr><td>Jabatan</td><td>:</td><td>${escapeHtml(requesterSigner.title || 'Apoteker Penanggung Jawab')}</td></tr><tr><td>No. SIPA</td><td>:</td><td>${escapeHtml(requesterSigner.licenseNo)}</td></tr><tr><td>No. Izin PBF</td><td>:</td><td>${escapeHtml(company?.pbf_license_number)}</td></tr><tr><td>No. CDOB Obat</td><td>:</td><td>${escapeHtml(company?.cdob_other_license_number)}</td></tr><tr><td>No. CDOB CCP</td><td>:</td><td>${escapeHtml(company?.cdob_ccp_license_number)}</td></tr></tbody></table>
<div class="indent">Mengajukan pesanan obat yang mengandung Prekursor Farmasi kepada:</div>
<table class="identity"><tbody><tr><td>Nama Industri Farmasi/PBF</td><td>:</td><td>${escapeHtml(purchaseOrder.vendor?.name)}</td></tr><tr><td>Alamat</td><td>:</td><td>${escapeHtml(vendorAddress)}</td></tr><tr><td>No. Telp</td><td>:</td><td>${escapeHtml(purchaseOrder.vendor?.phone)}</td></tr></tbody></table>
<div class="indent">Jenis obat mengandung Prekursor Farmasi yang dipesan adalah:</div>
<table class="order-table"><thead><tr><th>No.</th><th>Nama Obat Mengandung Prekursor Farmasi</th><th>Zat Aktif Prekursor Farmasi</th><th>Bentuk dan Kekuatan Obat</th><th>Satuan</th><th>Jumlah</th><th>Ket.</th></tr></thead><tbody>${rows || '<tr><td colspan="7">Tidak ada item</td></tr>'}</tbody></table>
<div class="closing">Obat mengandung Prekursor Farmasi tersebut akan digunakan untuk ${escapeHtml(purchaseOrder.usage_purpose || 'memenuhi kebutuhan')}<br/>Nama PBF: ${escapeHtml(company?.legal_name || company?.name)}<br/>Alamat Lengkap: ${escapeHtml(purchaseOrder.warehouse_address || companyAddress)}</div>
<section class="signatures"><div><div>Mengetahui,</div><div class="sign-space"></div><div class="sign-name">${escapeHtml(approverSigner.name)}</div><div>${escapeHtml(approverSigner.title || 'Direktur')}</div></div><div><div>Hormat saya,</div><div class="sign-space stamp">${logo}</div><div class="sign-name">${escapeHtml(requesterSigner.name)}</div><div>${escapeHtml(requesterSigner.title || 'Apoteker Penanggung Jawab')}</div><div class="sign-license">${escapeHtml(requesterSigner.licenseNo)}</div></div></section>
</main></body></html>`;
};
