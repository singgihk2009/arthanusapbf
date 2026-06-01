import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';

const tabs = ['Sales Flow', 'Overview', 'Profile', 'Documents', 'Sales Orders', 'Fulfillment', 'Invoices', 'Payments', 'Ledger Placeholder'];

const formatCurrency = (value) => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });
const formatNumber = (value) => Number(value || 0).toLocaleString('id-ID');
const statusKey = (status) => String(status || '').toLowerCase();
const upperStatus = (status) => String(status || '').toUpperCase();

const transactionStatusClass = (status) => {
  const normalized = statusKey(status);
  if (['approved', 'posted', 'paid', 'fully_shipped', 'verified'].includes(normalized)) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
  if (['submitted', 'partially_shipped', 'partially_paid', 'overdue', 'pending_review'].includes(normalized)) return 'border-amber-200 bg-amber-50 text-amber-700';
  if (['cancelled', 'rejected', 'void'].includes(normalized)) return 'border-rose-200 bg-rose-50 text-rose-700';
  return 'border-slate-200 bg-slate-50 text-slate-700';
};

const workflowStepClass = (state) => {
  if (state === 'done') return 'border-emerald-300 bg-emerald-50 text-emerald-800';
  if (state === 'active') return 'border-indigo-300 bg-indigo-50 text-indigo-800';
  if (state === 'warning') return 'border-amber-300 bg-amber-50 text-amber-800';
  return 'border-slate-200 bg-white text-slate-500';
};

const FlowMetric = ({ label, value, helper }) => (
  <div className='rounded-xl border border-slate-200 bg-white p-3 shadow-sm'>
    <div className='text-xs font-medium uppercase tracking-wide text-slate-500'>{label}</div>
    <div className='mt-1 text-lg font-semibold text-slate-900'>{value}</div>
    {helper && <div className='mt-1 text-xs text-slate-500'>{helper}</div>}
  </div>
);

