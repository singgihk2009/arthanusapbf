import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const dash = (value) => value || '-';
const formatQty = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = (value) => value || '-';

function CompanyMark({ company }) {
    if (company?.logo_path) {
        return <img src={`/storage/${company.logo_path}`} alt="Logo perusahaan" className="dispatch-print-logo" />;
    }

    return (
        <div className="dispatch-print-logo-fallback" aria-hidden="true">
            <span />
        </div>
    );
}

function DispatchPrintForm({ entry, lines, company }) {
    const printLines = lines.map((line, index) => ({ ...line, no: index + 1 }));
    const blankRows = Array.from({ length: Math.max(0, 10 - printLines.length) });
    const destinationCity = [entry.customer_city, entry.customer_province].filter(Boolean).join(' - ');

    return (
        <section className="dispatch-print-wrap" aria-label="Cetak Surat Pengiriman Barang">
            <div className="dispatch-print-sheet">
                <div className="dispatch-print-header">
                    <div className="dispatch-print-company">
                        <CompanyMark company={company} />
                        <div>
                            <div className="dispatch-print-company-name">{dash(company?.legal_name || 'PT. ARUTALA MAHA NUSANTARA')}</div>
                            <div className="dispatch-print-company-subtitle">PEDAGANG BESAR FARMASI DAN ALAT KESEHATAN</div>
                            <div>Jalan: {dash(company?.address)}{company?.city ? `, ${company.city}` : ''}{company?.province ? `, ${company.province}` : ''}</div>
                            <div>NPWP&nbsp;&nbsp;: {dash(company?.tax_id)}</div>
                            <div>No. Izin PBF&nbsp;&nbsp;: {dash(company?.pbf_license_number)} <span className="dispatch-print-spacer" /> CDOB&nbsp;&nbsp;&nbsp;&nbsp;: {dash(company?.cdob_other_license_number)}</div>
                            <div>No. Izin IDAK : {dash(company?.idak_license_number)} <span className="dispatch-print-spacer" /> CDOB CCP: {dash(company?.cdob_ccp_license_number)}</div>
                        </div>
                    </div>
                    <div className="dispatch-print-title-box">
                        <div className="dispatch-print-title">SURAT PENGIRIMAN BARANG</div>
                        <div className="dispatch-print-title-meta">
                            <div>Nomor&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {dash(entry.number)}</div>
                            <div>Tanggal&nbsp;&nbsp;&nbsp;: {formatDate(entry.document_date)}</div>
                        </div>
                    </div>
                </div>

                <div className="dispatch-print-rule" />

                <div className="dispatch-print-destination">
                    <div>
                        <div>Kepada Yth,</div>
                        <div className="dispatch-print-bold">{dash(entry.customer_name)} {entry.customer_phone ? `(${entry.customer_phone})` : ''}</div>
                        <div>{dash(entry.customer_address)}</div>
                        <div>{destinationCity || '-'}</div>
                    </div>
                    <div>
                        <div>IDOutlet&nbsp;&nbsp;: {dash(entry.customer_code)}</div>
                        <div>IDSales&nbsp;&nbsp;&nbsp;: {dash(entry.salesman_id)}</div>
                        <div>Sales&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {dash(entry.salesman_name)}</div>
                    </div>
                </div>

                <table className="dispatch-print-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Barang</th>
                            <th>Batch</th>
                            <th>Exp</th>
                            <th>Jml</th>
                            <th>Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {printLines.map((line) => (
                            <tr key={line.id}>
                                <td className="dispatch-print-num">{line.no}</td>
                                <td>{dash(line.item_name)}</td>
                                <td>{dash(line.batch_no)}</td>
                                <td>{formatDate(line.expired_date)}</td>
                                <td className="dispatch-print-num">{formatQty(line.qty_used)}</td>
                                <td>{dash(line.uom_code)}</td>
                            </tr>
                        ))}
                        {blankRows.map((_, index) => (
                            <tr className="dispatch-print-blank-row" key={`blank-${index}`}><td>&nbsp;</td><td /><td /><td /><td /><td /></tr>
                        ))}
                    </tbody>
                </table>

                <div className="dispatch-print-footer">
                    <div>
                        <div className="dispatch-print-note-label">Catatan / Ketentuan</div>
                        <div className="dispatch-print-note-box">{entry.notes || ''}</div>
                    </div>
                    <div className="dispatch-print-signature">
                        <div>Apoteker</div>
                        <div className="dispatch-print-sign-line" />
                        <div>SIPA: ........................................</div>
                    </div>
                    <div className="dispatch-print-signature">
                        <div>Hormat Kami,</div>
                        <div className="dispatch-print-sign-line" />
                        <div>Petugas Ekspedisi</div>
                    </div>
                </div>
            </div>
        </section>
    );
}

