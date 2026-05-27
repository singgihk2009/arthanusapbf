import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';

const tabs = ['Overview', 'Profile', 'Documents', 'Sales Orders', 'Shipments', 'Invoices', 'Payments', 'Ledger Placeholder'];

export default function Page({ customer, summary, salesOrders = [], documentTypes = [] }) {
  const [activeTab, setActiveTab] = useState('Overview');
  const { auth } = usePage().props;

  const statusClassName = customer.status === 'active'
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-gray-100 text-gray-700';

  const stats = useMemo(() => ([
    ['Credit Limit', Number(customer.credit_limit || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })],
    ['Payment Term', `${customer.payment_term_days} days`],
    ['Total Sales Orders', summary.total_sales_orders],
    ['Outstanding Balance', Number(summary.outstanding_balance || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' })],
  ]), [customer.credit_limit, customer.payment_term_days, summary.outstanding_balance, summary.total_sales_orders]);


  const docs = customer?.documents ?? [];
  const [selectedSalesOrderIds, setSelectedSalesOrderIds] = useState([]);
  const [notice, setNotice] = useState(null);
  const [completion, setCompletion] = useState(null);
  const [customForm, setCustomForm] = useState({ document_type_id: '', document_number: '', issue_date: '', expiry_date: '' });
  const customFileInput = useRef(null);

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

  const approvableSalesOrderIds = useMemo(
    () => salesOrders.filter((salesOrder) => ['draft', 'submitted'].includes(String(salesOrder.status || '').toLowerCase())).map((salesOrder) => salesOrder.id),
    [salesOrders],
  );
  const allApprovableChecked = approvableSalesOrderIds.length > 0 && approvableSalesOrderIds.every((id) => selectedSalesOrderIds.includes(id));

  const toggleSalesOrderSelection = (id) => {
    setSelectedSalesOrderIds((previous) => previous.includes(id)
      ? previous.filter((item) => item !== id)
      : [...previous, id]);
  };

  const toggleAllApprovableSalesOrders = () => {
    if (!approvableSalesOrderIds.length) return;
    setSelectedSalesOrderIds((previous) => {
      if (allApprovableChecked) {
        return previous.filter((id) => !approvableSalesOrderIds.includes(id));
      }

      return Array.from(new Set([...previous, ...approvableSalesOrderIds]));
    });
  };

  const bulkApproveSalesOrders = async () => {
    if (!selectedSalesOrderIds.length) return;
    if (!confirm(`Approve ${selectedSalesOrderIds.length} Sales Order terpilih?`)) return;

    const selectedOrders = salesOrders.filter((salesOrder) => selectedSalesOrderIds.includes(salesOrder.id));
    for (const salesOrder of selectedOrders) {
      const status = String(salesOrder.status || '').toLowerCase();
      if (status === 'draft') {
        await window.axios.post(route('apps.sales-orders.submit', salesOrder.id));
      }
      await window.axios.post(route('apps.sales-orders.approve', salesOrder.id));
    }
    setSelectedSalesOrderIds([]);
    router.reload({ only: ['salesOrders'], preserveScroll: true });
  };

  const statusBadge = (status) => ({ draft: 'bg-gray-100 text-gray-700', pending_review: 'bg-yellow-100 text-yellow-800', verified: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700', expired: 'bg-orange-100 text-orange-700', archived: 'bg-gray-300 text-gray-800' }[status] || 'bg-gray-100 text-gray-600');
  const documentTypeLabel = (doc) => doc?.document_type?.name || doc?.document_type_label || (doc?.document_type_id ? `TYPE #${doc.document_type_id}` : '-');

  const { data, setData, put, processing, errors, reset } = useForm({
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

  return (
    <AppLayout>
      <Head title='Customer Detail' />

      <div className='p-6 space-y-4'>
        <div className='sticky top-0 z-10 rounded-lg border bg-white p-4 shadow-sm'>
          <div className='flex flex-wrap items-start justify-between gap-4'>
            <div>
              <h1 className='text-2xl font-bold'>{customer.customer_name}</h1>
              <p className='text-sm text-gray-600'>{customer.customer_code}</p>
            </div>

            <div className='flex flex-col items-end gap-2'>
              <Link href={route('apps.customers.index')} className='rounded border bg-white px-3 py-2 text-sm'>Back to List</Link>
              <span className={`rounded-full px-2 py-1 text-xs ${statusClassName}`}>{customer.status}</span>
            </div>
          </div>
        </div>

        <div className='rounded-lg border bg-white p-3 shadow-sm'>
          <div className='mb-3 flex flex-wrap gap-3 text-sm'>
            {tabs.map((tab) => (
              <button
                key={tab}
                type='button'
                onClick={() => setActiveTab(tab)}
                className={`rounded-md border px-2 py-1 ${activeTab === tab ? 'border-indigo-600 bg-indigo-600 font-medium text-white' : 'border-gray-200 bg-gray-50 text-gray-700'}`}
              >
                {tab}
              </button>
            ))}
          </div>

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
                <p className='text-gray-600'>No data available yet.</p>
              </div>
            </>
          )}

          {activeTab === 'Profile' && (
            <form onSubmit={submitProfile} className='space-y-3'>
              <div className='grid grid-cols-1 gap-3 md:grid-cols-2'>
                <Input label='Customer Code' value={data.customer_code} onChange={(e) => setData('customer_code', e.target.value)} errors={errors.customer_code} />
                <Input label='Customer Name' value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} errors={errors.customer_name} />
                <Input label='Customer Type' value={data.customer_type} onChange={(e) => setData('customer_type', e.target.value)} errors={errors.customer_type} />
                <Input label='Contact Person' value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} errors={errors.contact_person} />
                <Input label='Phone' value={data.phone} onChange={(e) => setData('phone', e.target.value)} errors={errors.phone} />
                <Input label='Email' type='email' value={data.email} onChange={(e) => setData('email', e.target.value)} errors={errors.email} />
                <Input label='Address' value={data.address} onChange={(e) => setData('address', e.target.value)} errors={errors.address} />
                <Input label='City' value={data.city} onChange={(e) => setData('city', e.target.value)} errors={errors.city} />
                <Input label='Province' value={data.province} onChange={(e) => setData('province', e.target.value)} errors={errors.province} />
                <Input label='Postal Code' value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} errors={errors.postal_code} />
                <Input label='Country' value={data.country} onChange={(e) => setData('country', e.target.value)} errors={errors.country} />
                <Input label='NPWP' value={data.npwp} onChange={(e) => setData('npwp', e.target.value)} errors={errors.npwp} />
                <Input label='Payment Term (Days)' type='number' value={data.payment_term_days} onChange={(e) => setData('payment_term_days', e.target.value)} errors={errors.payment_term_days} />
                <Input label='Credit Limit' type='number' value={data.credit_limit} onChange={(e) => setData('credit_limit', e.target.value)} errors={errors.credit_limit} />

                <div className='flex flex-col gap-2'>
                  <label className='text-gray-600 text-sm'>Status</label>
                  <select
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                    className='w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none focus:ring-0 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800'
                  >
                    <option value='active'>Active</option>
                    <option value='inactive'>Inactive</option>
                  </select>
                  {errors.status && <small className='text-xs text-red-500'>{errors.status}</small>}
                </div>

                <Input label='Notes' value={data.notes} onChange={(e) => setData('notes', e.target.value)} errors={errors.notes} className='md:col-span-2' />
              </div>

              <div className='flex flex-wrap items-center gap-2'>
                <Button type='submit' label='Simpan' variant='gray' disabled={processing} />
                <Button type='button' label='Reset' variant='orange' onClick={() => reset()} disabled={processing} />
              </div>
            </form>
          )}

          {activeTab === 'Documents' && (
            <div className='space-y-5'>
              {completion && <div className='rounded border bg-blue-50 p-3 text-sm'><div className='font-semibold'>Completion: {completion.completion_percentage ?? 0}%</div></div>}
              {notice && <div className={`rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>{notice.text}</div>}

              <div className='rounded border p-4'>
                <div className='mb-3 text-sm font-semibold'>Tambah Dokumen Baru</div>
                <div className='grid gap-3 md:grid-cols-5'>
                  <select value={customForm.document_type_id} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_type_id: e.target.value }))} className='rounded border px-2 py-2'>
                    <option value=''>Pilih Document Type</option>
                    {documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name} ({type.code})</option>)}
                  </select>
                  <input value={customForm.document_number} onChange={(e) => setCustomForm((prev) => ({ ...prev, document_number: e.target.value }))} placeholder='Document Number' className='rounded border px-2 py-2' />
                  <input type='date' value={customForm.issue_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, issue_date: e.target.value }))} className='rounded border px-2 py-2' />
                  <input type='date' value={customForm.expiry_date} onChange={(e) => setCustomForm((prev) => ({ ...prev, expiry_date: e.target.value }))} className='rounded border px-2 py-2' />
                  <div className='flex items-center gap-2'><input ref={customFileInput} type='file' accept='.pdf,.jpg,.jpeg,.png' className='w-full rounded border px-2 py-2' /><button type='button' onClick={submitCustomUpload} className='shrink-0 rounded border border-blue-300 px-3 py-2 text-xs text-blue-700'>Upload</button></div>
                </div>
              </div>

              <div className='overflow-auto rounded border p-3'>
                <table className='min-w-full text-sm border'><thead><tr className='bg-gray-100'><th className='px-3 py-2 border text-left' colSpan={8}>Daftar Dokumen Customer</th></tr><tr className='bg-gray-50'><th className='border px-3 py-2 text-left font-medium'>Document Type</th><th className='border px-3 py-2 text-left font-medium'>Document Number</th><th className='border px-3 py-2 text-left font-medium'>Issue Date</th><th className='border px-3 py-2 text-left font-medium'>Expiry Date</th><th className='border px-3 py-2 text-left font-medium'>Status</th><th className='border px-3 py-2 text-left font-medium'>Reject Reason</th><th className='border px-3 py-2 text-left font-medium'>File</th><th className='border px-3 py-2 text-left font-medium'>Action</th></tr></thead>
                  <tbody>{docs.length ? docs.map((d) => <tr key={d.id}><td className='border px-3 py-2'>{documentTypeLabel(d)}</td><td className='border px-3 py-2'>{d.document_number || '-'}</td><td className='border px-3 py-2'>{formatDate(d.issue_date)}</td><td className='border px-3 py-2'>{formatDate(d.expiry_date)}</td><td className='border px-3 py-2'><span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${statusBadge(d.status)}`}>{d.status || 'draft'}</span></td><td className='border px-3 py-2'>{d.rejected_reason ? <span className='text-xs text-red-700'>{d.rejected_reason}</span> : '-'}</td><td className='border px-3 py-2'><a href={route('apps.document-center.documents.download', d.id)} target='_blank' className='rounded border border-gray-300 px-2 py-1 text-xs'>View</a></td><td className='border px-3 py-2 space-x-2'>{d.status === 'pending_review' && <><button type='button' onClick={() => doVerify(d.id)} className='rounded border border-green-300 px-2 py-1 text-xs text-green-700'>Accept</button><button type='button' onClick={() => doReject(d.id)} className='rounded border border-red-300 px-2 py-1 text-xs text-red-700'>Reject</button></>}<button type='button' onClick={() => doDelete(d.id)} className='rounded border border-gray-300 px-2 py-1 text-xs text-gray-700'>Delete</button></td></tr>) : <tr><td className='border px-2 py-3 text-center text-gray-500' colSpan={8}>Belum ada dokumen tersimpan.</td></tr>}</tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === 'Sales Orders' && (<div className='space-y-3'><div className='grid grid-cols-2 md:grid-cols-4 gap-2 text-sm'><div className='border rounded p-2'>Total SO<br/><b>{salesOrders.length}</b></div><div className='border rounded p-2'>Draft SO<br/><b>{salesOrders.filter(x=>x.status==='draft').length}</b></div><div className='border rounded p-2'>Approved SO<br/><b>{salesOrders.filter(x=>x.status==='approved').length}</b></div><div className='border rounded p-2'>Grand Total SO<br/><b>{Number(salesOrders.reduce((a,b)=>a+Number(b.grand_total||0),0)).toLocaleString('id-ID')}</b></div></div><div className='flex items-center gap-2'><Link href={route('apps.customers.sales-orders.create', customer.id)} className='inline-block rounded border px-3 py-1 text-sm'>Create Sales Order</Link><button type='button' onClick={bulkApproveSalesOrders} disabled={!selectedSalesOrderIds.length} className='inline-block rounded border border-blue-500 px-3 py-1 text-sm text-blue-600 disabled:cursor-not-allowed disabled:opacity-50'>Approve Selected ({selectedSalesOrderIds.length})</button></div><table className='w-full text-sm border'><thead><tr><th className='w-10 text-center'><input type='checkbox' checked={allApprovableChecked} onChange={toggleAllApprovableSalesOrders} disabled={!approvableSalesOrderIds.length} /></th><th>SO Number</th><th>Document Date</th><th>Expected Delivery</th><th>Price List</th><th>Status</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Grand Total</th><th>Actions</th></tr></thead><tbody>{salesOrders.map((so)=><tr key={so.id}><td className='text-center'>{['draft','submitted'].includes(String(so.status || '').toLowerCase()) ? <input type='checkbox' checked={selectedSalesOrderIds.includes(so.id)} onChange={() => toggleSalesOrderSelection(so.id)} /> : '-'}</td><td>{so.number}</td><td>{so.document_date}</td><td>{so.expected_delivery_date||'-'}</td><td>{so.price_list?.name||'-'}</td><td>{so.status}</td><td>{Number(so.subtotal||0).toLocaleString('id-ID')}</td><td>{Number(so.discount_total||0).toLocaleString('id-ID')}</td><td>{Number(so.tax_total||0).toLocaleString('id-ID')}</td><td>{Number(so.grand_total||0).toLocaleString('id-ID')}</td><td className='space-x-2'><Link href={route('apps.sales-orders.show', so.id)} className='text-blue-600'>View</Link>{so.status==='draft' && <><Link href={route('apps.sales-orders.edit', so.id)} className='text-amber-600'>Edit</Link><button className='text-indigo-600' onClick={()=>window.axios?.post(route('apps.sales-orders.submit',so.id)).then(()=>window.location.reload())}>Submit</button></>}{so.status==='submitted' && <button className='text-emerald-600' onClick={()=>window.axios?.post(route('apps.sales-orders.approve',so.id)).then(()=>window.location.reload())}>Approve</button>}</td></tr>)}</tbody></table></div>)}

          {activeTab !== 'Overview' && activeTab !== 'Profile' && activeTab !== 'Documents' && activeTab !== 'Sales Orders' && (
            <p className='text-gray-600 text-sm'>No data available yet.</p>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
