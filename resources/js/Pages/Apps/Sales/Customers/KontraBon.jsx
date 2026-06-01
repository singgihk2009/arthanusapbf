import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });
const formatMoneyPlain = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dash = (value) => value || '-';
const addressLine = (...parts) => parts.filter(Boolean).join(', ') || '-';

export default function Page({ customer, invoices = [], company = null, bankAccount = null, document }) {
  const totalBalance = Number(document?.total_balance || 0);
  const bankName = bankAccount?.bank_name || 'BANK CENTRAL ASIA';
  const bankNumber = bankAccount?.account_number || '1832721934';
  const bankHolder = bankAccount?.account_holder_name || company?.legal_name || 'ARUTALA MAHA NUSANTARA PT';
  const returnDate = invoices.map((invoice) => invoice.due_date).filter(Boolean).sort().at(-1) || document?.date;
  const salesLabel = customer.salesman_name || customer.sales_name || customer.sales || '';

  return (
    <AppLayout>
      <Head title={`Kontra Bon ${customer.customer_name}`}>
        <style>{`
          .kontra-print { display: none; }
          @media print {
            @page { size: 210mm 148mm; margin: 4mm 6mm; }
            body { background: #fff !important; }
            body * { visibility: hidden !important; }
            .kontra-print, .kontra-print * { visibility: visible !important; }
            .kontra-print { display: block !important; position: absolute; inset: 0 auto auto 0; width: 100%; color: #000; }
            .kontra-form { font-family: "Courier New", Courier, monospace; font-size: 10px; line-height: 1.12; letter-spacing: -0.25px; }
            .kb-grid { display: grid; grid-template-columns: 1fr 330px; }
            .kb-company { padding: 0 10px 3px 42px; }
            .kb-title-box { border-left: 1px solid #000; padding: 1px 0 3px 12px; }
            .kb-title { font-weight: 700; margin-bottom: 17px; }
            .kb-row { display: grid; grid-template-columns: 76px 8px 1fr; white-space: nowrap; }
            .kb-license { display: grid; grid-template-columns: 410px 1fr; }
            .kb-rule { border-top: 1px solid #000; height: 0; }
            .kb-customer { display: grid; grid-template-columns: 1fr 330px; padding: 4px 0 8px 0; }
            .kb-customer-left { padding-left: 24px; }
            .kb-customer-right { padding-left: 50px; }
            .kb-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .kb-table th, .kb-table td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 2px 8px; font-weight: 400; vertical-align: top; }
            .kb-table thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; text-align: center; }
            .kb-table th:nth-child(1), .kb-table td:nth-child(1) { width: 42px; text-align: center; }
            .kb-table th:nth-child(2), .kb-table td:nth-child(2) { width: 120px; }
            .kb-table th:nth-child(3), .kb-table td:nth-child(3) { width: 200px; }
            .kb-table th:nth-child(4), .kb-table td:nth-child(4) { width: 210px; }
            .kb-table th:nth-child(5), .kb-table td:nth-child(5) { width: 160px; }
            .kb-table th:nth-child(6), .kb-table td:nth-child(6) { width: auto; }
            .kb-line-row td { height: 112px; }
            .kb-empty-row td { height: 112px; text-align: center; padding-top: 16px; }
            .kb-total-row td { border-top: 1px solid #000; border-bottom: 1px solid #000; height: 22px; }
            .kb-double-rule { border-top: 1px solid #000; border-bottom: 1px solid #000; height: 3px; margin-top: 8px; }
            .kb-num { text-align: right; white-space: nowrap; }
            .kb-bottom { display: grid; grid-template-columns: 505px 1fr 320px; gap: 28px; padding-top: 14px; }
            .kb-payment-box { border: 1px solid #000; padding: 6px 10px; min-height: 74px; }
            .kb-payment-title { margin-bottom: 2px; }
            .kb-sign { text-align: center; min-height: 95px; display: flex; flex-direction: column; justify-content: flex-end; }
            .kb-sign-label { margin-bottom: 62px; }
            .kb-sign-line { border-top: 1px solid #000; padding-top: 2px; }
            .kb-company-sign { text-align: center; padding-top: 62px; }
            .kb-note { margin-top: 2px; font-size: 8.5px; }
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
              <p className='mt-1 text-xs text-amber-700'>Layout cetak mengikuti form dot-matrix Kontra Bon dan tidak membuat recording transaksi.</p>
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
                <tr><th className='px-3 py-2 text-left'>Tanggal</th><th className='px-3 py-2 text-left'>No. Trx</th><th className='px-3 py-2 text-left'>No. Faktur</th><th className='px-3 py-2 text-right'>Jumlah</th><th className='px-3 py-2 text-left'>Keterangan</th></tr>
              </thead>
              <tbody className='divide-y'>
                {!invoices.length && <tr><td colSpan={5} className='px-3 py-4 text-center text-gray-500'>Tidak ada invoice jatuh tempo yang belum dibayar pada pilihan ini.</td></tr>}
                {invoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td className='px-3 py-2'>{invoice.invoice_date}</td>
                    <td className='px-3 py-2'>{invoice.transaction_number || '-'}</td>
                    <td className='px-3 py-2'>{invoice.number}</td>
                    <td className='px-3 py-2 text-right'>{formatCurrency(invoice.balance_due)}</td>
                    <td className='px-3 py-2'>{invoice.days_overdue} hari lewat jatuh tempo</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <section className='kontra-print' aria-label='Cetak Kontra Bon'>
        <div className='kontra-form'>
          <div className='kb-grid'>
            <div className='kb-company'>
              <div><b>{dash(company?.legal_name || 'PT. ARUTALA MAHA NUSANTARA')}</b></div>
              <div>PEDAGANG BESAR FARMASI DAN ALAT KESEHATAN</div>
              <div>Jalan {dash(company?.address)} {company?.city ? `, ${company.city}` : ''} {company?.province ? `, ${company.province}` : ''}</div>
              <div className='kb-license'>
                <div className='kb-row'><span>NPWP</span><span>:</span><span>{dash(company?.tax_id)}</span></div>
                <div className='kb-row'><span>No. Sertifikat</span><span>:</span><span>{dash(company?.certificate_number)}</span></div>
              </div>
              <div className='kb-license'>
                <div className='kb-row'><span>No. Izin PBF</span><span>:</span><span>{dash(company?.pbf_license_number)}</span></div>
                <div className='kb-row'><span>CDOB</span><span>:</span><span>{dash(company?.cdob_other_license_number)}</span></div>
              </div>
              <div className='kb-license'>
                <div className='kb-row'><span>No. Izin IDAK</span><span>:</span><span>{dash(company?.idak_license_number)}</span></div>
                <div className='kb-row'><span>CDOB CCP</span><span>:</span><span>{dash(company?.cdob_ccp_license_number)}</span></div>
              </div>
            </div>
            <div className='kb-title-box'>
              <div className='kb-title'>KONTRA BON</div>
              <div className='kb-row'><span>Nomor</span><span>:</span><span>{dash(document?.number)}</span></div>
              <div className='kb-row'><span>Tanggal</span><span>:</span><span>{dash(document?.date)}</span></div>
              <div className='kb-row'><span>Sebanyak</span><span>:</span><span>{document?.invoice_count || invoices.length} Faktur</span></div>
            </div>
          </div>

          <div className='kb-rule' />
          <div className='kb-customer'>
            <div className='kb-customer-left'>
              <div>Kepada Yth.</div>
              <div><b>{dash(customer.customer_name)} {customer.phone ? `(${customer.phone})` : ''}</b></div>
              <div>{dash(customer.address)}</div>
              <div>{addressLine(customer.city, customer.province)}</div>
            </div>
            <div className='kb-customer-right'>
              <div className='kb-row'><span>IDOutlet</span><span>:</span><span>{dash(customer.customer_code)}</span></div>
              <div className='kb-row'><span>IDSales</span><span>:</span><span>{dash(customer.salesman_id)}</span></div>
              <div className='kb-row'><span>Sales</span><span>:</span><span>{dash(salesLabel)}</span></div>
            </div>
          </div>

          <table className='kb-table'>
            <thead>
              <tr><th>No.</th><th>Tanggal</th><th>No. Trx</th><th>No. Faktur</th><th>Jumlah</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
              {invoices.length ? invoices.map((invoice, index) => (
                <tr className='kb-line-row' key={invoice.id}>
                  <td>{index + 1}</td>
                  <td>{invoice.invoice_date}</td>
                  <td>{invoice.transaction_number || '-'}</td>
                  <td>{invoice.number}</td>
                  <td className='kb-num'>{formatMoneyPlain(invoice.balance_due)}</td>
                  <td>{invoice.days_overdue ? `${invoice.days_overdue} hari lewat jatuh tempo` : ''}</td>
                </tr>
              )) : <tr className='kb-empty-row'><td colSpan={6}>Tidak ada invoice jatuh tempo yang belum dibayar pada pilihan ini.</td></tr>}
              <tr className='kb-total-row'>
                <td colSpan={4} className='kb-num'>Total</td>
                <td className='kb-num'>{formatMoneyPlain(totalBalance)}</td>
                <td />
              </tr>
            </tbody>
          </table>

          <div className='kb-double-rule' />

          <div className='kb-bottom'>
            <div className='kb-payment-box'>
              <div className='kb-payment-title'>Pembayaran Melalui Rekening ________________________</div>
              <div className='kb-row'><span>Nama Bank</span><span>:</span><span>{dash(bankName)}</span></div>
              <div className='kb-row'><span>No. Rekening</span><span>:</span><span>{dash(bankNumber)}</span></div>
              <div className='kb-row'><span>Atas Nama</span><span>:</span><span>{dash(bankHolder)}</span></div>
              <div className='kb-row'><span>No. Konfirmasi</span><span>:</span><span>{dash(company?.phone)}</span></div>
              <br />
              <div className='kb-row'><span>Kembali Tgl</span><span>:</span><span>{dash(returnDate)}</span></div>
            </div>
            <div className='kb-sign'>
              <div className='kb-sign-label'>Penerima</div>
              <div className='kb-sign-line'>Terima Tgl: ____________</div>
            </div>
            <div className='kb-sign kb-company-sign'>
              <div>Hormat Kami,</div>
              <div className='kb-sign-line'>{dash(company?.legal_name || 'PT. ARUTALA MAHA NUSANTARA')}</div>
            </div>
          </div>
          <div className='kb-note'>Kontra Bon ini adalah surat pengingat/tagihan untuk collector, bukan invoice/faktur pajak dan tidak membuat pencatatan transaksi.</div>
        </div>
      </section>
    </AppLayout>
  );
}
