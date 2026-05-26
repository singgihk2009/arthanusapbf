import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({ companyProfile, party, documents, documentTypes }) {
  const { data, setData, put } = useForm({ ...companyProfile });
  const logoForm = useForm({ logo: null });
  const docForm = useForm({ file: null, document_type_id: '', title: '', document_number: '', issue_date: '', expiry_date: '' });

  const submitProfile = (e) => { e.preventDefault(); put('/apps/setup/company-profile'); };
  const profileFields = [
    { key: 'legal_name', label: 'Nama Legal' },
    { key: 'tax_id', label: 'NPWP' },
    { key: 'phone', label: 'Telepon' },
    { key: 'email', label: 'Email' },
    { key: 'website', label: 'Website' },
    { key: 'address', label: 'Alamat' },
    { key: 'city', label: 'Kota' },
    { key: 'province', label: 'Provinsi' },
    { key: 'postal_code', label: 'Kode Pos' },
    { key: 'country', label: 'Negara' },
    { key: 'pbf_license_number', label: 'No Izin PBF' },
    { key: 'idak_license_number', label: 'No Izin IDAK' },
    { key: 'cdob_other_license_number', label: 'No Izin CDOB Obat Lain' },
    { key: 'cdob_ccp_license_number', label: 'No Izin CDOB CCP' },
    { key: 'invoice_footer', label: 'Footer Invoice' },
    { key: 'invoice_terms', label: 'Syarat Invoice' },
  ];
  const submitLogo = (e) => { e.preventDefault(); logoForm.post('/apps/setup/company-profile/logo'); };
  const submitDoc = (e) => { e.preventDefault(); docForm.transform((d) => ({ ...d, owner_type: 'party', owner_id: party.id, business_id: 1 })).post('/apps/document-center/documents'); };

  return <AppLayout><Head title='Company Profile' /><div className='p-6 space-y-6'>
    <div className='bg-white rounded shadow p-4 flex gap-4'>
      <img src={companyProfile.logo_path ? `/storage/${companyProfile.logo_path}` : 'https://placehold.co/120x120?text=Logo'} className='w-24 h-24 object-cover rounded' />
      <div>
        <h1 className='text-2xl font-semibold'>{data.legal_name || party.name}</h1>
        <p>NPWP: {data.tax_id || '-'}</p><p>Telp: {data.phone || '-'}</p><p>Email: {data.email || '-'}</p>
        <p>{data.address || '-'} {data.city || ''} {data.province || ''} {data.postal_code || ''}</p>
      </div>
    </div>

    <form onSubmit={submitProfile} className='bg-white rounded shadow p-4 grid grid-cols-2 gap-3'>
      {profileFields.map((field) =>
        <input key={field.key} className='border p-2 rounded' placeholder={field.label} value={data[field.key] || ''} onChange={(e) => setData(field.key, e.target.value)} />)}
      <button className='bg-blue-600 text-white px-4 py-2 rounded col-span-2'>Simpan Profile</button>
    </form>

    <form onSubmit={submitLogo} className='bg-white rounded shadow p-4 space-y-2'>
      <h2 className='font-semibold'>Upload Logo</h2>
      <input type='file' accept='image/*' onChange={(e) => logoForm.setData('logo', e.target.files[0])} />
      <button className='bg-emerald-600 text-white px-4 py-2 rounded'>Upload Logo</button>
    </form>

    <div className='bg-white rounded shadow p-4 space-y-3'>
      <h2 className='font-semibold'>Company Documents</h2>
      <form onSubmit={submitDoc} className='grid grid-cols-2 gap-2'>
        <select className='border p-2 rounded' value={docForm.data.document_type_id} onChange={e => docForm.setData('document_type_id', e.target.value)}>
          <option value=''>Pilih Tipe Dokumen</option>
          {documentTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
        </select>
        <input className='border p-2 rounded' placeholder='Title' value={docForm.data.title} onChange={e => docForm.setData('title', e.target.value)} />
        <input type='file' onChange={e => docForm.setData('file', e.target.files[0])} />
        <button className='bg-indigo-600 text-white px-4 py-2 rounded'>Upload Document</button>
      </form>
      <table className='w-full text-sm border'><thead><tr><th className='border p-2'>Type</th><th className='border p-2'>Title</th><th className='border p-2'>Status</th></tr></thead>
        <tbody>{documents.map((d) => <tr key={d.id}><td className='border p-2'>{d.document_type?.name || '-'}</td><td className='border p-2'>{d.title}</td><td className='border p-2'>{d.status}</td></tr>)}</tbody>
      </table>
    </div>
  </div></AppLayout>;
}
