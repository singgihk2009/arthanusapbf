import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const emptyItem = (defaultFacilitySchemeId = '') => ({ product_id: '', product_name: '', uom_id: '', facility_scheme_id: defaultFacilitySchemeId, facility_reference_no: '', facility_reference_date: '', facility_reference_note: '', qty_ordered: 1, unit_price: 0, discount_amount: 0, tax_amount: 0, line_total: 0, notes: '' });
const normalizeFacilityId = (value, fallback = '') => {
    const resolved = value ?? fallback;
    return resolved === null || resolved === undefined || resolved === '' ? '' : String(resolved);
};
const normalizeItem = (item = {}, fallbackFacilitySchemeId = '') => {
    const qty = item.qty_ordered ?? 1;
    const price = item.unit_price ?? 0;
    const discount = item.discount_amount ?? 0;
    const tax = item.tax_amount ?? 0;

    return {
        ...emptyItem(fallbackFacilitySchemeId),
        ...item,
        product_id: item.product_id ? String(item.product_id) : '',
        uom_id: item.uom_id ? String(item.uom_id) : '',
        facility_scheme_id: normalizeFacilityId(item.facility_scheme_id, fallbackFacilitySchemeId),
        qty_ordered: qty,
        unit_price: price,
        discount_amount: discount,
        tax_amount: tax,
        line_total: (Number(qty) || 0) * (Number(price) || 0) - (Number(discount) || 0) + (Number(tax) || 0),
    };
};
const toDateInputValue = (value) => {
    if (!value) return '';
    const str = String(value);
    return str.length >= 10 ? str.slice(0, 10) : str;
};

const emptyDocument = () => ({ document_type_id: '', title: '', document_number: '', file: null });

