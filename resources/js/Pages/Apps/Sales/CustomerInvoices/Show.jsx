import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });

export default function Page({ invoice, lines = [], dispatches = [] }) {
  const postInvoice = () => {
    if (!confirm('Post invoice ini? Setelah diposting, qty invoiced Sales Order akan diperbarui.')) return;
    router.post(route('apps.customer-invoices.post', invoice.id));
  };

  return (
    <AppLayout>
      <Head title={`Invoice ${invoice.number}`} />
      <div className='space-y-4 p-6'>
        <div className='rounded border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-3'>
            <div>
              <h1 className='text-xl font-semibold'>{invoice.number}</h1>
              <p className='text-sm text-gray-600'>{invoice.customer_name} ({invoice.customer_code})</p>
              <p className='text-sm text-gray-600'>Status: <span className='font-semibold uppercase'>{invoice.status}</span></p>
            </div>
            <div className='flex gap-2'>
              {invoice.status === 'draft' && <button type='button' onClick={postInvoice} className='rounded bg-indigo-600 px-3 py-2 text-sm text-white'>Post Invoice</button>}
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
                    <tr><th className='px-3 py-2 text-left'>Dispatch</th><th className='px-3 py-2 text-left'>Item</th><th className='px-3 py-2 text-right'>Qty</th><th className='px-3 py-2 text-left'>UOM</th><th className='px-3 py-2 text-right'>Harga</th><th className='px-3 py-2 text-right'>Total</th></tr>
                  </thead>
                  <tbody className='divide-y'>
                    {lines.map((line) => (
                      <tr key={line.id}>
                        <td className='px-3 py-2'>{line.dispatch_number}</td>
                        <td className='px-3 py-2'>{line.item_sku} - {line.item_name}</td>
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
    </AppLayout>
  );
}