const StatusPill = ({ status }) => (
  <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold uppercase ${transactionStatusClass(status)}`}>{status || '-'}</span>
);

export default function Page({ customer, summary, salesOrders = [], dispatches = [], customerInvoices = [], customerPayments = [], documentTypes = [] }) {
  const [activeTab, setActiveTab] = useState('Sales Flow');
  const [flowMode, setFlowMode] = useState('cashier');
  const [selectedFlowSalesOrderId, setSelectedFlowSalesOrderId] = useState(salesOrders?.[0]?.id ?? null);
  const [selectedSalesOrderIds, setSelectedSalesOrderIds] = useState([]);
  const [selectedDispatchIds, setSelectedDispatchIds] = useState([]);
  const [selectedInvoiceIds, setSelectedInvoiceIds] = useState([]);
  const [notice, setNotice] = useState(null);
  const [completion, setCompletion] = useState(null);
  const [customForm, setCustomForm] = useState({ document_type_id: '', document_number: '', issue_date: '', expiry_date: '' });
  const customFileInput = useRef(null);
  const { auth } = usePage().props;

  const statusClassName = customer.status === 'active'
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-gray-100 text-gray-700';

  const stats = useMemo(() => ([
    ['Credit Limit', formatCurrency(customer.credit_limit)],
    ['Payment Term', `${customer.payment_term_days} days`],
    ['Total Sales Orders', summary.total_sales_orders],
    ['Outstanding Balance', formatCurrency(summary.outstanding_balance)],
  ]), [customer.credit_limit, customer.payment_term_days, summary.outstanding_balance, summary.total_sales_orders]);

  const docs = customer?.documents ?? [];
  const flowSalesOrder = useMemo(() => salesOrders.find((order) => order.id === selectedFlowSalesOrderId) ?? salesOrders[0] ?? null, [salesOrders, selectedFlowSalesOrderId]);
  const flowSalesOrderId = flowSalesOrder?.id ?? null;

  const dispatchBelongsToSalesOrder = (entry, salesOrderId) => {
    if (!salesOrderId) return false;
    return Number(entry.sale_id || 0) === Number(salesOrderId)
      || (statusKey(entry.source_type) === 'sales_order' && Number(entry.source_id || 0) === Number(salesOrderId));
  };

  const flowDispatches = useMemo(() => dispatches.filter((entry) => dispatchBelongsToSalesOrder(entry, flowSalesOrderId)), [dispatches, flowSalesOrderId]);
  const flowInvoiceIds = useMemo(() => Array.from(new Set(flowDispatches.map((entry) => entry.invoice_id).filter(Boolean).map(Number))), [flowDispatches]);
  const flowInvoices = useMemo(() => customerInvoices.filter((invoice) => flowInvoiceIds.includes(Number(invoice.id))), [customerInvoices, flowInvoiceIds]);
  const latestInvoice = flowInvoices[0] ?? customerInvoices[0] ?? null;
  const latestPayment = customerPayments[0] ?? null;

  const invoiceableDispatchIds = useMemo(
    () => dispatches
      .filter((entry) => upperStatus(entry.status) === 'POSTED' && !entry.invoice_id)
      .map((entry) => entry.id),
    [dispatches],
  );
  const allInvoiceableDispatchesChecked = invoiceableDispatchIds.length > 0 && invoiceableDispatchIds.every((id) => selectedDispatchIds.includes(id));
  const selectedDispatches = dispatches.filter((entry) => selectedDispatchIds.includes(entry.id));
  const createInvoiceUrl = selectedDispatchIds.length ? `/apps/customer-invoices/create?dispatch_ids=${selectedDispatchIds.join(',')}` : '#';
  const payableInvoiceIds = useMemo(
    () => customerInvoices
      .filter((invoice) => ['posted', 'partially_paid', 'overdue'].includes(statusKey(invoice.status)) && Number(invoice.balance_due || 0) > 0)
      .map((invoice) => invoice.id),
    [customerInvoices],
  );
  const allPayableInvoicesChecked = payableInvoiceIds.length > 0 && payableInvoiceIds.every((id) => selectedInvoiceIds.includes(id));
  const createPaymentUrl = selectedInvoiceIds.length ? `/apps/customer-payments/create?invoice_ids=${selectedInvoiceIds.join(',')}` : '#';

  const approvableSalesOrderIds = useMemo(
    () => salesOrders.filter((salesOrder) => {
      const status = statusKey(salesOrder.status);
      if (status === 'submitted') return true;
      if (status === 'draft') return Number(salesOrder.lines_count || 0) > 0;
      return false;
    }).map((salesOrder) => salesOrder.id),
    [salesOrders],
  );
  const allApprovableChecked = approvableSalesOrderIds.length > 0 && approvableSalesOrderIds.every((id) => selectedSalesOrderIds.includes(id));

  const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

  useEffect(() => {
    let cancelled = false;
    fetch(`/apps/documents/owners/customer/${customer.id}/completion`)
      .then((r) => r.json())
      .then((result) => { if (!cancelled) setCompletion(result); })
      .catch(() => { if (!cancelled) setCompletion(null); });

    return () => { cancelled = true; };
  }, [customer.id]);

  const submitCustomUpload = () => {
    if (!customForm.document_type_id) return setNotice({ type: 'error', text: 'Upload gagal: Document Type wajib dipilih.' });
    if (!customFileInput.current?.files?.[0]) return setNotice({ type: 'error', text: 'Upload gagal: pilih file terlebih dahulu.' });

    setNotice({ type: 'info', text: 'Sedang upload dokumen...' });

    const formData = new FormData();
    formData.append('business_id', String(auth?.user?.business_id ?? auth?.user?.company_id ?? 1));
    formData.append('owner_type', 'customer');
    formData.append('owner_id', String(customer.id));
    formData.append('document_type_id', String(customForm.document_type_id));
    if (customForm.document_number) formData.append('document_number', customForm.document_number);
    if (customForm.issue_date) formData.append('issue_date', customForm.issue_date);
    if (customForm.expiry_date) formData.append('expiry_date', customForm.expiry_date);
    formData.append('file', customFileInput.current.files[0]);

    window.axios.post(route('apps.document-center.documents.store'), formData)
      .then(() => {
        customFileInput.current.value = '';
        setCustomForm({ document_type_id: '', document_number: '', issue_date: '', expiry_date: '' });
        setNotice({ type: 'success', text: 'Dokumen berhasil diupload.' });
        router.reload({ only: ['customer'], preserveScroll: true });
      })
      .catch((error) => {
        const errorsBag = error?.response?.data?.errors ?? null;
        const firstError = Object.values(errorsBag ?? {}).flat().find(Boolean);
        setNotice({ type: 'error', text: `Upload gagal${firstError ? `: ${firstError}` : '.'}` });
      });
  };

  const doVerify = (docId) => {
    if (!confirm('Are you sure you want to verify this document?')) return;
    setNotice({ type: 'info', text: 'Sedang memproses verifikasi dokumen...' });
    window.axios.post(route('apps.document-center.documents.verify', docId))
      .then(() => {
        setNotice({ type: 'success', text: 'Document verified successfully.' });
        router.reload({ only: ['customer'], preserveScroll: true });
      })
      .catch((error) => {
        const errorsBag = error?.response?.data?.errors ?? null;
        const firstError = Object.values(errorsBag ?? {}).flat().find(Boolean) || error?.response?.data?.message;
        setNotice({ type: 'error', text: `Verify gagal${firstError ? `: ${firstError}` : '.'}` });
      });
  };

  const doReject = (docId) => {
    const reason = prompt('Masukkan alasan reject (minimal 5 karakter)');
    const normalizedReason = reason?.trim();
    if (!normalizedReason) return;
    if (normalizedReason.length < 5) return setNotice({ type: 'error', text: 'Alasan reject minimal 5 karakter.' });

    setNotice({ type: 'info', text: 'Sedang memproses reject dokumen...' });
    window.axios.post(route('apps.document-center.documents.reject', docId), { rejected_reason: normalizedReason })
      .then(() => {
        setNotice({ type: 'success', text: 'Document rejected successfully.' });
        router.reload({ only: ['customer'], preserveScroll: true });
      })
      .catch((error) => {
        const errorsBag = error?.response?.data?.errors ?? null;
        const firstError = Object.values(errorsBag ?? {}).flat().find(Boolean) || error?.response?.data?.message;
        setNotice({ type: 'error', text: `Reject gagal${firstError ? `: ${firstError}` : '.'}` });
      });
  };

  const doDelete = (docId) => {
    if (!confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) return;

    setNotice({ type: 'info', text: 'Sedang menghapus dokumen...' });
    window.axios.delete(route('apps.document-center.documents.destroy', docId))
      .then(() => {
        setNotice({ type: 'success', text: 'Dokumen berhasil dihapus.' });
        router.reload({ only: ['customer'], preserveScroll: true });
      })
      .catch((error) => {
        const errorsBag = error?.response?.data?.errors ?? null;
        const firstError = Object.values(errorsBag ?? {}).flat().find(Boolean) || error?.response?.data?.message;
        setNotice({ type: 'error', text: `Hapus gagal${firstError ? `: ${firstError}` : '.'}` });
      });
  };

  const postSalesOrderAction = (salesOrder, action) => {
    const routeName = action === 'submit' ? 'apps.sales-orders.submit' : 'apps.sales-orders.approve';
    setNotice({ type: 'info', text: `${action === 'submit' ? 'Submit' : 'Approve'} ${salesOrder.number || 'SO'} sedang diproses...` });
    window.axios?.post(route(routeName, salesOrder.id))
      .then(() => {
        setNotice({ type: 'success', text: `${action === 'submit' ? 'Submit' : 'Approve'} sales order berhasil.` });
        router.reload({ only: ['salesOrders', 'dispatches', 'customerInvoices', 'customerPayments', 'summary'], preserveScroll: true });
      })
      .catch((error) => setNotice({ type: 'error', text: error?.response?.data?.message || `${action === 'submit' ? 'Submit' : 'Approve'} gagal diproses.` }));
  };

  const bulkApproveSalesOrders = async () => {
    if (!selectedSalesOrderIds.length) return;
    if (!confirm(`Approve ${selectedSalesOrderIds.length} Sales Order terpilih?`)) return;

    const selectedOrders = salesOrders.filter((salesOrder) => selectedSalesOrderIds.includes(salesOrder.id));
    const failedOrders = [];

    for (const salesOrder of selectedOrders) {
      try {
        await window.axios.post(route('apps.sales-orders.approve', salesOrder.id));
      } catch (error) {
        const serverError = error?.response?.data?.errors ?? {};
        const firstError = Object.values(serverError).flat().find(Boolean) || error?.response?.data?.message || 'Unknown error';
        failedOrders.push(`${salesOrder.number || salesOrder.id}: ${firstError}`);
      }
    }

    if (failedOrders.length) {
      setNotice({ type: 'error', text: `Sebagian approval gagal. ${failedOrders.join(' | ')}` });
    } else {
      setNotice({ type: 'success', text: 'Approval sales order berhasil diproses.' });
    }

    setSelectedSalesOrderIds([]);
    router.reload({ only: ['salesOrders', 'summary'], preserveScroll: true });
  };

  const toggleSalesOrderSelection = (id) => {
    setSelectedSalesOrderIds((previous) => previous.includes(id)
      ? previous.filter((item) => item !== id)
      : [...previous, id]);
  };

  const toggleAllApprovableSalesOrders = () => {
    if (!approvableSalesOrderIds.length) return;
    setSelectedSalesOrderIds((previous) => {
      if (allApprovableChecked) return previous.filter((id) => !approvableSalesOrderIds.includes(id));
      return Array.from(new Set([...previous, ...approvableSalesOrderIds]));
    });
  };

  const toggleDispatchSelection = (id) => {
    setSelectedDispatchIds((previous) => previous.includes(id)
      ? previous.filter((item) => item !== id)
      : [...previous, id]);
  };

  const toggleAllInvoiceableDispatches = () => {
    if (!invoiceableDispatchIds.length) return;
    setSelectedDispatchIds((previous) => {
      if (allInvoiceableDispatchesChecked) return previous.filter((id) => !invoiceableDispatchIds.includes(id));
      return Array.from(new Set([...previous, ...invoiceableDispatchIds]));
    });
  };

  const toggleInvoiceSelection = (id) => {
    setSelectedInvoiceIds((previous) => previous.includes(id)
      ? previous.filter((item) => item !== id)
      : [...previous, id]);
  };

  const toggleAllPayableInvoices = () => {
    if (!payableInvoiceIds.length) return;
    setSelectedInvoiceIds((previous) => {
      if (allPayableInvoicesChecked) return previous.filter((id) => !payableInvoiceIds.includes(id));
      return Array.from(new Set([...previous, ...payableInvoiceIds]));
    });
  };

  const statusBadge = (status) => ({ draft: 'bg-gray-100 text-gray-700', pending_review: 'bg-yellow-100 text-yellow-800', verified: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700', expired: 'bg-orange-100 text-orange-700', archived: 'bg-gray-300 text-gray-800' }[status] || 'bg-gray-100 text-gray-600');
  const documentTypeLabel = (doc) => doc?.document_type?.name || doc?.document_type_label || (doc?.document_type_id ? `TYPE #${doc.document_type_id}` : '-');

  const { data, setData, put, processing, errors } = useForm({
    customer_code: customer?.customer_code ?? '',
    customer_name: customer?.customer_name ?? '',
    customer_type: customer?.customer_type ?? '',
    contact_person: customer?.contact_person ?? '',
    phone: customer?.phone ?? '',
    email: customer?.email ?? '',
    address: customer?.address ?? '',
    city: customer?.city ?? '',
    province: customer?.province ?? '',
    postal_code: customer?.postal_code ?? '',
    country: customer?.country ?? 'Indonesia',
    npwp: customer?.npwp ?? '',
    payment_term_days: customer?.payment_term_days ?? 0,
    credit_limit: customer?.credit_limit ?? 0,
    status: customer?.status ?? 'active',
    notes: customer?.notes ?? '',
  });

  const submitProfile = (e) => {
    e.preventDefault();
    put(route('apps.customers.update', customer.id));
  };

  const orderApproved = ['approved', 'partially_shipped', 'fully_shipped'].includes(statusKey(flowSalesOrder?.status));
  const hasPostedDispatch = flowDispatches.some((entry) => upperStatus(entry.status) === 'POSTED');
  const hasInvoice = flowInvoices.length > 0;
  const hasOpenInvoice = customerInvoices.some((invoice) => ['posted', 'partially_paid', 'overdue'].includes(statusKey(invoice.status)) && Number(invoice.balance_due || 0) > 0);
  const canCreateDispatch = flowSalesOrder && ['approved', 'partially_shipped'].includes(statusKey(flowSalesOrder.status));

  const workflowSteps = [
    { title: 'Sales Order', label: flowSalesOrder?.number || 'Belum ada SO', helper: flowSalesOrder?.status || 'Create SO', state: flowSalesOrder ? 'done' : 'active' },
    { title: 'Approval', label: orderApproved ? 'Approved' : flowSalesOrder ? 'Menunggu approval' : 'Belum mulai', helper: flowSalesOrder?.status || '-', state: orderApproved ? 'done' : flowSalesOrder ? 'active' : 'todo' },
    { title: 'Dispatch', label: hasPostedDispatch ? 'Posted' : flowDispatches.length ? 'Draft/Progress' : 'Belum dispatch', helper: `${flowDispatches.length} dispatch`, state: hasPostedDispatch ? 'done' : orderApproved ? 'active' : 'todo' },
    { title: 'Invoice', label: hasInvoice ? latestInvoice?.status : 'Belum invoice', helper: hasInvoice ? latestInvoice?.number : `${invoiceableDispatchIds.length} siap ditagihkan`, state: hasInvoice ? 'done' : hasPostedDispatch ? 'active' : 'todo' },
    { title: 'Collection', label: hasOpenInvoice ? 'Outstanding' : latestPayment ? 'Ada payment' : 'Belum bayar', helper: latestPayment?.number || formatCurrency(summary.outstanding_balance), state: hasOpenInvoice ? 'active' : latestPayment ? 'done' : 'todo' },
  ];

  const renderSalesOrderActions = (salesOrder) => {
    const status = statusKey(salesOrder.status);
    return (
      <div className='flex flex-wrap gap-2 text-xs'>
        <Link href={route('apps.sales-orders.show', salesOrder.id)} className='rounded border border-slate-300 px-2 py-1 text-slate-700 hover:bg-slate-50'>View</Link>
        {status === 'draft' && <Link href={route('apps.sales-orders.edit', salesOrder.id)} className='rounded border border-amber-300 px-2 py-1 text-amber-700 hover:bg-amber-50'>Edit</Link>}
        {status === 'draft' && <button type='button' className='rounded border border-indigo-300 px-2 py-1 text-indigo-700 hover:bg-indigo-50' onClick={() => postSalesOrderAction(salesOrder, 'submit')}>Submit</button>}
        {status === 'submitted' && <button type='button' className='rounded border border-emerald-300 px-2 py-1 text-emerald-700 hover:bg-emerald-50' onClick={() => postSalesOrderAction(salesOrder, 'approve')}>Approve</button>}
        {['approved', 'partially_shipped'].includes(status) && <Link href={route('apps.sales-orders.dispatch.create', salesOrder.id)} className='rounded border border-emerald-300 px-2 py-1 text-emerald-700 hover:bg-emerald-50'>Create Dispatch</Link>}
      </div>
    );
  };

  return (
    <AppLayout>
      <Head title='Customer Detail' />

      <div className='space-y-4 p-6'>
        <div className='sticky top-0 z-10 rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-4'>
            <div>
              <div className='text-xs font-semibold uppercase tracking-wide text-indigo-600'>Customer Sales Workspace</div>
              <h1 className='text-2xl font-bold text-slate-900'>{customer.customer_name}</h1>
              <p className='text-sm text-slate-600'>{customer.customer_code}</p>
            </div>

            <div className='flex flex-wrap items-center gap-2'>
              <Link href={route('apps.customers.sales-orders.create', customer.id)} className='rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700'>Create Sales Order</Link>
              <Link href={route('apps.customers.index')} className='rounded-lg border bg-white px-3 py-2 text-sm'>Back to List</Link>
              <span className={`rounded-full px-2 py-1 text-xs ${statusClassName}`}>{customer.status}</span>
            </div>
          </div>
        </div>

        {notice && <div className={`rounded-lg border p-3 text-sm ${notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}

        <div className='rounded-xl border border-slate-200 bg-white p-3 shadow-sm'>
          <div className='mb-4 flex flex-wrap gap-2 text-sm'>
            {tabs.map((tab) => (
              <button
                key={tab}
                type='button'
                onClick={() => setActiveTab(tab)}
                className={`rounded-lg border px-3 py-2 ${activeTab === tab ? 'border-indigo-600 bg-indigo-600 font-medium text-white' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-white'}`}
              >
                {tab}
              </button>
            ))}
          </div>

          {activeTab === 'Sales Flow' && (
            <div className='space-y-4'>
              <div className='grid gap-3 md:grid-cols-4'>
                <FlowMetric label='SO Aktif' value={salesOrders.length} helper={`${approvableSalesOrderIds.length} perlu submit/approval`} />
                <FlowMetric label='Dispatch Siap Invoice' value={invoiceableDispatchIds.length} helper={`${dispatches.length} total dispatch`} />
                <FlowMetric label='Open Invoice' value={payableInvoiceIds.length} helper={formatCurrency(summary.outstanding_balance)} />
                <FlowMetric label='Collection' value={customerPayments.length} helper={latestPayment ? `Terakhir ${latestPayment.number}` : 'Belum ada payment'} />
              </div>

              <div className='grid gap-4 xl:grid-cols-4'>
                <div className='space-y-4 xl:col-span-3'>
                  <div className='rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-white p-4'>
                    <div className='flex flex-wrap items-start justify-between gap-3'>
                      <div>
                        <h2 className='text-lg font-semibold text-slate-900'>Guided Sales Flow</h2>
                        <p className='mt-1 text-sm text-slate-600'>Satu layar kerja untuk SO → Approval → Dispatch → Invoice → Collection. Dokumen dan posting tetap memakai logic yang sudah ada.</p>
                      </div>
                      <div className='rounded-lg border border-white bg-white p-1 text-xs shadow-sm'>
                        <button type='button' onClick={() => setFlowMode('cashier')} className={`rounded-md px-3 py-2 ${flowMode === 'cashier' ? 'bg-indigo-600 text-white' : 'text-slate-600'}`}>Mode Kasir</button>
                        <button type='button' onClick={() => setFlowMode('b2b')} className={`rounded-md px-3 py-2 ${flowMode === 'b2b' ? 'bg-indigo-600 text-white' : 'text-slate-600'}`}>Mode B2B</button>
                      </div>
                    </div>

                    <div className='mt-4 grid gap-3 md:grid-cols-5'>
                      {workflowSteps.map((step) => (
                        <div key={step.title} className={`rounded-xl border p-3 ${workflowStepClass(step.state)}`}>
                          <div className='text-xs font-semibold uppercase tracking-wide'>{step.title}</div>
                          <div className='mt-2 text-sm font-semibold'>{step.label}</div>
                          <div className='mt-1 text-xs opacity-80'>{step.helper}</div>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className='grid gap-4 lg:grid-cols-2'>
                    <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                      <div className='flex items-center justify-between gap-2'>
                        <div>
                          <h3 className='font-semibold text-slate-900'>1. Pilih / buat Sales Order</h3>
                          <p className='text-xs text-slate-500'>Mode kasir fokus transaksi cepat, mode B2B tetap mendukung submit dan approval.</p>
                        </div>
                        <Link href={route('apps.customers.sales-orders.create', customer.id)} className='rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white'>Create SO</Link>
                      </div>
                      <div className='mt-3 max-h-80 overflow-auto rounded-lg border border-slate-200'>
                        <table className='min-w-full text-sm'>
                          <thead className='bg-slate-50 text-xs uppercase text-slate-500'><tr><th className='px-3 py-2 text-left'>SO</th><th className='px-3 py-2 text-left'>Status</th><th className='px-3 py-2 text-right'>Total</th></tr></thead>
                          <tbody className='divide-y divide-slate-100'>
                            {!salesOrders.length && <tr><td colSpan={3} className='px-3 py-4 text-center text-slate-500'>Belum ada Sales Order.</td></tr>}
                            {salesOrders.map((salesOrder) => (
                              <tr key={salesOrder.id} onClick={() => setSelectedFlowSalesOrderId(salesOrder.id)} className={`cursor-pointer hover:bg-indigo-50 ${flowSalesOrder?.id === salesOrder.id ? 'bg-indigo-50' : ''}`}>
                                <td className='px-3 py-2'><div className='font-medium text-slate-900'>{salesOrder.number}</div><div className='text-xs text-slate-500'>{salesOrder.document_date}</div></td>
                                <td className='px-3 py-2'><StatusPill status={salesOrder.status} /></td>
                                <td className='px-3 py-2 text-right font-medium'>{formatCurrency(salesOrder.grand_total)}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                      <h3 className='font-semibold text-slate-900'>2. Next best action</h3>
                      <p className='mt-1 text-xs text-slate-500'>{flowMode === 'cashier' ? 'Kasir dapat menyelesaikan flow secara berurutan dari panel ini tanpa mencari menu lain.' : 'B2B menjaga kontrol dokumen: submit, approve, fulfillment, invoice, dan collection tetap terpisah namun terlihat dalam satu workspace.'}</p>

                      {flowSalesOrder ? (
                        <div className='mt-4 space-y-3'>
                          <div className='rounded-lg border border-slate-200 bg-slate-50 p-3'>
                            <div className='flex items-start justify-between gap-2'>
                              <div>
                                <div className='font-semibold text-slate-900'>{flowSalesOrder.number}</div>
                                <div className='text-xs text-slate-500'>{flowSalesOrder.warehouse?.name || '-'} · {flowSalesOrder.price_list?.name || '-'}</div>
                              </div>
                              <StatusPill status={flowSalesOrder.status} />
                            </div>
                            <div className='mt-3 flex justify-between text-sm'><span>Grand Total</span><b>{formatCurrency(flowSalesOrder.grand_total)}</b></div>
                          </div>
                          {renderSalesOrderActions(flowSalesOrder)}
                          {canCreateDispatch && <Link href={route('apps.sales-orders.dispatch.create', flowSalesOrder.id)} className='block rounded-lg bg-emerald-600 px-3 py-2 text-center text-sm font-medium text-white'>Continue to Dispatch</Link>}
                          {selectedDispatchIds.length > 0 && <Link href={createInvoiceUrl} className='block rounded-lg bg-indigo-600 px-3 py-2 text-center text-sm font-medium text-white'>Create Invoice from Selected Dispatch ({selectedDispatchIds.length})</Link>}
                          {selectedInvoiceIds.length > 0 && <Link href={createPaymentUrl} className='block rounded-lg bg-slate-900 px-3 py-2 text-center text-sm font-medium text-white'>Create Payment ({selectedInvoiceIds.length})</Link>}
                        </div>
                      ) : (
                        <div className='mt-4 rounded-lg border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500'>Mulai dengan membuat Sales Order baru untuk customer ini.</div>
                      )}
                    </div>
                  </div>

                  <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                    <div className='flex flex-wrap items-center justify-between gap-2'>
                      <div>
                        <h3 className='font-semibold text-slate-900'>3. Dispatch / Fulfillment → Invoice</h3>
                        <p className='text-xs text-slate-500'>Pilih dispatch POSTED yang belum ditagihkan, lalu buat invoice dari sini.</p>
                      </div>
                      <Link href={createInvoiceUrl} className={`rounded-lg px-3 py-2 text-sm font-medium ${selectedDispatchIds.length ? 'bg-indigo-600 text-white' : 'pointer-events-none bg-slate-200 text-slate-500'}`}>Create Invoice ({selectedDispatchIds.length})</Link>
                    </div>
                    <div className='mt-3 overflow-x-auto rounded-lg border border-slate-200'>
                      <table className='min-w-full text-sm'>
                        <thead className='bg-slate-50 text-xs uppercase text-slate-500'><tr><th className='px-3 py-2 text-center'>Pilih</th><th className='px-3 py-2 text-left'>Dispatch</th><th className='px-3 py-2 text-left'>SO Ref</th><th className='px-3 py-2 text-left'>Warehouse</th><th className='px-3 py-2 text-left'>Invoice</th><th className='px-3 py-2 text-left'>Status</th><th className='px-3 py-2 text-center'>Aksi</th></tr></thead>
                        <tbody className='divide-y divide-slate-100'>
                          {!dispatches.length && <tr><td colSpan={7} className='px-3 py-4 text-center text-slate-500'>Belum ada dispatch.</td></tr>}
                          {dispatches.map((entry) => {
                            const posted = upperStatus(entry.status) === 'POSTED';
                            const invoiceable = posted && !entry.invoice_id;
                            const salesOrderId = entry.sale_id ?? (statusKey(entry.source_type) === 'sales_order' ? entry.source_id : null);
                            return (
                              <tr key={entry.id} className={dispatchBelongsToSalesOrder(entry, flowSalesOrderId) ? 'bg-indigo-50/40' : ''}>
                                <td className='px-3 py-2 text-center'>{invoiceable ? <input type='checkbox' checked={selectedDispatchIds.includes(entry.id)} onChange={() => toggleDispatchSelection(entry.id)} /> : '-'}</td>
                                <td className='px-3 py-2'><div className='font-medium'>{entry.number}</div><div className='text-xs text-slate-500'>{entry.document_date}</div></td>
                                <td className='px-3 py-2'>{salesOrderId ? <Link href={route('apps.sales-orders.show', salesOrderId)} className='text-blue-600 hover:underline'>{entry.source_number || '-'}</Link> : '-'}</td>
                                <td className='px-3 py-2'>{entry.warehouse_label}</td>
                                <td className='px-3 py-2'>{entry.invoice_id ? <Link href={route('apps.customer-invoices.show', entry.invoice_id)} className='text-blue-600 hover:underline'>{entry.invoice_number}</Link> : <span className='text-slate-500'>Belum ditagihkan</span>}</td>
                                <td className='px-3 py-2'><StatusPill status={entry.status} /></td>
                                <td className='px-3 py-2 text-center'><Link href={route('apps.outbound.internal-usage.edit', posted ? { internalUsage: entry.id, view: 1 } : entry.id)} className='rounded border border-slate-300 px-2 py-1 text-xs'>{posted ? 'View' : 'Edit'}</Link></td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                    <div className='flex flex-wrap items-center justify-between gap-2'>
                      <div>
                        <h3 className='font-semibold text-slate-900'>4. Invoice → Collection</h3>
                        <p className='text-xs text-slate-500'>Pilih invoice posted/partial/overdue untuk collection payment.</p>
                      </div>
                      <Link href={createPaymentUrl} className={`rounded-lg px-3 py-2 text-sm font-medium ${selectedInvoiceIds.length ? 'bg-slate-900 text-white' : 'pointer-events-none bg-slate-200 text-slate-500'}`}>Create Payment ({selectedInvoiceIds.length})</Link>
                    </div>
                    <div className='mt-3 overflow-x-auto rounded-lg border border-slate-200'>
                      <table className='min-w-full text-sm'>
                        <thead className='bg-slate-50 text-xs uppercase text-slate-500'><tr><th className='px-3 py-2 text-center'>Pilih</th><th className='px-3 py-2 text-left'>Invoice</th><th className='px-3 py-2 text-left'>Due</th><th className='px-3 py-2 text-left'>Status</th><th className='px-3 py-2 text-right'>Grand Total</th><th className='px-3 py-2 text-right'>Balance</th><th className='px-3 py-2 text-center'>Aksi</th></tr></thead>
                        <tbody className='divide-y divide-slate-100'>
                          {!customerInvoices.length && <tr><td colSpan={7} className='px-3 py-4 text-center text-slate-500'>Belum ada invoice.</td></tr>}
                          {customerInvoices.map((invoice) => {
                            const payable = payableInvoiceIds.includes(invoice.id);
                            return (
                              <tr key={invoice.id} className={flowInvoiceIds.includes(Number(invoice.id)) ? 'bg-indigo-50/40' : ''}>
                                <td className='px-3 py-2 text-center'>{payable ? <input type='checkbox' checked={selectedInvoiceIds.includes(invoice.id)} onChange={() => toggleInvoiceSelection(invoice.id)} /> : '-'}</td>
                                <td className='px-3 py-2'><div className='font-medium'>{invoice.number}</div><div className='text-xs text-slate-500'>{invoice.invoice_date}</div></td>
                                <td className='px-3 py-2'>{invoice.due_date || '-'}</td>
                                <td className='px-3 py-2'><StatusPill status={invoice.status} /></td>
                                <td className='px-3 py-2 text-right'>{formatCurrency(invoice.grand_total)}</td>
                                <td className='px-3 py-2 text-right font-semibold'>{formatCurrency(invoice.balance_due)}</td>
                                <td className='px-3 py-2 text-center'><Link href={route('apps.customer-invoices.show', invoice.id)} className='rounded border border-slate-300 px-2 py-1 text-xs'>View</Link></td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div className='space-y-4'>
                  <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                    <h3 className='font-semibold text-slate-900'>Mode Operasional</h3>
                    {flowMode === 'cashier' ? (
                      <ol className='mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600'>
                        <li>Input item di SO seperti kasir.</li>
                        <li>Submit/approve sesuai permission user.</li>
                        <li>Create dispatch dari SO approved.</li>
                        <li>Pilih dispatch POSTED lalu create invoice.</li>
                        <li>Jika dibayar langsung, pilih invoice dan create payment.</li>
                      </ol>
                    ) : (
                      <ol className='mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600'>
                        <li>Sales membuat SO draft dan submit.</li>
                        <li>Supervisor approve.</li>
                        <li>Warehouse create/post dispatch.</li>
                        <li>Finance create/post invoice dari dispatch.</li>
                        <li>Collection mengalokasikan payment ke invoice.</li>
                      </ol>
                    )}
                  </div>

                  <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                    <h3 className='font-semibold text-slate-900'>Ringkasan Customer</h3>
                    <div className='mt-3 space-y-2 text-sm'>
                      <div className='flex justify-between'><span>Credit Limit</span><b>{formatCurrency(customer.credit_limit)}</b></div>
                      <div className='flex justify-between'><span>Payment Term</span><b>{customer.payment_term_days} days</b></div>
                      <div className='flex justify-between'><span>Outstanding</span><b>{formatCurrency(summary.outstanding_balance)}</b></div>
                      <div className='flex justify-between border-t pt-2'><span>Status</span><StatusPill status={customer.status} /></div>
                    </div>
                  </div>

                  <div className='rounded-xl border border-slate-200 bg-white p-4 shadow-sm'>
                    <h3 className='font-semibold text-slate-900'>Audit Trail Ringkas</h3>
                    <div className='mt-3 space-y-3 text-sm'>
                      <div className='rounded-lg bg-slate-50 p-3'><b>SO:</b> {flowSalesOrder?.number || '-'} · {flowSalesOrder?.status || '-'}</div>
                      <div className='rounded-lg bg-slate-50 p-3'><b>Dispatch:</b> {flowDispatches.length} dokumen · {hasPostedDispatch ? 'posted tersedia' : 'belum posted'}</div>
                      <div className='rounded-lg bg-slate-50 p-3'><b>Invoice:</b> {latestInvoice?.number || '-'} · {latestInvoice?.status || '-'}</div>
                      <div className='rounded-lg bg-slate-50 p-3'><b>Payment:</b> {latestPayment?.number || '-'} · {latestPayment?.status || '-'}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'Overview' && (
            <>
              <div className='grid grid-cols-2 gap-3 md:grid-cols-4'>
                {stats.map(([label, value]) => (
                  <div key={label} className='rounded-lg border bg-white p-3 shadow-sm'>
                    <div className='text-xs text-gray-500'>{label}</div>
                    <div className='font-semibold text-gray-900'>{value}</div>
                  </div>
                ))}
              </div>

              <div className='mt-4 space-y-1 text-sm text-gray-700'>
                <div>Contact: {customer.contact_person || '-'}</div>
                <div>Phone: {customer.phone || '-'}</div>
                <div>Email: {customer.email || '-'}</div>
                <div>Address: {[customer.address, customer.city, customer.province, customer.postal_code, customer.country].filter(Boolean).join(', ') || '-'}</div>
                <div>NPWP: {customer.npwp || '-'}</div>
                <div>Notes: {customer.notes || '-'}</div>
                <p className='mt-3 text-gray-600'>Customer Ledger will be available in Phase 2.</p>
              </div>
            </>
          )}

          {activeTab === 'Profile' && (
            <form onSubmit={submitProfile} className='grid gap-4 md:grid-cols-2'>
              <label className='block text-sm'>Customer Code<Input value={data.customer_code} onChange={(e) => setData('customer_code', e.target.value)} className='mt-1 w-full' />{errors.customer_code && <p className='text-xs text-red-600'>{errors.customer_code}</p>}</label>
              <label className='block text-sm'>Customer Name<Input value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} className='mt-1 w-full' />{errors.customer_name && <p className='text-xs text-red-600'>{errors.customer_name}</p>}</label>
              <label className='block text-sm'>Customer Type<Input value={data.customer_type || ''} onChange={(e) => setData('customer_type', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Contact Person<Input value={data.contact_person || ''} onChange={(e) => setData('contact_person', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Phone<Input value={data.phone || ''} onChange={(e) => setData('phone', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Email<Input value={data.email || ''} onChange={(e) => setData('email', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm md:col-span-2'>Address<textarea value={data.address || ''} onChange={(e) => setData('address', e.target.value)} className='mt-1 w-full rounded border-gray-300' rows={3} /></label>
              <label className='block text-sm'>City<Input value={data.city || ''} onChange={(e) => setData('city', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Province<Input value={data.province || ''} onChange={(e) => setData('province', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Postal Code<Input value={data.postal_code || ''} onChange={(e) => setData('postal_code', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Country<Input value={data.country || ''} onChange={(e) => setData('country', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>NPWP<Input value={data.npwp || ''} onChange={(e) => setData('npwp', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Payment Term Days<Input type='number' value={data.payment_term_days} onChange={(e) => setData('payment_term_days', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Credit Limit<Input type='number' value={data.credit_limit} onChange={(e) => setData('credit_limit', e.target.value)} className='mt-1 w-full' /></label>
              <label className='block text-sm'>Status<select value={data.status} onChange={(e) => setData('status', e.target.value)} className='mt-1 w-full rounded border-gray-300'><option value='active'>Active</option><option value='inactive'>Inactive</option></select></label>
              <label className='block text-sm md:col-span-2'>Notes<textarea value={data.notes || ''} onChange={(e) => setData('notes', e.target.value)} className='mt-1 w-full rounded border-gray-300' rows={3} /></label>
              <div className='md:col-span-2'><Button disabled={processing}>Save Profile</Button></div>
            </form>
          )}

          {activeTab === 'Documents' && (
            <div className='space-y-4'>
              <div className='rounded border bg-gray-50 p-3 text-sm'>
                <div className='font-semibold'>Document Completion</div>
                {completion ? <div className='mt-1 text-gray-700'>{completion.completed || 0}/{completion.required || 0} required documents completed ({completion.percentage || 0}%).</div> : <div className='mt-1 text-gray-500'>Loading completion...</div>}
              </div>
              <div className='rounded border p-3'>
                <div className='mb-3 font-semibold'>Upload Custom Document</div>
                <div className='grid gap-2 md:grid-cols-5'>
                  <select value={customForm.document_type_id} onChange={(e) => setCustomForm((p) => ({ ...p, document_type_id: e.target.value }))} className='rounded border-gray-300 text-sm'><option value=''>Document Type</option>{documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</select>
                  <Input placeholder='Document Number' value={customForm.document_number} onChange={(e) => setCustomForm((p) => ({ ...p, document_number: e.target.value }))} />
                  <Input type='date' value={customForm.issue_date} onChange={(e) => setCustomForm((p) => ({ ...p, issue_date: e.target.value }))} />
                  <Input type='date' value={customForm.expiry_date} onChange={(e) => setCustomForm((p) => ({ ...p, expiry_date: e.target.value }))} />
                  <input ref={customFileInput} type='file' className='text-sm' />
                </div>
                <button type='button' onClick={submitCustomUpload} className='mt-3 rounded bg-indigo-600 px-3 py-2 text-sm text-white'>Upload Document</button>
              </div>
              <div className='overflow-x-auto'>
                <table className='min-w-full text-sm border'><thead><tr className='bg-gray-100'><th className='px-3 py-2 border text-left' colSpan={8}>Daftar Dokumen Customer</th></tr><tr className='bg-gray-50'><th className='border px-3 py-2 text-left font-medium'>Document Type</th><th className='border px-3 py-2 text-left font-medium'>Document Number</th><th className='border px-3 py-2 text-left font-medium'>Issue Date</th><th className='border px-3 py-2 text-left font-medium'>Expiry Date</th><th className='border px-3 py-2 text-left font-medium'>Status</th><th className='border px-3 py-2 text-left font-medium'>Reject Reason</th><th className='border px-3 py-2 text-left font-medium'>File</th><th className='border px-3 py-2 text-left font-medium'>Action</th></tr></thead>
                  <tbody>{docs.length ? docs.map((d) => <tr key={d.id}><td className='border px-3 py-2'>{documentTypeLabel(d)}</td><td className='border px-3 py-2'>{d.document_number || '-'}</td><td className='border px-3 py-2'>{formatDate(d.issue_date)}</td><td className='border px-3 py-2'>{formatDate(d.expiry_date)}</td><td className='border px-3 py-2'><span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${statusBadge(d.status)}`}>{d.status || 'draft'}</span></td><td className='border px-3 py-2'>{d.rejected_reason ? <span className='text-xs text-red-700'>{d.rejected_reason}</span> : '-'}</td><td className='border px-3 py-2'><a href={route('apps.document-center.documents.download', d.id)} target='_blank' className='rounded border border-gray-300 px-2 py-1 text-xs'>View</a></td><td className='border px-3 py-2 space-x-2'>{d.status === 'pending_review' && <><button type='button' onClick={() => doVerify(d.id)} className='rounded border border-green-300 px-2 py-1 text-xs text-green-700'>Accept</button><button type='button' onClick={() => doReject(d.id)} className='rounded border border-red-300 px-2 py-1 text-xs text-red-700'>Reject</button></>}<button type='button' onClick={() => doDelete(d.id)} className='rounded border border-gray-300 px-2 py-1 text-xs text-gray-700'>Delete</button></td></tr>) : <tr><td className='border px-2 py-3 text-center text-gray-500' colSpan={8}>Belum ada dokumen tersimpan.</td></tr>}</tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === 'Sales Orders' && (
            <div className='space-y-3'>
              <div className='grid grid-cols-2 gap-2 text-sm md:grid-cols-4'><div className='rounded border p-2'>Total SO<br/><b>{salesOrders.length}</b></div><div className='rounded border p-2'>Draft SO<br/><b>{salesOrders.filter((x) => x.status === 'draft').length}</b></div><div className='rounded border p-2'>Approved SO<br/><b>{salesOrders.filter((x) => x.status === 'approved').length}</b></div><div className='rounded border p-2'>Grand Total SO<br/><b>{formatCurrency(salesOrders.reduce((a, b) => a + Number(b.grand_total || 0), 0))}</b></div></div>
              <div className='flex items-center gap-2'><Link href={route('apps.customers.sales-orders.create', customer.id)} className='inline-block rounded border px-3 py-1 text-sm'>Create Sales Order</Link><button type='button' onClick={bulkApproveSalesOrders} disabled={!selectedSalesOrderIds.length} className='inline-block rounded border border-blue-500 px-3 py-1 text-sm text-blue-600 disabled:cursor-not-allowed disabled:opacity-50'>Approve Selected ({selectedSalesOrderIds.length})</button></div>
              <div className='overflow-x-auto rounded border'><table className='min-w-full text-sm'><thead className='bg-gray-50'><tr><th className='w-10 text-center'><input type='checkbox' checked={allApprovableChecked} onChange={toggleAllApprovableSalesOrders} disabled={!approvableSalesOrderIds.length} /></th><th className='px-3 py-2 text-left'>SO Number</th><th className='px-3 py-2 text-left'>Document Date</th><th className='px-3 py-2 text-left'>Expected Delivery</th><th className='px-3 py-2 text-left'>Price List</th><th className='px-3 py-2 text-left'>Status</th><th className='px-3 py-2 text-right'>Subtotal</th><th className='px-3 py-2 text-right'>Discount</th><th className='px-3 py-2 text-right'>Tax</th><th className='px-3 py-2 text-right'>Grand Total</th><th className='px-3 py-2 text-left'>Actions</th></tr></thead><tbody className='divide-y'>{salesOrders.map((so) => <tr key={so.id}><td className='text-center'>{(statusKey(so.status) === 'submitted' || (statusKey(so.status) === 'draft' && Number(so.lines_count || 0) > 0)) ? <input type='checkbox' checked={selectedSalesOrderIds.includes(so.id)} onChange={() => toggleSalesOrderSelection(so.id)} /> : '-'}</td><td className='px-3 py-2'>{so.number}</td><td className='px-3 py-2'>{so.document_date}</td><td className='px-3 py-2'>{so.expected_delivery_date || '-'}</td><td className='px-3 py-2'>{so.price_list?.name || so.priceList?.name || '-'}</td><td className='px-3 py-2'><StatusPill status={so.status} /></td><td className='px-3 py-2 text-right'>{formatNumber(so.subtotal)}</td><td className='px-3 py-2 text-right'>{formatNumber(so.discount_total)}</td><td className='px-3 py-2 text-right'>{formatNumber(so.tax_total)}</td><td className='px-3 py-2 text-right'>{formatNumber(so.grand_total)}</td><td className='px-3 py-2'>{renderSalesOrderActions(so)}</td></tr>)}</tbody></table></div>
            </div>
          )}

          {activeTab === 'Fulfillment' && (
            <div className='space-y-3'>
              <div className='rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800'>
                <div className='font-semibold'>Rekomendasi flow invoice setelah dispatch POSTED</div>
                <ol className='mt-2 list-decimal space-y-1 pl-5'>
                  <li>Pilih satu atau beberapa dispatch yang statusnya POSTED dan belum masuk invoice.</li>
                  <li>Klik <b>Create Invoice</b> untuk membuat draft tagihan dari dispatch terpilih.</li>
                  <li>Di draft invoice, finance dapat mengatur diskon header, PPN, dan biaya kirim sebelum invoice diposting.</li>
                  <li>Setelah invoice diposting, dispatch terkunci sebagai sudah ditagihkan agar tidak double invoice.</li>
                </ol>
              </div>

              <div className='flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-gray-50 p-3 text-sm'>
                <div>
                  <div className='font-semibold text-gray-800'>Dispatch siap ditagihkan: {invoiceableDispatchIds.length}</div>
                  <div className='text-gray-600'>Terpilih: {selectedDispatchIds.length}{selectedDispatches.length ? ` (${selectedDispatches.map((entry) => entry.number).join(', ')})` : ''}</div>
                </div>
                <Link href={createInvoiceUrl} className={`rounded px-3 py-2 text-sm font-medium ${selectedDispatchIds.length ? 'bg-indigo-600 text-white' : 'pointer-events-none bg-gray-200 text-gray-500'}`}>Create Invoice</Link>
              </div>

              <div className='overflow-x-auto rounded border border-gray-200'>
                <table className='min-w-full divide-y divide-gray-200 text-sm'>
                  <thead className='bg-gray-50'><tr><th className='px-3 py-2 text-center font-semibold text-gray-700'><input type='checkbox' checked={allInvoiceableDispatchesChecked} onChange={toggleAllInvoiceableDispatches} disabled={!invoiceableDispatchIds.length} /></th><th className='px-3 py-2 text-left font-semibold text-gray-700'>No</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Number</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Tanggal</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Warehouse</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Department</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Referensi</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Invoice</th><th className='px-3 py-2 text-left font-semibold text-gray-700'>Status</th><th className='px-3 py-2 text-center font-semibold text-gray-700'>Aksi</th></tr></thead>
                  <tbody className='divide-y divide-gray-100'>
                    {dispatches.length === 0 && <tr><td colSpan={10} className='px-3 py-4 text-center text-gray-500'>Belum ada data dispatch.</td></tr>}
                    {dispatches.map((entry, idx) => {
                      const posted = upperStatus(entry.status) === 'POSTED';
                      const invoiceable = posted && !entry.invoice_id;
                      const salesOrderId = entry.sale_id ?? (statusKey(entry.source_type) === 'sales_order' ? entry.source_id : null);
                      const salesOrderNumber = entry.source_number || '-';
                      return (
                        <tr key={entry.id} className='text-gray-800'>
                          <td className='px-3 py-2 text-center'>{invoiceable ? <input type='checkbox' checked={selectedDispatchIds.includes(entry.id)} onChange={() => toggleDispatchSelection(entry.id)} /> : '-'}</td>
                          <td className='px-3 py-2'>{idx + 1}</td>
                          <td className='px-3 py-2'>{entry.number}</td>
                          <td className='px-3 py-2'>{entry.document_date}</td>
                          <td className='px-3 py-2'>{entry.warehouse_label}</td>
                          <td className='px-3 py-2'>{entry.department || '-'}</td>
                          <td className='px-3 py-2'>{salesOrderId ? <Link href={route('apps.sales-orders.show', salesOrderId)} className='text-blue-600 hover:underline'>{salesOrderNumber}</Link> : '-'}</td>
                          <td className='px-3 py-2'>{entry.invoice_id ? <Link href={route('apps.customer-invoices.show', entry.invoice_id)} className='text-blue-600 hover:underline'>{entry.invoice_number}</Link> : <span className='text-gray-500'>Belum ditagihkan</span>}</td>
                          <td className='px-3 py-2'><StatusPill status={entry.status} /></td>
                          <td className='px-3 py-2 text-center'><Link href={route('apps.outbound.internal-usage.edit', posted ? { internalUsage: entry.id, view: 1 } : entry.id)} className='rounded border border-gray-300 px-2 py-1 text-xs'>{posted ? 'View' : 'Edit'}</Link></td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === 'Invoices' && (
            <div className='space-y-3'>
              <div className='flex flex-wrap items-center justify-between gap-2 rounded border bg-gray-50 p-3 text-sm'><div><b>Invoice terbuka: {payableInvoiceIds.length}</b><div className='text-gray-600'>Terpilih untuk collection: {selectedInvoiceIds.length}</div></div><Link href={createPaymentUrl} className={`rounded px-3 py-2 font-medium ${selectedInvoiceIds.length ? 'bg-indigo-600 text-white' : 'pointer-events-none bg-gray-200 text-gray-500'}`}>Create Payment</Link></div>
              <div className='overflow-x-auto rounded border'><table className='min-w-full text-sm'><thead className='bg-gray-50'><tr><th className='px-3 py-2 text-center'><input type='checkbox' checked={allPayableInvoicesChecked} onChange={toggleAllPayableInvoices} disabled={!payableInvoiceIds.length} /></th><th className='px-3 py-2 text-left'>Number</th><th className='px-3 py-2 text-left'>Tanggal</th><th className='px-3 py-2 text-left'>Due Date</th><th className='px-3 py-2 text-left'>Status</th><th className='px-3 py-2 text-right'>Grand Total</th><th className='px-3 py-2 text-right'>Balance Due</th><th className='px-3 py-2 text-center'>Aksi</th></tr></thead><tbody className='divide-y'>{!customerInvoices.length && <tr><td colSpan={8} className='px-3 py-4 text-center text-gray-500'>Belum ada invoice.</td></tr>}{customerInvoices.map((invoice) => { const payable = payableInvoiceIds.includes(invoice.id); return <tr key={invoice.id}><td className='px-3 py-2 text-center'>{payable ? <input type='checkbox' checked={selectedInvoiceIds.includes(invoice.id)} onChange={() => toggleInvoiceSelection(invoice.id)} /> : '-'}</td><td className='px-3 py-2'>{invoice.number}</td><td className='px-3 py-2'>{invoice.invoice_date}</td><td className='px-3 py-2'>{invoice.due_date || '-'}</td><td className='px-3 py-2'><StatusPill status={invoice.status} /></td><td className='px-3 py-2 text-right'>{formatCurrency(invoice.grand_total)}</td><td className='px-3 py-2 text-right'>{formatCurrency(invoice.balance_due)}</td><td className='px-3 py-2 text-center'><Link href={route('apps.customer-invoices.show', invoice.id)} className='rounded border px-2 py-1 text-xs'>View</Link></td></tr>; })}</tbody></table></div>
            </div>
          )}

          {activeTab === 'Payments' && (
            <div className='overflow-x-auto rounded border'><table className='min-w-full text-sm'><thead className='bg-gray-50'><tr><th className='px-3 py-2 text-left'>Number</th><th className='px-3 py-2 text-left'>Tanggal</th><th className='px-3 py-2 text-left'>Metode</th><th className='px-3 py-2 text-left'>Status</th><th className='px-3 py-2 text-right'>Kas</th><th className='px-3 py-2 text-right'>WHT</th><th className='px-3 py-2 text-right'>Potongan Lain</th><th className='px-3 py-2 text-right'>Settlement</th><th className='px-3 py-2 text-center'>Aksi</th></tr></thead><tbody className='divide-y'>{!customerPayments.length && <tr><td colSpan={9} className='px-3 py-4 text-center text-gray-500'>Belum ada payment.</td></tr>}{customerPayments.map((payment) => <tr key={payment.id}><td className='px-3 py-2'>{payment.number}</td><td className='px-3 py-2'>{payment.payment_date}</td><td className='px-3 py-2'>{payment.payment_method || '-'}</td><td className='px-3 py-2'><StatusPill status={payment.status} /></td><td className='px-3 py-2 text-right'>{formatCurrency(payment.amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(payment.wht_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(payment.other_deduction_amount)}</td><td className='px-3 py-2 text-right'>{formatCurrency(payment.gross_settlement_amount)}</td><td className='px-3 py-2 text-center'><Link href={route('apps.customer-payments.show', payment.id)} className='rounded border px-2 py-1 text-xs'>View</Link></td></tr>)}</tbody></table></div>
          )}

          {activeTab === 'Ledger Placeholder' && <p className='text-sm text-gray-600'>Customer ledger akan tersedia pada fase berikutnya.</p>}
        </div>
      </div>
    </AppLayout>
  );
}
