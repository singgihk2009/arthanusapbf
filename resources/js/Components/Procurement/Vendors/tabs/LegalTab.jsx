export default function LegalTab({ data }) {
  const documents = data?.documents ?? [];

  return <div className='space-y-4'>
    <div className='overflow-auto rounded border p-3'>
      <table className='min-w-full border text-sm'>
        <thead>
          <tr className='bg-gray-100'>
            <th className='border px-3 py-2 text-left' colSpan={7}>Daftar dokumen yang harus di lengkapi</th>
          </tr>
          <tr className='bg-gray-50'>
            <th className='border px-3 py-2'>Document Type</th>
            <th className='border px-3 py-2'>Category</th>
            <th className='border px-3 py-2'>Requested</th>
            <th className='border px-3 py-2'>Document Number</th>
            <th className='border px-3 py-2'>Issue Date</th>
            <th className='border px-3 py-2'>Expiry Date</th>
            <th className='border px-3 py-2'>Status (Tab Document)</th>
          </tr>
        </thead>
        <tbody>
          {documents.length ? documents.map((doc) => <tr key={doc.requirement_id}>
            <td className='border px-3 py-2'>{doc.document_type_name ?? '-'}</td>
            <td className='border px-3 py-2'>{doc.category ?? '-'}</td>
            <td className='border px-3 py-2'>{doc.is_requested ? 'Yes' : 'No'}</td>
            <td className='border px-3 py-2'>{doc.document_number || '-'}</td>
            <td className='border px-3 py-2'>{doc.issue_date || '-'}</td>
            <td className='border px-3 py-2'>{doc.expiry_date || '-'}</td>
            <td className='border px-3 py-2'>{doc.verification_status || 'belum upload'}</td>
          </tr>) : <tr><td className='border px-3 py-3 text-center text-gray-500' colSpan={7}>Tidak ada dokumen yang wajib dilengkapi.</td></tr>}
        </tbody>

      </table>
      <p className='mt-2 text-right text-sm text-gray-600'>
        Note: Silahkan lengkapi dan upload dokumen pendukung dari menu Documents
      </p>
    </div>
  </div>;
}
