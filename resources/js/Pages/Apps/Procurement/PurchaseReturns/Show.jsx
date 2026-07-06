import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

const money = (value) => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Show({ purchaseReturn }) {
  const action = (name) => router.post(route(`apps.procurement.purchase-returns.${name}`, purchaseReturn.id));
  return <>
    <Head title={purchaseReturn.return_no} />
    <div className="space-y-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">{purchaseReturn.return_no}</h2>
          <p className="text-sm text-gray-600">{purchaseReturn.return_date} | {purchaseReturn.vendor?.vendor_name || purchaseReturn.vendor?.name} | {purchaseReturn.reason_category}</p>
          <p className="text-sm text-gray-600">GR: {purchaseReturn.goods_receipt?.gr_number || purchaseReturn.goods_receipt?.number} | Warehouse: {purchaseReturn.warehouse?.code || purchaseReturn.warehouse?.name}</p>
        </div>
        <span className="rounded border px-3 py-1 text-sm font-semibold">{purchaseReturn.status}</span>
      </div>

      <div className="flex flex-wrap gap-2">
        {purchaseReturn.status === 'DRAFT' && <button onClick={() => action('submit')} className="rounded border border-indigo-500 px-3 py-2 text-sm text-indigo-600">Submit</button>}
        {['DRAFT','SUBMITTED'].includes(purchaseReturn.status) && <button onClick={() => action('approve')} className="rounded border border-emerald-500 px-3 py-2 text-sm text-emerald-600">Approve</button>}
        {purchaseReturn.status === 'APPROVED' && <button onClick={() => action('post')} className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Post Return</button>}
      </div>

      {purchaseReturn.deduction && <div className="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
        Deduction: <b>{purchaseReturn.deduction.deduction_no}</b> | Amount {money(purchaseReturn.deduction.amount)} | Applied {money(purchaseReturn.deduction.applied_amount)} | Remaining Credit {money(purchaseReturn.deduction.remaining_amount)} | Invoice {purchaseReturn.deduction.vendor_invoice?.invoice_no_internal || '-'}
      </div>}

      <div className="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
        <table className="min-w-full text-sm"><thead className="bg-gray-50"><tr>{['Item','Batch','Expired','Qty','Unit Cost','Amount','Reason','Notes'].map((h) => <th key={h} className="px-3 py-2 text-left">{h}</th>)}</tr></thead><tbody>
          {purchaseReturn.lines.map((line) => <tr key={line.id} className="border-t"><td className="px-3 py-2">{line.item?.name}</td><td className="px-3 py-2">{line.batch_number || '-'}</td><td className="px-3 py-2">{line.expired_date || '-'}</td><td className="px-3 py-2 text-right">{money(line.qty_returned)}</td><td className="px-3 py-2 text-right">{money(line.unit_cost)}</td><td className="px-3 py-2 text-right">{money(line.line_amount)}</td><td className="px-3 py-2">{line.reason}</td><td className="px-3 py-2">{line.condition_notes || '-'}</td></tr>)}
        </tbody><tfoot><tr className="border-t font-semibold"><td className="px-3 py-2" colSpan={3}>Total</td><td className="px-3 py-2 text-right">{money(purchaseReturn.total_qty)}</td><td></td><td className="px-3 py-2 text-right">{money(purchaseReturn.total_amount)}</td><td colSpan={2}></td></tr></tfoot></table>
      </div>
      {purchaseReturn.notes && <div className="rounded bg-gray-50 p-3 text-sm">{purchaseReturn.notes}</div>}
    </div>
  </>;
}

Show.layout = (page) => <AppLayout children={page} />;
