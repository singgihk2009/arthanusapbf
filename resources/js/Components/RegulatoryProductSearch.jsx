import React, { useEffect, useMemo, useState } from 'react';

const SOURCE_OPTIONS = [
    { label: 'All', value: '' },
    { label: 'BPOM', value: 'BPOM' },
    { label: 'KEMENKES', value: 'KEMENKES' },
];

export default function RegulatoryProductSearch({ selectedProduct, onSelect, onClear }) {
    const [query, setQuery] = useState('');
    const [sourceName, setSourceName] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const canSearch = query.trim().length >= 3;

    useEffect(() => {
        if (!canSearch) { setResults([]); setError(''); return; }
        const timer = setTimeout(async () => {
            setLoading(true);
            try {
                const response = await window.axios.get(route('apps.master-data.regulatory-products.search'), { params: { q: query, source_name: sourceName || undefined, limit: 20 } });
                setResults(response.data?.data ?? []);
                setError('');
            } catch (err) {
                setResults([]);
                setError(err?.response?.data?.message ?? 'Pencarian gagal. Coba ulangi.');
            } finally { setLoading(false); }
        }, 500);
        return () => clearTimeout(timer);
    }, [query, sourceName, canSearch]);

    return (<div className="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
        <div className="text-sm text-gray-600 dark:text-gray-300">Data BPOM/KEMENKES adalah referensi regulasi. SKU/UOM/barcode/stok tetap data internal perusahaan.</div>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-2">
            <input className="md:col-span-3 w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" placeholder="Cari NIE / nama produk / produsen..." value={query} onChange={(e) => setQuery(e.target.value)} />
            <select className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" value={sourceName} onChange={(e) => setSourceName(e.target.value)}>{SOURCE_OPTIONS.map((x)=><option key={x.label} value={x.value}>{x.label}</option>)}</select>
        </div>
        {loading && <div className="text-sm text-gray-500">Loading...</div>}
        {error && <div className="text-sm text-rose-600">{error}</div>}
        {!loading && !error && canSearch && results.length === 0 && <div className="text-sm text-gray-500">Tidak ada hasil.</div>}
        {results.length > 0 && <div className="max-h-64 overflow-auto rounded border border-gray-100 dark:border-gray-700"><table className="min-w-full text-xs"><thead><tr><th>Source</th><th>NIE</th><th>Nama Produk</th><th>Produsen</th><th>Sediaan</th><th>Kekuatan</th><th>Komoditi</th></tr></thead><tbody>{results.map((r)=><tr key={r.id} className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" onClick={()=>onSelect(r)}><td>{r.source_name}</td><td>{r.nie}</td><td>{r.product_name_source}</td><td>{r.industry_name}</td><td>{r.dosage_form}</td><td>{r.strength}</td><td>{r.commodity_type}</td></tr>)}</tbody></table></div>}
        {selectedProduct && <div className="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm dark:border-emerald-900 dark:bg-emerald-900/20"><div className="font-medium">Selected Regulatory Product</div><div>{selectedProduct.source_name} - {selectedProduct.nie}</div><div>{selectedProduct.product_name_source}</div><button type="button" className="mt-2 text-xs text-rose-600" onClick={onClear}>Clear Regulatory Reference</button></div>}
    </div>);
}