export default function Print({ entry, lines = [], company = null }) {
    return (
        <AppLayout>
            <Head title={`Print Dispatch ${entry.number}`}>
                <style>{`
                    .dispatch-print-wrap { display: none; }
                    @media print {
                        @page { size: 210mm 148mm; margin: 5mm 7mm; }
                        body { background: #fff !important; }
                        body * { visibility: hidden !important; }
                        .dispatch-print-wrap, .dispatch-print-wrap * { visibility: visible !important; }
                        .dispatch-print-wrap { display: block !important; position: absolute; inset: 0 auto auto 0; width: 100%; color: #000; }
                        .dispatch-print-sheet { width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; line-height: 1.15; }
                        .dispatch-print-header { display: grid; grid-template-columns: 1fr 205px; gap: 10px; }
                        .dispatch-print-company { display: grid; grid-template-columns: 42px 1fr; gap: 6px; }
                        .dispatch-print-logo { width: 34px; max-height: 42px; object-fit: contain; }
                        .dispatch-print-logo-fallback { position: relative; width: 34px; height: 42px; }
                        .dispatch-print-logo-fallback::before { content: ''; position: absolute; left: 11px; top: 7px; width: 7px; height: 30px; border-radius: 4px; background: #36bed0; box-shadow: 14px 0 0 #36bed0; }
                        .dispatch-print-logo-fallback span::before { content: '+'; position: absolute; left: 11px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #36bed0; color: #fff; text-align: center; font-weight: 700; line-height: 12px; font-size: 10px; }
                        .dispatch-print-company-name { font-size: 13px; font-weight: 800; letter-spacing: .2px; }
                        .dispatch-print-company-subtitle, .dispatch-print-bold, .dispatch-print-title { font-weight: 700; }
                        .dispatch-print-title-box { border-left: 1px solid #000; padding-left: 16px; }
                        .dispatch-print-title { margin-top: 8px; text-align: center; font-size: 11px; }
                        .dispatch-print-title-meta { margin-top: 32px; }
                        .dispatch-print-spacer { display: inline-block; width: 28px; }
                        .dispatch-print-rule { border-top: 1px solid #000; height: 0; margin: 3px 0 5px; }
                        .dispatch-print-destination { display: grid; grid-template-columns: 1fr 190px; gap: 12px; margin-bottom: 8px; }
                        .dispatch-print-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.5px; }
                        .dispatch-print-table th, .dispatch-print-table td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 1px 4px; overflow: hidden; white-space: nowrap; text-overflow: clip; font-weight: 400; }
                        .dispatch-print-table thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; text-align: center; font-weight: 700; }
                        .dispatch-print-table tbody tr:last-child td { border-bottom: 1px solid #000; }
                        .dispatch-print-table th:nth-child(1), .dispatch-print-table td:nth-child(1) { width: 24px; }
                        .dispatch-print-table th:nth-child(2), .dispatch-print-table td:nth-child(2) { width: auto; }
                        .dispatch-print-table th:nth-child(3), .dispatch-print-table td:nth-child(3) { width: 92px; text-align: center; }
                        .dispatch-print-table th:nth-child(4), .dispatch-print-table td:nth-child(4) { width: 58px; text-align: center; }
                        .dispatch-print-table th:nth-child(5), .dispatch-print-table td:nth-child(5) { width: 54px; }
                        .dispatch-print-table th:nth-child(6), .dispatch-print-table td:nth-child(6) { width: 62px; text-align: center; }
                        .dispatch-print-num { text-align: right; }
                        .dispatch-print-blank-row td { height: 12px; }
                        .dispatch-print-footer { display: grid; grid-template-columns: 265px 1fr 1fr; gap: 20px; margin-top: 22px; align-items: start; }
                        .dispatch-print-note-label { margin-bottom: -1px; text-align: center; }
                        .dispatch-print-note-box { min-height: 58px; border: 1px solid #000; padding: 5px; white-space: pre-wrap; }
                        .dispatch-print-signature { min-height: 75px; text-align: center; }
                        .dispatch-print-sign-line { margin: 58px auto 2px; width: 122px; border-top: 1px solid #000; }
                    }
                `}</style>
            </Head>

            <div className="space-y-4 p-6 print:hidden">
                <div className="rounded border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Surat Pengiriman Barang</h1>
                            <p className="text-sm text-gray-600 dark:text-gray-400">{entry.number} / {entry.document_date}</p>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Tujuan: {entry.customer_name}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => window.print()} className="rounded bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600">Print</button>
                            <Link href={route('apps.outbound.internal-usage.edit', { internalUsage: entry.id, view: 1 })} className="rounded border px-3 py-2 text-sm">View Dispatch</Link>
                            <Link href={route('apps.outbound.internal-usage.index')} className="rounded border px-3 py-2 text-sm">Back</Link>
                        </div>
                    </div>
                </div>

                <div className="rounded border bg-white p-4 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <p className="text-gray-600 dark:text-gray-400">Preview siap cetak untuk kertas 1/2 A4. Klik tombol Print untuk membuka dialog cetak browser.</p>
                </div>
            </div>

            <DispatchPrintForm entry={entry} lines={lines} company={company} />
        </AppLayout>
    );
}
