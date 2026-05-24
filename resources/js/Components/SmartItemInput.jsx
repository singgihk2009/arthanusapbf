import axios from 'axios';
import { IconPackage } from '@tabler/icons-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function SmartItemInput({ value, onSelect, placeholder = 'Scan barcode / type SKU / type product name...', disabled = false, warehouseId = null, autoFocus = false, inputClassName = '', inputRef = null }) {
  const [query, setQuery] = useState(value?.name || value?.label || '');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const [error, setError] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const debounceRef = useRef(null);
  const localInputRef = useRef(null);

  useEffect(() => {
    setQuery(value?.name || value?.label || '');
  }, [value?.id]);

  const canAutoSearch = useMemo(() => query.trim().length >= 3, [query]);

  const runSearch = async (term, mode = 'auto') => {
    setLoading(true);
    setError('');

    try {
      const response = await axios.get(route('apps.items.search'), {
        params: { q: term, mode, limit: 20, warehouse_id: warehouseId || undefined },
      });

      const data = response?.data || [];
      setResults(data);
      setIsOpen(true);
      setHighlightedIndex(data.length > 0 ? 0 : -1);

      if (data.length === 0) setError(mode === 'barcode' ? 'Barcode not found' : 'No product found');
      return data;
    } finally { setLoading(false); }
  };

  const focusInput = () => (inputRef?.current || localInputRef.current)?.focus();

  const selectItem = (item) => {
    onSelect?.(item);
    setQuery(item?.name || item?.label || '');
    setResults([]);
    setIsOpen(false);
    setHighlightedIndex(-1);
    setError('');
    focusInput();
  };

  const onChange = (e) => {
    const next = e.target.value;
    setQuery(next);
    setError('');
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (next.trim().length < 3) { setResults([]); setIsOpen(false); setHighlightedIndex(-1); return; }
    debounceRef.current = setTimeout(() => runSearch(next.trim(), 'auto'), 200);
  };

  const onKeyDown = async (e) => {
    if (e.key === 'ArrowDown') { if (!isOpen || results.length === 0) return; e.preventDefault(); setHighlightedIndex((prev) => (prev + 1) % results.length); return; }
    if (e.key === 'ArrowUp') { if (!isOpen || results.length === 0) return; e.preventDefault(); setHighlightedIndex((prev) => (prev <= 0 ? results.length - 1 : prev - 1)); return; }
    if (e.key === 'Escape') { setIsOpen(false); setHighlightedIndex(-1); return; }
    if (e.key === 'Enter') { e.preventDefault(); const term = query.trim(); if (!term) return; if (isOpen && highlightedIndex >= 0 && results[highlightedIndex]) { selectItem(results[highlightedIndex]); return; } const scanned = await runSearch(term, 'barcode'); if (scanned.length === 1) selectItem(scanned[0]); }
  };

  return (
    <div className='relative'>
      <input
        ref={inputRef || localInputRef}
        type='text'
        className={`w-full rounded-lg border border-slate-200 p-1 text-sm outline-none ring-orange-200 transition focus:ring-2 ${inputClassName}`}
        value={query}
        placeholder={placeholder}
        onChange={onChange}
        onKeyDown={onKeyDown}
        disabled={disabled}
        autoFocus={autoFocus}
      />
      {loading && <div className='mt-1 text-xs text-slate-500'>Searching...</div>}
      {!loading && error && <div className='mt-1 text-xs text-amber-600'>{error}</div>}

      {isOpen && canAutoSearch && results.length > 0 && (
        <div className='absolute z-20 mt-2 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-xl max-h-80'>
          {results.map((item, index) => (
            <button
              key={item.id}
              type='button'
              className={`w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 ${index === highlightedIndex ? 'bg-orange-50' : 'hover:bg-slate-50'}`}
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => selectItem(item)}
            >
              <div className='flex items-start justify-between gap-2'>
                <div>
                  <div className='text-sm font-semibold text-slate-800'>{item.name}</div>
                  <div className='text-xs text-slate-500'>SKU: {item.sku || '-'} • Barcode: {item.barcode || '-'} • UOM: {item.uom_name || '-'}</div>
                  <div className='mt-1 text-xs text-slate-500'>Stock: {item.available_stock ?? '-'} • Price: {Number(item.selling_price || 0).toLocaleString('id-ID')}</div>
                </div>
                <IconPackage className='h-4 w-4 text-slate-400'/>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
