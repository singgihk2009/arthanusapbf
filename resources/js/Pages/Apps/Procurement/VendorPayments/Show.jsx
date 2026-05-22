import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const money = (value) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));

const formatDate = (value) => {
  if (!value) return '-';
  const parsedDate = new Date(value);
  if (Number.isNaN(parsedDate.getTime())) return value;

  return parsedDate.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const escapeHtml = (text) => String(text ?? '-')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#039;');

export default function Show({ vendor, payment, uploadedDocuments = [] }) {
  const lines = payment?.lines || [];
  const bankAccountLabel = payment.bank_account?.label || [payment.bank_account?.bank_name, payment.bank_account?.account_number, payment.bank_account?.account_name ? `a/n ${payment.bank_account.account_name}` : null].filter(Boolean).join(' - ') || '-';

  const summaryRows = [
    ['Total Invoice Payment', money(payment.total_invoice_amount)],
    ['Total WHT', money(payment.total_wht_amount)],
    ['Stamp Duty', money(payment.stamp_duty_amount)],
    ['Freight', money(payment.freight_amount)],
    ['Bank Charge', money(payment.bank_charge_amount)],
    ['Net Payment to Vendor', money(payment.net_vendor_payment_amount)],
    ['Total Cash / Bank Out', money(payment.total_cash_out_amount)],
  ];

  const handlePrintVoucher = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const linesHtml = lines.length
      ? lines.map((line, index) => `
          <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(line.invoice_number || '-')}</td>
            <td>${escapeHtml(formatDate(line.invoice_date))}</td>
            <td class="right">${money(line.invoice_total_amount)}</td>
            <td class="right">${money(line.invoice_outstanding_amount)}</td>
            <td class="right">${money(line.payment_amount)}</td>
            <td class="right">${money(line.wht_amount)}</td>
            <td class="right">${money(line.net_payment_amount)}</td>
          </tr>
      `).join('')
      : '<tr><td colspan="8" class="center muted">Tidak ada alokasi invoice pada payment ini.</td></tr>';

    const summaryHtml = summaryRows.map(([label, value]) => `
      <tr><td>${escapeHtml(label)}</td><td class="right">${escapeHtml(value)}</td></tr>
    `).join('');

    const documentRowsHtml = uploadedDocuments.length
      ? uploadedDocuments.map((doc) => `
          <tr>
            <td>${escapeHtml(doc.document_type?.name || doc.document_type?.code || '-')}</td>
            <td>${escapeHtml(doc.title || '-')}</td>
            <td>${escapeHtml(doc.document_number || '-')}</td>
            <td>${escapeHtml(doc.original_file_name || '-')}</td>
            <td>${escapeHtml(doc.status || 'uploaded')}</td>
          </tr>
      `).join('')
      : '<tr><td colspan="5" class="center muted">Belum ada dokumen yang terupload untuk payment ini.</td></tr>';

    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8" />
        <title>Payment Voucher ${escapeHtml(payment.payment_no)}</title>
        <style>
          @page { size: A4 portrait; margin: 18mm 12mm 14mm 12mm; }
          * { box-sizing: border-box; }
          body { font-family: Arial, sans-serif; color: #111827; font-size: 11px; }
          h1 { margin: 0; font-size: 22px; letter-spacing: 1px; }
          .title { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
          .badge { border: 1px solid #d1d5db; border-radius: 999px; padding: 4px 10px; font-size: 10px; text-transform: uppercase; }
          .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 20px; margin-bottom: 10px; }
          .meta-row .label { color: #4b5563; width: 130px; display: inline-block; }
          table { width: 100%; border-collapse: collapse; }
          th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: top; }
          th { background: #f3f4f6; text-align: left; }
          .right { text-align: right; }
          .center { text-align: center; }
          .muted { color: #6b7280; }
          .summary { margin-top: 10px; width: 48%; margin-left: auto; }
          .summary tr:last-child td { font-weight: 700; }
          .section-title { font-size: 12px; font-weight: 700; margin: 16px 0 6px; }
          .notes { margin-top: 12px; }
        </style>
      </head>
      <body>
        <div class="title">
          <h1>PAYMENT VOUCHER</h1>
          <div class="badge">Status: ${escapeHtml(payment.status || '-')}</div>
        </div>

        <div class="meta">
          <div class="meta-row"><span class="label">Payment No</span>: ${escapeHtml(payment.payment_no || '-')}</div>
          <div class="meta-row"><span class="label">Payment Date</span>: ${escapeHtml(formatDate(payment.payment_date))}</div>
          <div class="meta-row"><span class="label">Vendor</span>: ${escapeHtml(vendor.vendor_name || vendor.name || '-')}</div>
          <div class="meta-row"><span class="label">Payment Method</span>: ${escapeHtml(payment.payment_method || '-')}</div>
          <div class="meta-row"><span class="label">Currency</span>: ${escapeHtml(payment.currency || 'IDR')}</div>
          <div class="meta-row"><span class="label">Bank Account</span>: ${escapeHtml(bankAccountLabel)}</div>
        </div>

        <div class="section-title">Invoice Allocation</div>
        <table>
          <thead>
            <tr>
              <th style="width:4%">No</th>
              <th style="width:16%">Invoice No</th>
              <th style="width:10%">Invoice Date</th>
              <th style="width:14%" class="right">Invoice Total</th>
              <th style="width:14%" class="right">Outstanding</th>
              <th style="width:14%" class="right">Payment</th>
              <th style="width:12%" class="right">WHT</th>
              <th style="width:16%" class="right">Net Payment</th>
            </tr>
          </thead>
          <tbody>${linesHtml}</tbody>
        </table>

        <table class="summary"><tbody>${summaryHtml}</tbody></table>

        <div class="notes"><strong>Notes:</strong> ${escapeHtml(payment.notes || '-')}</div>

        <div class="section-title">Daftar Dokumen Terupload (${uploadedDocuments.length})</div>
        <table>
          <thead>
            <tr>
              <th>Document Type</th><th>Judul</th><th>No Dokumen</th><th>Nama File</th><th>Status Upload</th>
            </tr>
          </thead>
          <tbody>${documentRowsHtml}</tbody>
        </table>
      </body>
      </html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();

    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  };

  return (
    <AppLayout>
      <Head title='Payment Voucher Detail' />
      <div className='p-6 space-y-4'>
        <div className='flex flex-wrap items-center justify-between gap-2'>
          <h1 className='text-xl font-semibold'>Payment Voucher Detail</h1>
          <div className='flex items-center gap-2'>
            <button type='button' onClick={handlePrintVoucher} className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50'>Print Voucher (PDF A4)</button>
            <Link href={`/apps/procurement/vendors/${vendor.id}?tab=payments`} className='rounded-lg bg-gray-100 px-3 py-2 text-sm'>Back</Link>
          </div>
        </div>

        <div className='rounded-xl border bg-white p-5 shadow-sm'>
          <div className='mb-5 flex items-start justify-between border-b pb-4'>
            <div>
              <p className='text-xs tracking-[0.2em] text-gray-500'>BUSINESS PAYMENT VOUCHER</p>
              <h2 className='text-2xl font-bold text-gray-800'>{payment.payment_no || '-'}</h2>
            </div>
            <span className='rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700'>{payment.status || '-'}</span>
          </div>

          <div className='mb-5 grid grid-cols-1 gap-4 text-sm md:grid-cols-2'>
            <div><span className='font-semibold text-gray-600'>Vendor:</span> {vendor.vendor_name || vendor.name || '-'}</div>
            <div><span className='font-semibold text-gray-600'>Payment Date:</span> {formatDate(payment.payment_date)}</div>
            <div><span className='font-semibold text-gray-600'>Payment Method:</span> {payment.payment_method || '-'}</div>
            <div><span className='font-semibold text-gray-600'>Currency:</span> {payment.currency || 'IDR'}</div>
            <div className='md:col-span-2'><span className='font-semibold text-gray-600'>Bank Account:</span> {bankAccountLabel}</div>
          </div>

          <div className='overflow-x-auto'>
            <table className='min-w-full text-sm'>
              <thead className='bg-gray-50'>
                <tr>
                  <th className='px-3 py-2 text-left'>No</th><th className='px-3 py-2 text-left'>Invoice No</th><th className='px-3 py-2 text-left'>Invoice Date</th><th className='px-3 py-2 text-right'>Invoice Total</th><th className='px-3 py-2 text-right'>Outstanding</th><th className='px-3 py-2 text-right'>Payment</th><th className='px-3 py-2 text-right'>WHT</th><th className='px-3 py-2 text-right'>Net Payment</th>
                </tr>
              </thead>
              <tbody>
                {lines.length ? lines.map((line, index) => (
                  <tr key={line.id || `${line.vendor_invoice_id}-${index}`} className='border-t'>
                    <td className='px-3 py-2'>{index + 1}</td>
                    <td className='px-3 py-2'>{line.invoice_number || '-'}</td>
                    <td className='px-3 py-2'>{formatDate(line.invoice_date)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.invoice_total_amount)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.invoice_outstanding_amount)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.payment_amount)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.wht_amount)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.net_payment_amount)}</td>
                  </tr>
                )) : <tr><td colSpan={8} className='px-3 py-4 text-center text-gray-500'>Tidak ada alokasi invoice pada payment ini.</td></tr>}
              </tbody>
            </table>
          </div>

          <div className='mt-4 ml-auto w-full max-w-md rounded-lg border'>
            {summaryRows.map(([label, value]) => (
              <div key={label} className='grid grid-cols-2 border-b px-3 py-2 text-sm last:border-b-0'>
                <span className='text-gray-600'>{label}</span>
                <span className='text-right font-medium'>{value}</span>
              </div>
            ))}
          </div>

          <div className='mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700'>
            <span className='font-semibold'>Notes:</span> {payment.notes || '-'}
          </div>

          <div className='mt-4 rounded border'>
            <div className='border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700'>
              Daftar Dokumen Terupload ({uploadedDocuments.length})
            </div>
            <div className='overflow-auto'>
              <table className='min-w-full text-sm'>
                <thead>
                  <tr className='bg-white'>
                    <th className='border px-2 py-2 text-left'>Document Type</th>
                    <th className='border px-2 py-2 text-left'>Judul</th>
                    <th className='border px-2 py-2 text-left'>No Dokumen</th>
                    <th className='border px-2 py-2 text-left'>Nama File</th>
                    <th className='border px-2 py-2 text-left'>Status Upload</th>
                    <th className='border px-2 py-2 text-left'>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {uploadedDocuments.length ? uploadedDocuments.map((doc) => <tr key={doc.id}>
                    <td className='border px-2 py-2'>{doc.document_type?.name || doc.document_type?.code || '-'}</td>
                    <td className='border px-2 py-2'>{doc.title || '-'}</td>
                    <td className='border px-2 py-2'>{doc.document_number || '-'}</td>
                    <td className='border px-2 py-2'>{doc.original_file_name || '-'}</td>
                    <td className='border px-2 py-2'>
                      <span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${doc.status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                        {doc.status || 'uploaded'}
                      </span>
                    </td>
                    <td className='border px-2 py-2'>
                      <a href={route('apps.document-center.documents.download', doc.id)} target='_blank' className='text-blue-600 underline' rel='noreferrer'>View</a>
                    </td>
                  </tr>) : <tr><td colSpan={6} className='border px-2 py-4 text-center text-gray-500'>Belum ada dokumen yang terupload untuk payment ini.</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
