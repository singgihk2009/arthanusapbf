import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function SmartItemInput({ value, onSelect, placeholder = 'Scan barcode / type SKU / type product name...', disabled = false, warehouseId = null, autoFocus = false }) {
  const [query, setQuery] = useState(value?.name || value?.label || '');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const [error, setError] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const debounceRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    setQuery(value?.name || value?.label || '');
  }, [value?.id]);

  const canAutoSearch = useMemo(() => query.trim().length >= 3, [query]);

  const runSearch = async (term, mode = 'auto') => {
    setLoading(true);
    setError('');

    try {
      const response = await axios.get(route('apps.items.search'), {
        params: {
          q: term,
          mode,
          limit: 20,
          warehouse_id: warehouseId || undefined,
        },
      });

      const data = response?.data || [];
      setResults(data);
      setIsOpen(true);
      setHighlightedIndex(data.length > 0 ? 0 : -1);

      if (data.length === 0) {
        setError(mode === 'barcode' ? 'Barcode not found' : 'No product found');
      }

      return data;
    } finally {
      setLoading(false);
    }
  };

  const selectItem = (item) => {
    onSelect?.(item);
    setQuery('');
    setResults([]);
    setIsOpen(false);
    setHighlightedIndex(-1);
    setError('');
    inputRef.current?.focus();
  };

  const onChange = (e) => {
    const next = e.target.value;
    setQuery(next);
    setError('');

    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (next.trim().length < 3) {
      setResults([]);
      setIsOpen(false);
      setHighlightedIndex(-1);
      return;
    }

    debounceRef.current = setTimeout(() => {
      runSearch(next.trim(), 'auto');
    }, 250);
  };

  const onKeyDown = async (e) => {
    if (e.key === 'ArrowDown') {
      if (!isOpen || results.length === 0) return;
      e.preventDefault();
      setHighlightedIndex((prev) => (prev + 1) % results.length);
      return;
    }

    if (e.key === 'ArrowUp') {
      if (!isOpen || results.length === 0) return;
      e.preventDefault();
      setHighlightedIndex((prev) => (prev <= 0 ? results.length - 1 : prev - 1));
      return;
    }

    if (e.key === 'Escape') {
      setIsOpen(false);
      setHighlightedIndex(-1);
      return;
    }

    if (e.key === 'Enter') {
      e.preventDefault();
      const term = query.trim();
      if (!term) return;

      if (isOpen && highlightedIndex >= 0 && results[highlightedIndex]) {
        selectItem(results[highlightedIndex]);
        return;
      }

      const scanned = await runSearch(term, 'barcode');
      if (scanned.length === 1) selectItem(scanned[0]);
    }
  };

  return (
    <div className='relative'>
      <input
        ref={inputRef}
        type='text'
        className='border p-1 w-full'
        value={query}
        placeholder={placeholder}
        onChange={onChange}
        onKeyDown={onKeyDown}
        disabled={disabled}
        autoFocus={autoFocus}
      />
      {loading && <div className='text-[10px] text-gray-500 mt-1'>Searching...</div>}
      {!loading && error && <div className='text-[10px] text-amber-600 mt-1'>{error}</div>}

      {isOpen && canAutoSearch && results.length > 0 && (
        <div className='absolute z-20 mt-1 w-full rounded border bg-white shadow max-h-64 overflow-auto'>
          {results.map((item, index) => (
            <button
              key={item.id}
              type='button'
              className={`w-full text-left px-2 py-1 border-b last:border-b-0 ${index === highlightedIndex ? 'bg-orange-50' : ''}`}
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => selectItem(item)}
            >
              <div className='text-xs font-medium'>{item.name}</div>
              <div className='text-[10px] text-gray-600'>
                SKU: {item.sku || '-'} | Code: {item.code || '-'} | Barcode: {item.barcode || '-'} | UOM: {item.uom_name || '-'}
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
