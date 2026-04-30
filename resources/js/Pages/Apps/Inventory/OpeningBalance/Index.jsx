import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import React, { useState } from 'react';

export default function Index() {
    const { warehouses, items, uoms } = usePage().props;
    const [manualForm, setManualForm] = useState({
        warehouse_id: '',
        item_id: '',
        qty: '',
        uom_id: '',
        unit_cost: '',
        batch_id: '',
        trx_datetime: '',
    });
    const [importFile, setImportFile] = useState(null);
    const [errors, setErrors] = useState({});
    const [result, setResult] = useState(null);
    const [loadingManual, setLoadingManual] = useState(false);
    const [loadingImport, setLoadingImport] = useState(false);

    const updateManual = (key, value) => {
        setManualForm((prev) => ({ ...prev, [key]: value }));
    };

    const submitManual = async (event) => {
        event.preventDefault();
        setErrors({});
        setResult(null);
        setLoadingManual(true);

        try {
            const response = await window.axios.post(route('apps.inventory.posting.opening-balance'), {
                ...manualForm,
                batch_id: manualForm.batch_id || null,
                trx_datetime: manualForm.trx_datetime || null,
            });

            setResult({ type: 'success', message: response.data?.message ?? 'Saldo awal berhasil diposting.' });
            setManualForm({
                warehouse_id: '',
                item_id: '',
                qty: '',
                uom_id: '',
                unit_cost: '',
                batch_id: '',
                trx_datetime: '',
            });
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
                setResult({ type: 'error', message: 'Validasi gagal. Cek input form.' });
            } else {
                setResult({ type: 'error', message: error.response?.data?.message ?? 'Terjadi kesalahan saat menyimpan saldo awal.' });
            }
        } finally {
            setLoadingManual(false);
        }
    };

    const submitImport = async (event) => {
        event.preventDefault();
        setResult(null);

        if (!importFile) {
            setResult({ type: 'error', message: 'Pilih file CSV/XLSX terlebih dahulu.' });
            return;
        }

        setLoadingImport(true);

        const formData = new FormData();
        formData.append('file', importFile);

        try {
            const response = await window.axios.post(route('apps.inventory.opening-balance.import'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const data = response.data ?? {};
            const errorCount = Array.isArray(data.errors) ? data.errors.length : 0;
            setResult({
                type: errorCount > 0 ? 'error' : 'success',
                message: `Import selesai. Berhasil: ${data.created ?? 0}, Error: ${errorCount}`,
                details: data.errors ?? [],
            });
        } catch (error) {
            if (error.response?.status === 422) {
                setResult({ type: 'error', message: 'Format file tidak valid. Gunakan CSV/XLSX sesuai template.' });
            } else {
                setResult({ type: 'error', message: 'Import gagal diproses.' });
            }
        } finally {
            setLoadingImport(false);
        }
    };

    return (
        <>
            <Head title="Input Saldo Awal" />

            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="mb-1 text-base font-semibold text-gray-800 dark:text-gray-100">Input Saldo Awal (Manual)</h2>
                    <p className="mb-4 text-sm text-gray-500">Isi qty, UOM, dan harga per unit untuk membuat mutasi opening balance.</p>

                    <form onSubmit={submitManual} className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Gudang</label>
                            <select value={manualForm.warehouse_id} onChange={(e) => updateManual('warehouse_id', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950">
                                <option value="">Pilih Gudang</option>
                                {warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}
                            </select>
                            {errors.warehouse_id && <p className="mt-1 text-xs text-red-500">{errors.warehouse_id[0]}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Item</label>
                            <select value={manualForm.item_id} onChange={(e) => updateManual('item_id', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950">
                                <option value="">Pilih Item</option>
                                {items.map((item) => <option key={item.id} value={item.id}>{item.sku} - {item.name}</option>)}
                            </select>
                            {errors.item_id && <p className="mt-1 text-xs text-red-500">{errors.item_id[0]}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">UOM</label>
                            <select value={manualForm.uom_id} onChange={(e) => updateManual('uom_id', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950">
                                <option value="">Pilih UOM</option>
                                {uoms.map((uom) => <option key={uom.id} value={uom.id}>{uom.code} - {uom.name}</option>)}
                            </select>
                            {errors.uom_id && <p className="mt-1 text-xs text-red-500">{errors.uom_id[0]}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Qty</label>
                            <input type="number" step="0.000001" min="0" value={manualForm.qty} onChange={(e) => updateManual('qty', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" />
                            {errors.qty && <p className="mt-1 text-xs text-red-500">{errors.qty[0]}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Harga per Unit</label>
                            <input type="number" step="0.000001" min="0" value={manualForm.unit_cost} onChange={(e) => updateManual('unit_cost', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" />
                            {errors.unit_cost && <p className="mt-1 text-xs text-red-500">{errors.unit_cost[0]}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-gray-700 dark:text-gray-300">Tanggal/Waktu Transaksi (opsional)</label>
                            <input type="datetime-local" value={manualForm.trx_datetime} onChange={(e) => updateManual('trx_datetime', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-900 dark:bg-gray-950" />
                        </div>

                        <div className="md:col-span-2 lg:col-span-3">
                            <button type="submit" disabled={loadingManual} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900">
                                {loadingManual ? 'Menyimpan...' : 'Simpan Saldo Awal'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <h2 className="mb-1 text-base font-semibold text-gray-800 dark:text-gray-100">Import Saldo Awal (CSV/Excel)</h2>
                    <p className="mb-4 text-sm text-gray-500">Download template, isi data, lalu upload untuk import massal saldo awal.</p>

                    <div className="mb-4 flex flex-wrap gap-2">
                        <a href={route('apps.inventory.opening-balance.template.csv')} className="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900">Download Template CSV</a>
                        <a href={route('apps.inventory.opening-balance.template.excel')} className="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900">Download Template Excel</a>
                    </div>

                    <form onSubmit={submitImport} className="flex flex-col gap-3 md:flex-row md:items-center">
                        <input type="file" accept=".csv,.xlsx,.xls,.txt" onChange={(e) => setImportFile(e.target.files?.[0] ?? null)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-900" />
                        <button type="submit" disabled={loadingImport} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {loadingImport ? 'Importing...' : 'Import Saldo Awal'}
                        </button>
                    </form>
                </div>

                {result && (
                    <div className={`rounded-lg border p-4 text-sm ${result.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
                        <p className="font-medium">{result.message}</p>
                        {Array.isArray(result.details) && result.details.length > 0 && (
                            <ul className="mt-2 list-disc pl-5">
                                {result.details.slice(0, 20).map((detail, index) => <li key={index}>Baris {detail.row}: {detail.message}</li>)}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
