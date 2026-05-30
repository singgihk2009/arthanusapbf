import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });
const formatMoneyPlain = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatQty = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dash = (value) => value || '-';

const numberWords = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
const spellNumber = (value) => {
  const number = Math.floor(Math.abs(Number(value || 0)));
  if (number < 12) return numberWords[number];
  if (number < 20) return `${spellNumber(number - 10)} Belas`;
  if (number < 100) return `${spellNumber(Math.floor(number / 10))} Puluh ${spellNumber(number % 10)}`.trim();
  if (number < 200) return `Seratus ${spellNumber(number - 100)}`.trim();
  if (number < 1000) return `${spellNumber(Math.floor(number / 100))} Ratus ${spellNumber(number % 100)}`.trim();
  if (number < 2000) return `Seribu ${spellNumber(number - 1000)}`.trim();
  if (number < 1000000) return `${spellNumber(Math.floor(number / 1000))} Ribu ${spellNumber(number % 1000)}`.trim();
  if (number < 1000000000) return `${spellNumber(Math.floor(number / 1000000))} Juta ${spellNumber(number % 1000000)}`.trim();
  if (number < 1000000000000) return `${spellNumber(Math.floor(number / 1000000000))} Milyar ${spellNumber(number % 1000000000)}`.trim();
  return `${spellNumber(Math.floor(number / 1000000000000))} Triliun ${spellNumber(number % 1000000000000)}`.trim();
};
const terbilang = (value) => `${spellNumber(value).replace(/\s+/g, ' ').trim() || 'Nol'} Rupiah`;

function DotMatrixInvoice({ invoice, lines, dispatches, company }) {
  const printLines = lines.map((line, index) => ({
    ...line,
    no: index + 1,
    discountAmount: Number(line.discount_amount || 0),
    discountPercent: Number(line.discount_percent || 0),
    total: Number(line.line_total || 0),
  }));
  const discount1 = Number(invoice.discount_total || 0);
  const totalDiscount2 = printLines.reduce((sum, line) => sum + Number(line.discountAmount || 0), 0);
  const orderNumber = invoice.sales_order_number || invoice.source_number || dispatches[0]?.source_number;
  const orderDate = invoice.sales_order_date || dispatches[0]?.document_date;
  const salesLabel = invoice.salesman_name || invoice.customer_salesman_name || invoice.posted_by_name;

  return (
    <section className='print-invoice-wrap' aria-label='Cetak Faktur Penjualan'>
      <div className='print-invoice'>
        <div className='print-header'>
          <div>
            <div>{dash(company?.legal_name || 'PT. ARUTALA MAHA NUSANTARA')}</div>
            <div>PEDAGANG BESAR FARMASI DAN ALAT KESEHATAN</div>
            <div>Jalan : {dash(company?.address)} {company?.city ? `, ${company.city}` : ''} {company?.province ? `, ${company.province}` : ''}</div>
            <div>NPWP&nbsp;&nbsp;: {dash(company?.tax_id)}</div>
            <div>No. Izin PBF&nbsp;&nbsp;: {dash(company?.pbf_license_number)} <span className='print-spacer' /> CDOB&nbsp;&nbsp;&nbsp;&nbsp;: {dash(company?.cdob_other_license_number)}</div>
            <div>No. Izin IDAK : {dash(company?.idak_license_number)} <span className='print-spacer' /> CDOB CCP: {dash(company?.cdob_ccp_license_number)}</div>
          </div>
          <div className='print-title-box'>
            <div className='print-title'>FAKTUR PENJUALAN</div>
            <div>No. Order&nbsp;&nbsp;: {dash(orderNumber)}</div>
            <div>Tgl Order : {dash(orderDate)}</div>
            <div>No. Faktur : {dash(invoice.number)}</div>
            <div>Tgl Faktur: {dash(invoice.invoice_date)}</div>
          </div>
        </div>

        <div className='print-rule' />
        <div className='print-customer'>
          <div>
            <div>Kepada Yth.</div>
            <div>{dash(invoice.customer_name)} {invoice.customer_phone ? `(${invoice.customer_phone})` : ''}</div>
            <div>Jl. {dash(invoice.customer_address)}</div>
            <div>{[invoice.customer_city, invoice.customer_province].filter(Boolean).join(' - ') || '-'}</div>
            <div>NPWP : {dash(invoice.customer_npwp)}</div>
          </div>
          <div>
            <div>IDOutlet&nbsp;&nbsp;: {dash(invoice.customer_code)}</div>
            <div>IDSales&nbsp;&nbsp;&nbsp;: {dash(invoice.salesman_id || invoice.customer_salesman_id)}</div>
            <div>Sales&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {dash(salesLabel)}</div>
          </div>
        </div>
        <div className='print-rule' />

        <table className='print-table'>
          <thead>
            <tr>
              <th>No.</th>
              <th>Nama Barang</th>
              <th>Batch</th>
              <th>Exp</th>
              <th>Jml</th>
              <th>Satuan</th>
              <th>Harga</th>
              <th>Rp. Disk</th>
              <th>Rp. Sub Total</th>
            </tr>
          </thead>
          <tbody>
            {printLines.map((line) => (
              <tr key={line.id}>
                <td className='num'>{line.no}</td>
                <td>{dash(line.item_name)}</td>
                <td>{dash(line.batch_no)}</td>
                <td>{dash(line.expired_date)}</td>
                <td className='num'>{formatQty(line.qty)}</td>
                <td>{dash(line.uom_code)}</td>
                <td className='money'>{formatMoneyPlain(line.unit_price)}</td>
                <td className='money'>{formatMoneyPlain(line.discountAmount || (Number(line.unit_price || 0) * Number(line.qty || 0) * line.discountPercent / 100))}</td>
                <td className='money'>{formatMoneyPlain(line.total)}</td>
              </tr>
            ))}
            {Array.from({ length: Math.max(0, 10 - printLines.length) }).map((_, index) => (
              <tr className='blank-row' key={`blank-${index}`}><td>&nbsp;</td><td /><td /><td /><td /><td /><td /><td /><td /></tr>
            ))}
          </tbody>
        </table>

        <div className='print-rule' />
        <div className='print-footer-grid'>
          <div>
            <div>Terbilang : <strong>{terbilang(invoice.grand_total)}</strong></div>
            <div className='print-payment-note'>Pembayaran: CEK/BG/TRANSFER, melalui rekening BANK CENTRAL ASIA</div>
            <div className='print-payment-note indent'>No. Rekening: 1832721934, a.n. {dash(company?.legal_name || 'ARUTALA MAHA NUSANTARA PT')}</div>
          </div>
          <div className='print-totals'>
            <div><span>Sebelum Diskon</span><span>Rp.</span><span>{formatMoneyPlain(invoice.subtotal)}</span></div>
            <div><span>Total Diskon 1</span><span>Rp.</span><span>{formatMoneyPlain(discount1)}</span></div>
            <div><span>Total Diskon 2</span><span>Rp.</span><span>{formatMoneyPlain(totalDiscount2)}</span></div>
            <div className='print-total-line'><span>Harus Dibayar</span><span>Rp.</span><span>{formatMoneyPlain(invoice.grand_total)}</span></div>
          </div>
        </div>
        <div className='print-rule' />
      </div>
    </section>
  );
}