export default function Form({ purchaseOrder = null, vendors = [], products = [], uoms = [], facilitySchemes = [], documentTypes = [], uploadedDocuments = [], defaultFacilitySchemeId = '', defaultVendorId = null, returnTo = '' }) {
    const [notice, setNotice] = useState(null);
    const isEdit = !!purchaseOrder;
    const initialHeaderFacilitySchemeId = normalizeFacilityId(
        purchaseOrder?.facility_scheme_id,
        purchaseOrder?.items?.find((item) => item?.facility_scheme_id)?.facility_scheme_id ?? ''
    );
    const initialItems = purchaseOrder?.items?.length
        ? purchaseOrder.items.map((item) => normalizeItem(item, initialHeaderFacilitySchemeId))
        : [emptyItem(String(initialHeaderFacilitySchemeId || defaultFacilitySchemeId || ''))];
    const { data, setData, post, transform, processing, errors, isDirty } = useForm({
        vendor_id: purchaseOrder?.vendor_id || defaultVendorId || '',
        po_date: toDateInputValue(purchaseOrder?.po_date),
        expected_delivery_date: toDateInputValue(purchaseOrder?.expected_delivery_date),
        notes: purchaseOrder?.notes || '',
        facility_scheme_id: initialHeaderFacilitySchemeId || (isEdit ? '' : String(defaultFacilitySchemeId || '')),
        facility_reference_no: purchaseOrder?.facility_reference_no || purchaseOrder?.items?.[0]?.facility_reference_no || '',
        facility_reference_date: toDateInputValue(purchaseOrder?.facility_reference_date || purchaseOrder?.items?.[0]?.facility_reference_date),
        return_to: returnTo || '',
        items: initialItems,
        documents: [emptyDocument()],
    });

    const productUomMap = useMemo(() => Object.fromEntries(products.map((p) => [String(p.id), p.base_uom_id ? String(p.base_uom_id) : ''])), [products]);
    const facilityMap = useMemo(() => Object.fromEntries(facilitySchemes.map((f) => [String(f.id), f])), [facilitySchemes]);

    const setItem = (index, key, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [key]: value };

        if (key === 'product_id') {
            const defaultUom = productUomMap[String(value)] || '';
            if (defaultUom) items[index].uom_id = defaultUom;
        }
        const base = (+items[index].qty_ordered || 0) * (+items[index].unit_price || 0);
        items[index].line_total = base - (+items[index].discount_amount || 0) + (+items[index].tax_amount || 0);
        setData('items', items);
    };

    const totals = data.items.reduce((a, i) => ({ subtotal: a.subtotal + ((+i.qty_ordered || 0) * (+i.unit_price || 0)), discount: a.discount + (+i.discount_amount || 0), tax: a.tax + (+i.tax_amount || 0), grand: a.grand + (+i.line_total || 0) }), { subtotal: 0, discount: 0, tax: 0, grand: 0 });
    const submit = (e) => {
        e.preventDefault();
        setNotice(null);
        const filteredDocuments = (data.documents || []).filter((doc) => doc?.document_type_id && doc?.file);
        const submitPayload = {
            ...data,
            documents: filteredDocuments,
        };
        const fallbackReturnTo = isEdit && data.vendor_id ? `/apps/procurement/vendors/${data.vendor_id}?tab=purchase-orders` : route('apps.procurement.purchase-orders.index');
        const redirectTarget = returnTo || data.return_to || fallbackReturnTo;
        const options = {
            forceFormData: true,
            onSuccess: () => {
                setNotice({ type: 'success', text: 'PO dan dokumen berhasil disimpan.' });
                if (isEdit) router.get(redirectTarget);
            },
            onError: () => setNotice({ type: 'error', text: 'Gagal menyimpan PO/dokumen. Cek field yang wajib diisi.' }),
        };
        if (isEdit) {
            transform(() => ({ ...submitPayload, _method: 'put' }));
            post(route('apps.procurement.purchase-orders.update', purchaseOrder.id), options);
            return;
        }

        transform(() => submitPayload);
        post(route('apps.procurement.purchase-orders.store'), options);
    };
    const handleBack = () => {
        const confirmLeave = window.confirm('Pastikan data sudah disimpan. Kembali sekarang dapat menyebabkan data yang belum disimpan hilang.');
        if (!confirmLeave) return;

        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        if (returnTo) {
            router.get(returnTo);
            return;
        }

        router.get(route('apps.procurement.purchase-orders.index'));
    };

    return <>
        <Head title={isEdit ? 'Edit Purchase Order' : 'Create Purchase Order'} />
        <Card title={isEdit ? 'Edit Purchase Order' : 'Create Purchase Order'} form={submit} footer={<div className='flex items-center gap-2'><Button type='submit' label='Save Draft' disabled={processing} variant='gray' /><button type='button' onClick={handleBack} className='rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50'>Back</button>{isDirty && <span className='text-xs text-amber-600'>Data belum disimpan.</span>}</div>}>
            {notice && <div className={`mb-3 rounded border px-3 py-2 text-sm ${notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{notice.text}</div>}
            <div className='grid grid-cols-1 gap-4 md:grid-cols-3'>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Vendor</label><select value={data.vendor_id} onChange={(e) => setData('vendor_id', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'><option value=''>-</option>{vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}</select>{errors.vendor_id && <small className='text-xs text-red-500'>{errors.vendor_id}</small>}</div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>PO Date</label><input type='date' value={data.po_date} onChange={(e) => setData('po_date', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' />{errors.po_date && <small className='text-xs text-red-500'>{errors.po_date}</small>}</div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Expected Date</label><input type='date' value={data.expected_delivery_date} onChange={(e) => setData('expected_delivery_date', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' /></div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Facility</label><select value={data.facility_scheme_id} onChange={(e) => setData('facility_scheme_id', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700'><option value=''>-</option>{facilitySchemes.map((f)=><option key={f.id} value={f.id}>{f.code} - {f.name}</option>)}</select></div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>No Referensi Facility</label><input value={data.facility_reference_no} onChange={(e) => setData('facility_reference_no', e.target.value)} placeholder={(facilityMap[String(data.facility_scheme_id)]?.code === 'KEK_VAT_EXEMPT') ? 'No Referensi PPKEK' : 'No Referensi'} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' /></div>
                <div className='flex flex-col gap-2'><label className='text-sm text-gray-600'>Tanggal Facility</label><input type='date' value={data.facility_reference_date} onChange={(e) => setData('facility_reference_date', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' /></div>
                <div className='flex flex-col gap-2 md:col-span-3'><label className='text-sm text-gray-600'>Notes</label><textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className='w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' /></div>
            </div>
            <div className='mt-4 space-y-2'>
                <div className='hidden md:grid md:grid-cols-8 md:gap-2 px-2 text-xs font-semibold text-gray-600'>
                    <div className='px-1'>Produk Master</div>
                    <div className='px-1'>Nama Produk</div>
                    <div className='px-1'>UoM</div>
                    <div className='px-1'>Qty</div>
                    <div className='px-1'>Harga</div>
                    <div className='px-1'>Pajak</div>
                    <div className='px-1'>Total</div>
                    <div className='px-1'>Aksi</div>
                </div>
                {data.items.map((it, i) => <div key={i} className='grid grid-cols-1 gap-2 rounded border border-gray-200 p-2 md:grid-cols-8 dark:border-gray-800'>
                    <select value={it.product_id || ''} onChange={(e) => setItem(i, 'product_id', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900'><option value=''>Product</option>{products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>
                    <input value={it.product_name || ''} onChange={(e) => setItem(i, 'product_name', e.target.value)} placeholder='Nama produk' className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <select value={it.uom_id || ''} onChange={(e) => setItem(i, 'uom_id', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900'><option value=''>UOM</option>{uoms.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select>
                    <input type='number' value={it.qty_ordered} onChange={(e) => setItem(i, 'qty_ordered', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <input type='number' value={it.unit_price} onChange={(e) => setItem(i, 'unit_price', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <input type='number' value={it.tax_amount} onChange={(e) => setItem(i, 'tax_amount', e.target.value)} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                    <div className='flex items-center text-sm'>{Number(it.line_total || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <button type='button' onClick={() => setData('items', data.items.filter((_, idx) => idx !== i))} className='rounded border border-rose-400 px-2 py-1 text-xs text-rose-600'>Remove</button>
                </div>)}
                <button type='button' onClick={() => setData('items', [...data.items, emptyItem(String(data.facility_scheme_id || defaultFacilitySchemeId || ''))])} className='rounded border border-gray-300 px-3 py-1 text-sm'>+ Add Item</button>
            </div>
            <div className='mt-3 text-right text-sm font-medium'>Subtotal: {totals.subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | Tax: {totals.tax.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | Grand Total: {totals.grand.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
            <div className='mt-6 rounded border border-gray-200 p-3 dark:border-gray-800'>
                <h3 className='text-sm font-semibold text-gray-700 dark:text-gray-200'>Upload Dokumen (Document Center)</h3>
                <p className='mb-3 mt-1 text-xs text-gray-500'>Bisa upload multi dokumen: pilih tipe dokumen, isi judul, isi no dokumen, lalu upload file (PDF/JPG/PNG).</p>
                <div className='space-y-3'>
                    {data.documents.map((doc, idx) => (
                        <div key={idx} className='grid grid-cols-1 gap-2 rounded border border-gray-200 p-2 md:grid-cols-5 dark:border-gray-800'>
                            <select value={doc.document_type_id} onChange={(e) => {
                                const docs = [...data.documents];
                                docs[idx] = { ...docs[idx], document_type_id: e.target.value };
                                setData('documents', docs);
                            }} className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900'>
                                <option value=''>Pilih Tipe Dokumen</option>
                                {documentTypes.map((type) => <option key={type.id} value={type.id}>{type.name || type.code}</option>)}
                            </select>
                            <input value={doc.title || ''} onChange={(e) => {
                                const docs = [...data.documents];
                                docs[idx] = { ...docs[idx], title: e.target.value };
                                setData('documents', docs);
                            }} placeholder='Judul dokumen' className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                            <input value={doc.document_number || ''} onChange={(e) => {
                                const docs = [...data.documents];
                                docs[idx] = { ...docs[idx], document_number: e.target.value };
                                setData('documents', docs);
                            }} placeholder='No dokumen' className='rounded border border-gray-200 px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900' />
                            <input type='file' accept='.pdf,.jpg,.jpeg,.png' onChange={(e) => {
                                const docs = [...data.documents];
                                docs[idx] = { ...docs[idx], file: e.target.files?.[0] || null };
                                setData('documents', docs);
                            }} className='rounded border border-gray-200 px-2 py-1 text-sm file:mr-2 file:rounded file:border-0 file:bg-gray-100 file:px-2 file:py-1 dark:border-gray-800 dark:bg-gray-900' />
                            <div className='flex items-center gap-2'>
                                <button type='button' onClick={() => setData('documents', data.documents.filter((_, rowIndex) => rowIndex !== idx))} className='rounded border border-rose-400 px-2 py-1 text-xs text-rose-600'>Remove</button>
                                {doc.file && <button type='button' onClick={() => window.open(URL.createObjectURL(doc.file), '_blank', 'noopener,noreferrer')} className='rounded border border-blue-300 px-2 py-1 text-xs text-blue-600'>View file terpilih</button>}
                            </div>
                        </div>
                    ))}
                </div>
                <button type='button' onClick={() => setData('documents', [...data.documents, emptyDocument()])} className='mt-3 rounded border border-gray-300 px-3 py-1 text-sm'>+ Add Dokumen</button>
                <div className='mt-2 text-xs text-gray-500'>Dokumen akan di-upload saat klik tombol <span className='font-semibold'>Save Draft</span>.</div>
                {errors.documents && <small className='mt-2 block text-xs text-red-500'>{errors.documents}</small>}
                <div className='mt-5 rounded border border-gray-200 p-3 dark:border-gray-800'>
                    <h4 className='mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200'>Daftar Dokumen Terupload ({uploadedDocuments.length})</h4>
                    <div className='overflow-x-auto'>
                        <table className='min-w-full border border-gray-200 text-sm dark:border-gray-800'>
                            <thead><tr className='bg-gray-50 dark:bg-gray-900'><th className='border px-3 py-2 text-left font-semibold'>Document Type</th><th className='border px-3 py-2 text-left font-semibold'>Judul</th><th className='border px-3 py-2 text-left font-semibold'>No Dokumen</th><th className='border px-3 py-2 text-left font-semibold'>Nama File</th><th className='border px-3 py-2 text-left font-semibold'>Status Upload</th><th className='border px-3 py-2 text-left font-semibold'>Aksi</th></tr></thead>
                            <tbody>
                                {uploadedDocuments.length > 0 ? uploadedDocuments.map((doc) => <tr key={doc.id}>
                                    <td className='border px-3 py-2'>{doc.document_type?.name || doc.document_type?.code || '-'}</td>
                                    <td className='border px-3 py-2'>{doc.title || '-'}</td>
                                    <td className='border px-3 py-2'>{doc.document_number || '-'}</td>
                                    <td className='border px-3 py-2'>{doc.original_file_name || '-'}</td>
                                    <td className='border px-3 py-2'>
                                        <span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${doc.status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                                            {doc.status || 'uploaded'}
                                        </span>
                                    </td>
                                    <td className='border px-3 py-2'>
                                        <div className='flex items-center gap-2'>
                                            <button type='button' onClick={() => router.delete(route('apps.procurement.purchase-orders.documents.delete', [purchaseOrder.id, doc.id]))} className='rounded border border-rose-400 px-2 py-1 text-xs text-rose-600'>Remove</button>
                                            <button type='button' onClick={() => window.open(route('apps.document-center.documents.download', doc.id), '_blank', 'noopener,noreferrer')} className='rounded border border-blue-300 px-2 py-1 text-xs text-blue-600'>View</button>
                                        </div>
                                    </td>
                                </tr>) : <tr><td colSpan={6} className='border px-3 py-4 text-center text-gray-500'>Belum ada dokumen yang terupload untuk PO ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Card>
    </>;
}

Form.layout = (page) => <AppLayout children={page} />;
