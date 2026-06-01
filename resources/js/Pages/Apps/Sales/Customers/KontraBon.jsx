import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });
const formatMoneyPlain = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dash = (value) => value || '-';

export default function Page({ customer, invoices = [], company = null, document }) {
  const totalBalance = Number(document?.total_balance || 0);

  return (
    <AppLayout>
      <Head title={`Kontra Bon ${customer.customer_name}`}>
        <style>{`
          .kontra-print { display: none; }
          @media print {
            @page { size: 210mm 148mm; margin: 7mm 8mm; }
            body { background: #fff !important; }
            body * { visibility: hidden !important; }
            .kontra-print, .kontra-print * { visibility: visible !important; }
            .kontra-print { display: block !important; position: absolute; inset: 0 auto auto 0; width: 100%; color: #000; }
            .kontra-doc { font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.25; }
            .kontra-header { display: grid; grid-template-columns: 1fr 190px; gap: 12px; align-items: start; }
            .kontra-company { font-size: 9px; }
            .kontra-title { border: 1px solid #000; padding: 6px; text-align: center; }
            .kontra-title h1 { margin: 0; font-size: 16px; letter-spacing: 1px; }
            .kontra-title div { margin-top: 3px; font-size: 9px; }
            .kontra-rule { border-top: 1px solid #000; height: 0; margin: 7px 0; }
            .kontra-meta { display: grid; grid-template-columns: 1fr 190px; gap: 12px; }
            .kontra-meta-row { display: grid; grid-template-columns: 74px 8px 1fr; margin-bottom: 2px; }
            .kontra-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 7px; }
            .kontra-table th, .kontra-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
            .kontra-table th { text-align: center; font-weight: 700; }
            .kontra-table th:nth-child(1), .kontra-table td:nth-child(1) { width: 22px; text-align: center; }
            .kontra-table th:nth-child(2), .kontra-table td:nth-child(2) { width: 118px; }
            .kontra-table th:nth-child(3), .kontra-table td:nth-child(3), .kontra-table th:nth-child(4), .kontra-table td:nth-child(4) { width: 68px; }
            .kontra-table th:nth-child(5), .kontra-table td:nth-child(5) { width: 46px; text-align: right; }
            .kontra-table th:nth-child(6), .kontra-table td:nth-child(6), .kontra-table th:nth-child(7), .kontra-table td:nth-child(7), .kontra-table th:nth-child(8), .kontra-table td:nth-child(8) { width: 86px; }
            .num { text-align: right; }
            .kontra-note { margin-top: 7px; border: 1px dashed #000; padding: 5px; font-size: 9px; }
            .kontra-signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-top: 12px; text-align: center; }
            .kontra-sign-box { min-height: 48px; display: flex; flex-direction: column; justify-content: space-between; }
            .kontra-sign-line { border-top: 1px solid #000; padding-top: 3px; }
            .kontra-empty { border: 1px solid #000; padding: 10px; text-align: center; }
          }
        `}</style>
      </Head>

      <div className='screen-kontra-bon space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-3'>
            <div>
              <h1 className='text-xl font-semibold'>Kontra Bon</h1>
              <p className='text-sm text-gray-600'>{customer.customer_name} ({customer.customer_code})</p>
              <p className='text-sm text-gray-600'>{invoices.length} invoice jatuh tempo · Total {formatCurrency(totalBalance)}</p>
              <p className='mt-1 text-xs text-amber-700'>Kontra Bon hanya surat reminder/tagihan untuk collector, bukan invoice dan tidak membuat recording transaksi.</p>
            </div>
            <div className='flex flex-wrap gap-2'>
              <button type='button' onClick={() => window.print()} className='rounded bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600'>Cetak Kontra Bon</button>
              <Link href={route('apps.customers.show', customer.id)} className='rounded border px-3 py-2 text-sm'>Back to Customer</Link>
            </div>
          </div>
        </div>

        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='overflow-x-auto'>
            <table className='min-w-full text-sm'>
              <thead className='bg-gray-50'>
                <tr><th className='px-3 py-2 text-left'>Invoice</th><th className='px-3 py-2 text-left'>Tanggal</th><th className='px-3 py-2 text-left'>Due Date</th><th className='px-3 py-2 text-right'>Hari Lewat</th><th className='px-3 py-2 text-right'>Grand Total</th><th className='px-3 py-2 text-right'>Paid</th><th className='px-3 py-2 text-right'>Balance</th></tr>
              </thead>
              <tbody className='divide-y'>
                {!invoices.length && <tr><td colSpan={7} className='px-3 py-4 text-center text-gray-500'>Tidak ada invoice jatuh tempo yang belum dibayar pada pilihan ini.</td></tr>}
                {invoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td className='px-3 py-2'>{invoice.number}</td>
                    <td className='px-3 py-2'>{invoice.invoice_date}</td>
                    <td className='px-3 py-2'>{invoice.due_date}</td>
                    <td className='px-3 py-2 text-right'>{invoice.days_overdue}</td>
                    <td className='px-3 py-2 text-right'>{formatCurrency(invoice.grand_total)}</td>
                    <td className='px-3 py-2 text-right'>{formatCurrency(invoice.amount_paid)}</td>
                    <td className='px-3 py-2 text-right font-semibold'>{formatCurrency(invoice.balance_due)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <section className='kontra-print' aria-label='Cetak Kontra Bon'>
        <div className='kontra-doc'>
          <div className='kontra-header'>
            <div className='kontra-company'>
              <div><b>{dash(company?.legal_name || 'PT. ARUTALA MAHA NUSANTARA')}</b></div>
              <div>{dash(company?.address)} {company?.city ? `, ${company.city}` : ''} {company?.province ? `, ${company.province}` : ''}</div>
              <div>NPWP: {dash(company?.tax_id)} · Telp: {dash(company?.phone)}</div>
            </div>
            <div className='kontra-title'>
              <h1>KONTRA BON</h1>
              <div>Surat reminder tagihan — bukan invoice</div>
            </div>
          </div>

          <div className='kontra-rule' />
          <div className='kontra-meta'>
            <div>
              <div className='kontra-meta-row'><span>Kepada</span><span>:</span><b>{dash(customer.customer_name)}</b></div>
              <div className='kontra-meta-row'><span>Kode</span><span>:</span><span>{dash(customer.customer_code)}</span></div>
              <div className='kontra-meta-row'><span>Alamat</span><span>:</span><span>{[customer.address, customer.city, customer.province, customer.postal_code].filter(Boolean).join(', ') || '-'}</span></div>
              <div className='kontra-meta-row'><span>Kontak</span><span>:</span><span>{dash(customer.contact_person)} / {dash(customer.phone)}</span></div>
            </div>
            <div>
              <div className='kontra-meta-row'><span>No. KB</span><span>:</span><span>{dash(document?.number)}</span></div>
              <div className='kontra-meta-row'><span>Tanggal</span><span>:</span><span>{dash(document?.date)}</span></div>
              <div className='kontra-meta-row'><span>Collector</span><span>:</span><span>________________</span></div>
              <div className='kontra-meta-row'><span>Cetak</span><span>:</span><span>{dash(document?.printed_at)}</span></div>
            </div>
          </div>

          {invoices.length ? (
            <table className='kontra-table'>
              <thead>
                <tr><th>No</th><th>No. Invoice</th><th>Tgl Invoice</th><th>Jatuh Tempo</th><th>Hari</th><th>Nilai Invoice</th><th>Terbayar</th><th>Sisa Tagihan</th></tr>
              </thead>
              <tbody>
                {invoices.map((invoice, index) => (
                  <tr key={invoice.id}>
                    <td>{index + 1}</td>
                    <td>{invoice.number}</td>
                    <td>{invoice.invoice_date}</td>
                    <td>{invoice.due_date}</td>
                    <td>{invoice.days_overdue}</td>
                    <td className='num'>{formatMoneyPlain(invoice.grand_total)}</td>
                    <td className='num'>{formatMoneyPlain(invoice.amount_paid)}</td>
                    <td className='num'>{formatMoneyPlain(invoice.balance_due)}</td>
                  </tr>
                ))}
                <tr>
                  <td colSpan={7} className='num'><b>Total Sisa Tagihan</b></td>
                  <td className='num'><b>{formatMoneyPlain(totalBalance)}</b></td>
                </tr>
              </tbody>
            </table>
          ) : (
            <div className='kontra-empty'>Tidak ada invoice jatuh tempo yang belum dibayar pada pilihan ini.</div>
          )}

          <div className='kontra-note'>
            Catatan: Kontra Bon ini bukan invoice/faktur pajak dan tidak mengubah pencatatan piutang. Dokumen ini hanya pengingat untuk penagihan invoice yang sudah jatuh tempo namun belum lunas.
          </div>

          <div className='kontra-signatures'>
            <div className='kontra-sign-box'><div>Disiapkan Oleh,</div><div className='kontra-sign-line'>Finance/Admin</div></div>
            <div className='kontra-sign-box'><div>Dibawa Oleh,</div><div className='kontra-sign-line'>Collector</div></div>
            <div className='kontra-sign-box'><div>Diterima Customer,</div><div className='kontra-sign-line'>Nama / Tanggal / Stempel</div></div>
          </div>
        </div>
      </section>
    </AppLayout>
  );
}