export default function Page({ invoice, lines = [], dispatches = [], company = null }) {
  const postInvoice = () => {
    if (!confirm('Post invoice ini? Setelah diposting, qty invoiced Sales Order akan diperbarui.')) return;
    router.post(route('apps.customer-invoices.post', invoice.id));
  };

  return (
    <AppLayout>
      <Head title={`Invoice ${invoice.number}`}>
        <style>{`
          .print-invoice-wrap { display: none; }
          @media print {
            @page { size: 210mm 148mm; margin: 5mm 6mm; }
            body { background: #fff !important; }
            body * { visibility: hidden !important; }
            .print-invoice-wrap, .print-invoice-wrap * { visibility: visible !important; }
            .print-invoice-wrap { display: block !important; position: absolute; inset: 0 auto auto 0; width: 100%; color: #000; }
            .print-invoice { font-family: "Courier New", Courier, monospace; font-size: 10px; line-height: 1.18; letter-spacing: -0.2px; }
            .print-header { display: grid; grid-template-columns: 1fr 190px; gap: 10px; }
            .print-title { text-align: center; font-weight: 700; margin-bottom: 8px; }
            .print-rule { border-top: 1px dashed #000; height: 0; margin: 5px 0; }
            .print-spacer { display: inline-block; width: 36px; }
            .print-customer { display: grid; grid-template-columns: 1fr 190px; gap: 10px; }
            .print-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .print-table th, .print-table td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 1px 4px; overflow: hidden; white-space: nowrap; text-overflow: clip; font-weight: 400; }
            .print-table thead th { border-top: 1px dashed #000; border-bottom: 1px dashed #000; text-align: center; }
            .print-table th:nth-child(1), .print-table td:nth-child(1) { width: 24px; }
            .print-table th:nth-child(2), .print-table td:nth-child(2) { width: 185px; }
            .print-table th:nth-child(3), .print-table td:nth-child(3) { width: 72px; }
            .print-table th:nth-child(4), .print-table td:nth-child(4) { width: 48px; }
            .print-table th:nth-child(5), .print-table td:nth-child(5) { width: 45px; }
            .print-table th:nth-child(6), .print-table td:nth-child(6) { width: 48px; }
            .print-table th:nth-child(7), .print-table td:nth-child(7) { width: 64px; }
            .print-table th:nth-child(8), .print-table td:nth-child(8) { width: 64px; }
            .print-table th:nth-child(9), .print-table td:nth-child(9) { width: 78px; }
            .num, .money { text-align: right; }
            .blank-row td { height: 14px; }
            .print-footer-grid { display: grid; grid-template-columns: 1fr 220px; gap: 10px; }
            .print-payment-note { margin-top: 18px; }
            .print-payment-note.indent { margin-top: 0; padding-left: 78px; }
            .print-totals > div { display: grid; grid-template-columns: 1fr 25px 82px; gap: 4px; }
            .print-totals span:last-child { text-align: right; }
            .print-total-line { border-top: 1px dashed #000; margin-top: 4px; padding-top: 3px; font-weight: 700; }
          }
        `}</style>
      </Head>
      <div className='screen-invoice space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-3'>
            <div>
              <h1 className='text-xl font-semibold'>{invoice.number}</h1>
              <p className='text-sm text-gray-600'>{invoice.customer_name} ({invoice.customer_code})</p>
              <p className='text-sm text-gray-600'>Status: <span className='font-semibold uppercase'>{invoice.status}</span></p>
            </div>
            <div className='flex flex-wrap gap-2'>
              <button type='button' onClick={() => window.print()} className='rounded bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600'>Print Invoice</button>
              {invoice.status === 'draft' && <button type='button' onClick={postInvoice} className='rounded bg-indigo-600 px-3 py-2 text-sm text-white'>Post Invoice</button>}
              {['posted', 'partially_paid', 'overdue'].includes(String(invoice.status || '').toLowerCase()) && Number(invoice.balance_due || 0) > 0 && <Link href={`/apps/customer-payments/create?invoice_ids=${invoice.id}`} className='rounded bg-emerald-600 px-3 py-2 text-sm text-white'>Collect Payment</Link>}
              <Link href={route('apps.customer-invoices.index')} className='rounded border px-3 py-2 text-sm'>Back to List</Link>
            </div>
          </div>
        </div>

        <div className='grid gap-4 lg:grid-cols-3'>
          <div className='space-y-4 lg:col-span-2'>
            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Dispatch Referensi</h2>
              <div className='flex flex-wrap gap-2 text-sm'>
                {dispatches.map((dispatch) => <span key={dispatch.id} className='rounded border px-2 py-1'>{dispatch.number} / {dispatch.source_number}</span>)}
              </div>
            </div>
            <div className='rounded border bg-white p-4 shadow-sm'>
              <h2 className='mb-3 font-semibold'>Line Invoice</h2>
              <div className='overflow-x-auto'>
                <table className='min-w-full text-sm'>
                  <thead className='bg-gray-50'>
                    <tr><th className='px-3 py-2 text-left'>Dispatch</th><th className='px-3 py-2 text-left'>Item</th><th className='px-3 py-2 text-left'>Batch</th><th className='px-3 py-2 text-right'>Qty</th><th className='px-3 py-2 text-left'>UOM</th><th className='px-3 py-2 text-right'>Harga</th><th className='px-3 py-2 text-right'>Total</th></tr>
                  </thead>
                  <tbody className='divide-y'>
                    {lines.map((line) => (
                      <tr key={line.id}>
                        <td className='px-3 py-2'>{line.dispatch_number}</td>
                        <td className='px-3 py-2'>{line.item_sku} - {line.item_name}</td>
                        <td className='px-3 py-2'>{line.batch_no || '-'}</td>
                        <td className='px-3 py-2 text-right'>{Number(line.qty || 0).toLocaleString('id-ID')}</td>
                        <td className='px-3 py-2'>{line.uom_code}</td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(line.unit_price)}</td>
                        <td className='px-3 py-2 text-right'>{formatCurrency(line.line_total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div className='rounded border bg-white p-4 shadow-sm'>
            <h2 className='mb-3 font-semibold'>Ringkasan</h2>
            <div className='space-y-2 text-sm'>
              <div className='flex justify-between'><span>Tanggal</span><b>{invoice.invoice_date}</b></div>
              <div className='flex justify-between'><span>Jatuh Tempo</span><b>{invoice.due_date || '-'}</b></div>
              <div className='flex justify-between'><span>Subtotal</span><b>{formatCurrency(invoice.subtotal)}</b></div>
              <div className='flex justify-between'><span>Diskon</span><b>- {formatCurrency(invoice.discount_total)}</b></div>
              <div className='flex justify-between'><span>Biaya Kirim</span><b>{formatCurrency(invoice.freight_amount)}</b></div>
              <div className='flex justify-between'><span>PPN</span><b>{formatCurrency(invoice.tax_total)}</b></div>
              <div className='flex justify-between border-t pt-2 text-base'><span>Grand Total</span><b>{formatCurrency(invoice.grand_total)}</b></div>
              <div className='flex justify-between'><span>Balance Due</span><b>{formatCurrency(invoice.balance_due)}</b></div>
            </div>
          </div>
        </div>
      </div>
      <DotMatrixInvoice invoice={invoice} lines={lines} dispatches={dispatches} company={company} />
    </AppLayout>
  );
}
