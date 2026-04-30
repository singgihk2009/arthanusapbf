import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React from 'react';

export default function Index() {
    const { items, selectedItem } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ pictures: [], default_new_picture_index: '' });

    const upload = (e) => {
        e.preventDefault();
        if (!selectedItem) return;
        post(route('apps.master-data.pictures.store', selectedItem.id));
    };

    return (
        <>
            <Head title="Picture Produk" />
            <div className="space-y-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <label className="mb-2 block text-sm font-medium">Pilih Produk</label>
                    <select
                        value={selectedItem?.id ?? ''}
                        onChange={(e) => router.get(route('apps.master-data.pictures.index'), { item_id: e.target.value || undefined }, { preserveState: true })}
                        className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <option value="">-- pilih produk --</option>
                        {items.map((item) => <option key={item.id} value={item.id}>{item.sku} - {item.name} ({item.pictures_count}/6)</option>)}
                    </select>
                </div>

                {selectedItem && (
                    <>
                        <form onSubmit={upload} className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950 space-y-3">
                            <h2 className="font-semibold">Upload Foto untuk {selectedItem.name}</h2>
                            <input type="file" multiple accept="image/*" onChange={(e) => setData('pictures', Array.from(e.target.files).slice(0, 6))} className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" />
                            {errors.pictures && <small className="text-xs text-red-500">{errors.pictures}</small>}
                            <select value={data.default_new_picture_index} onChange={(e) => setData('default_new_picture_index', e.target.value)} className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900">
                                <option value="">Default upload: otomatis</option>
                                {data.pictures.map((_, index) => <option key={index} value={index}>Foto upload #{index + 1} jadi default</option>)}
                            </select>
                            <Button type="submit" label="Upload" variant="gray" disabled={processing} />
                        </form>

                        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            {selectedItem.pictures?.map((picture) => (
                                <div key={picture.id} className="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-900 dark:bg-gray-950 space-y-2">
                                    <img src={picture.image_url} alt={picture.file_name} className="h-36 w-full rounded object-cover" />
                                    <p className="truncate text-xs">{picture.file_name}</p>
                                    <div className="flex gap-2">
                                        <button type="button" className="rounded border px-2 py-1 text-xs" onClick={() => router.post(route('apps.master-data.pictures.default', selectedItem.id), { _method: 'PATCH', picture_id: picture.id })}>Jadikan Default</button>
                                        <button type="button" className="rounded border border-red-300 px-2 py-1 text-xs text-red-600" onClick={() => router.delete(route('apps.master-data.pictures.destroy', [selectedItem.id, picture.id]))}>Hapus</button>
                                    </div>
                                    {picture.is_default && <p className="text-xs font-semibold text-emerald-600">Default</p>}
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
