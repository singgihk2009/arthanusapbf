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

export default function Show({ invoice, uploadedDocuments = [] }) {
  const lines = invoice.lines || [];

  const summaryRows = [
    ['Subtotal', money(invoice.subtotal)],
    ['Discount', money(invoice.discount_amount)],
    [`PPN (${Number(invoice.tax_rate ?? 0)}%)`, money(invoice.tax_amount)],
    ['WHT', money(invoice.wht_tax_amount)],
    ['Freight', money(invoice.freight_amount)],
    ['Grand Total', money(invoice.grand_total)],
    ['Net Payable', money(invoice.net_payable_amount)],
    ['Paid', money(invoice.paid_amount)],
    ['Outstanding', money(invoice.outstanding_amount)],
  ];

  const handlePrintInvoice = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const linesHtml = lines.length
      ? lines.map((line, index) => `
          <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(line.item?.name || line.description || '-')}</td>
            <td class="right">${escapeHtml(line.item?.sku || '-')}</td>
            <td class="right">${money(line.qty_invoiced)}</td>
            <td class="right">${money(line.unit_price)}</td>
            <td class="right">${money(line.line_total)}</td>
          </tr>
      `).join('')
      : '<tr><td colspan="6" class="center muted">Tidak ada item invoice.</td></tr>';

    const summaryHtml = summaryRows.map(([label, value]) => `
      <tr><td>${escapeHtml(label)}</td><td class="right">${escapeHtml(value)}</td></tr>
    `).join('');

    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8" />
        <title>Invoice ${escapeHtml(invoice.invoice_no_internal)}</title>
        <style>
          @page { size: A4 portrait; margin: 35mm 12mm 14mm 12mm; }
          * { box-sizing: border-box; }
          body { font-family: Arial, sans-serif; color: #111827; font-size: 12px; }
          .header-space { height: 18mm; }
          .title { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
          h1 { margin: 0; font-size: 24px; letter-spacing: 1px; }
          .badge { border: 1px solid #d1d5db; border-radius: 999px; padding: 4px 10px; font-size: 11px; }
          .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 28px; margin-bottom: 12px; }
          .meta-row .label { color: #4b5563; width: 130px; display: inline-block; }
          table { width: 100%; border-collapse: collapse; }
          th, td { border: 1px solid #d1d5db; padding: 6px; }
          th { background: #f3f4f6; text-align: left; }
          .right { text-align: right; }
          .center { text-align: center; }
          .muted { color: #6b7280; }
          .summary { margin-top: 10px; width: 45%; margin-left: auto; }
          .summary tr:last-child td { font-weight: 700; }
          .notes { margin-top: 14px; }
        </style>
      </head>
      <body>
        <div class="header-space"></div>
        <div class="title">
          <h1>INVOICE</h1>
          <div class="badge">Status: ${escapeHtml(String(invoice.status || '-').toUpperCase())}</div>
        </div>

        <div class="meta">
          <div class="meta-row"><span class="label">Internal Invoice No</span>: ${escapeHtml(invoice.invoice_no_internal)}</div>
          <div class="meta-row"><span class="label">Vendor Invoice No</span>: ${escapeHtml(invoice.vendor_invoice_no)}</div>
          <div class="meta-row"><span class="label">Vendor</span>: ${escapeHtml(invoice.vendor?.name || '-')}</div>
          <div class="meta-row"><span class="label">Invoice Date</span>: ${escapeHtml(formatDate(invoice.invoice_date))}</div>
          <div class="meta-row"><span class="label">Currency</span>: ${escapeHtml(invoice.currency_code || 'IDR')}</div>
          <div class="meta-row"><span class="label">Due Date</span>: ${escapeHtml(formatDate(invoice.due_date))}</div>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width:5%">No</th>
              <th style="width:35%">Item</th>
              <th style="width:14%">SKU</th>
              <th style="width:12%" class="right">Qty</th>
              <th style="width:17%" class="right">Unit Price</th>
              <th style="width:17%" class="right">Line Total</th>
            </tr>
          </thead>
          <tbody>${linesHtml}</tbody>
        </table>

        <table class="summary"><tbody>${summaryHtml}</tbody></table>

        <div class="notes">
          <strong>Notes:</strong><br />
          ${escapeHtml(invoice.notes || 'Terima kasih atas kerja samanya.')}
        </div>
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
      <Head title='Vendor Invoice Detail' />
      <div className='p-6 space-y-4'>
        <div className='flex flex-wrap items-center justify-between gap-2'>
          <h1 className='text-xl font-semibold'>Vendor Invoice Detail</h1>
          <div className='flex items-center gap-2'>
            <button type='button' onClick={handlePrintInvoice} className='rounded-lg border border-indigo-500 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50'>Print Invoice (PDF A4)</button>
            <Link href={`/apps/procurement/vendors/${invoice.vendor_id}?tab=invoices`} className='rounded-lg bg-gray-100 px-3 py-2 text-sm'>Back</Link>
          </div>
        </div>

        <div className='rounded-xl border bg-white p-5 shadow-sm'>
          <div className='mb-5 flex items-start justify-between border-b pb-4'>
            <div>
              <p className='text-xs tracking-[0.2em] text-gray-500'>PROFESSIONAL INVOICE</p>
              <h2 className='text-2xl font-bold text-gray-800'>{invoice.invoice_no_internal}</h2>
            </div>
            <span className='rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700'>{invoice.status || '-'}</span>
          </div>

          <div className='mb-5 grid grid-cols-1 gap-4 text-sm md:grid-cols-2'>
            <div><span className='font-semibold text-gray-600'>Vendor:</span> {invoice.vendor?.name || '-'}</div>
            <div><span className='font-semibold text-gray-600'>Vendor Invoice No:</span> {invoice.vendor_invoice_no || '-'}</div>
            <div><span className='font-semibold text-gray-600'>Invoice Date:</span> {formatDate(invoice.invoice_date)}</div>
            <div><span className='font-semibold text-gray-600'>Due Date:</span> {formatDate(invoice.due_date)}</div>
            <div><span className='font-semibold text-gray-600'>Currency:</span> {invoice.currency_code || 'IDR'}</div>
            <div><span className='font-semibold text-gray-600'>Exchange Rate:</span> {money(invoice.exchange_rate ?? 1)}</div>
          </div>

          <div className='overflow-x-auto'>
            <table className='min-w-full text-sm'>
              <thead className='bg-gray-50'>
                <tr>
                  <th className='px-3 py-2 text-left'>No</th><th className='px-3 py-2 text-left'>Item</th><th className='px-3 py-2 text-left'>SKU</th><th className='px-3 py-2 text-right'>Qty</th><th className='px-3 py-2 text-right'>Unit Price</th><th className='px-3 py-2 text-right'>Line Total</th>
                </tr>
              </thead>
              <tbody>
                {lines.length ? lines.map((line, index) => (
                  <tr key={line.id || `${line.item_id}-${index}`} className='border-t'>
                    <td className='px-3 py-2'>{index + 1}</td>
                    <td className='px-3 py-2'>{line.item?.name || line.description || '-'}</td>
                    <td className='px-3 py-2'>{line.item?.sku || '-'}</td>
                    <td className='px-3 py-2 text-right'>{money(line.qty_invoiced)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.unit_price)}</td>
                    <td className='px-3 py-2 text-right'>{money(line.line_total)}</td>
                  </tr>
                )) : <tr><td colSpan={6} className='px-3 py-4 text-center text-gray-500'>Tidak ada item invoice.</td></tr>}
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
            <span className='font-semibold'>Notes:</span> {invoice.notes || '-'}
          </div>


          <div className='mt-4 rounded border'>
            <div className='border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700'>
              Daftar Supporting Dokumen Terupload ({uploadedDocuments.length})
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
                    <td className='border px-2 py-2'>{doc.document_type?.name || '-'}</td>
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
                  </tr>) : <tr><td colSpan={6} className='border px-2 py-4 text-center text-gray-500'>Belum ada dokumen yang terupload untuk invoice ini.</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
