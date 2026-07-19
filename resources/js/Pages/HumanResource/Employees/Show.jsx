import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const formatDate = (value) => {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const safeValue = (value, fallback = '-') => {
    if (value === null || value === undefined || value === '') return fallback;
    return value;
};

const getLicenseStatusBadgeClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-700 border-green-200',
        expiring_soon: 'bg-amber-100 text-amber-700 border-amber-200',
        expired: 'bg-red-100 text-red-700 border-red-200',
        suspended: 'bg-gray-100 text-gray-700 border-gray-200',
        revoked: 'bg-rose-100 text-rose-800 border-rose-200',
    };

    return classes[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const getEmployeeStatusBadgeClass = (isActive) => (
    isActive ? 'bg-green-100 text-green-700 border-green-200' : 'bg-gray-100 text-gray-700 border-gray-200'
);

const toLabel = (value) => safeValue(value, '-').toString().replaceAll('_', ' ').replace(/\b\w/g, (s) => s.toUpperCase());

export default function Show() {
    const { employee, summary, signerProfiles = [], poTypes = {} } = usePage().props;
    const [activeTab, setActiveTab] = useState('overview');

    const licenses = employee.licenses || [];
    const tabs = [
        ['overview', 'Overview'],
        ['licenses', 'Licenses & Certifications'],
        ['documents', 'Documents'],
        ['login', 'Login Account'],
        ['signers', 'PO Signers'],
        ['activity', 'Activity'],
    ];

    const initials = useMemo(() => {
        const name = safeValue(employee.full_name, '').trim();
        if (!name) return 'NA';
        return name.split(' ').slice(0, 2).map((n) => n[0]).join('').toUpperCase();
    }, [employee.full_name]);

    return <>
        <Head title='Employee 360 View' />

        <div className='space-y-4'>
            <div className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm'>
                <div className='flex flex-col gap-4 md:flex-row md:items-start md:justify-between'>
                    <div className='flex gap-4'>
                        <div className='flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-100 text-lg font-semibold text-gray-700'>
                            {employee.photo_url ? <img src={employee.photo_url} alt={employee.full_name} className='h-full w-full object-cover' /> : initials}
                        </div>
                        <div>
                            <h1 className='text-2xl font-bold text-gray-900'>{safeValue(employee.full_name)}</h1>
                            <p className='text-sm text-gray-600'>{safeValue(employee.employee_code)} • {safeValue(employee.position?.name)} • {safeValue(employee.department?.name)}</p>
                            <div className='mt-2 flex flex-wrap gap-2'>
                                <span className={`rounded-full border px-2 py-0.5 text-xs font-medium ${getEmployeeStatusBadgeClass(employee.is_active)}`}>
                                    {employee.is_active ? 'Active' : 'Inactive'}
                                </span>
                                <span className='rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700'>
                                    {safeValue(employee.employment_status, 'Employment Status N/A')}
                                </span>
                                {summary?.primary_license?.license_number && <span className='rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700'>
                                    Primary License: {summary.primary_license.license_number}
                                </span>}
                            </div>
                        </div>
                    </div>

                    <div className='flex flex-wrap gap-2 md:justify-end'>
                        <Link href={route('apps.human-resource.employees.edit', employee.id)} className='rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'>Edit Employee</Link>
                        <button type='button' className='cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400' disabled>Add License</button>
                        <button type='button' className='cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400' disabled>Link/Create User Login</button>
                        <Link href={route('apps.human-resource.employees.index')} className='rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'>Back to List</Link>
                    </div>
                </div>
            </div>

            <div className='grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5'>
                {[
                    ['Total Licenses', summary?.total_licenses ?? 0],
                    ['Active Licenses', summary?.active_licenses ?? 0],
                    ['Expiring Soon', summary?.expiring_soon_licenses ?? 0],
                    ['Expired Licenses', summary?.expired_licenses ?? 0],
                    ['Login Account', summary?.has_login_account ? 'Linked' : 'Not Linked'],
                ].map(([label, value]) => <div key={label} className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm'>
                    <p className='text-xs font-semibold uppercase tracking-wide text-gray-500'>{label}</p>
                    <p className='mt-1 text-xl font-semibold text-gray-900'>{value}</p>
                </div>)}
            </div>

            <div className='overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 shadow-sm'>
                <div className='flex min-w-max gap-1'>
                    {tabs.map(([key, label]) => <button key={key} type='button' onClick={() => setActiveTab(key)} className={`rounded-lg px-4 py-2 text-sm font-medium ${activeTab === key ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{label}</button>)}
                </div>
            </div>

            {activeTab === 'overview' && <div className='grid gap-4 lg:grid-cols-2'>
                {[['Basic Information', [['Employee Code', employee.employee_code], ['Full Name', employee.full_name], ['NIK', employee.nik], ['Gender', employee.gender], ['Birth Place', employee.birth_place], ['Birth Date', formatDate(employee.birth_date)]]],
                    ['Employment Information', [['Department', employee.department?.name], ['Position', employee.position?.name], ['Join Date', formatDate(employee.join_date)], ['Employment Status', employee.employment_status], ['Warehouse', employee.warehouse?.name ?? employee.warehouse_name], ['Company', employee.company?.name ?? employee.company_name]]],
                    ['Contact Information', [['Email', employee.email], ['Phone', employee.phone], ['Address', employee.address]]],
                    ['Document Signature Profile', [['Signature Status', employee.signature_path ? 'Available' : 'Not Available'], ['Name for Document', employee.signature_name ?? employee.full_name], ['Position for Document', employee.signature_position ?? employee.position?.name], ['Primary License Number', summary?.primary_license?.license_number], ['Linked PO Signer Profiles', summary?.signer_profile_count ?? 0]]],
                ].map(([title, rows]) => <div key={title} className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm'>
                    <h2 className='text-sm font-semibold uppercase tracking-wide text-gray-500'>{title}</h2>
                    <div className='mt-3 space-y-2'>
                        {rows.map(([label, value]) => <div key={label} className='flex items-start justify-between gap-4 border-b border-gray-100 pb-2 text-sm last:border-b-0 last:pb-0'>
                            <span className='text-gray-500'>{label}</span>
                            <span className='text-right font-medium text-gray-900'>{safeValue(value)}</span>
                        </div>)}
                    </div>
                </div>)}
            </div>}

            {activeTab === 'licenses' && <div className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm'>
                <div className='mb-3 flex items-center justify-between'>
                    <h2 className='text-lg font-semibold text-gray-900'>Licenses & Certifications</h2>
                    <button type='button' disabled className='cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400'>Add License</button>
                </div>
                {licenses.length === 0 ? <div className='rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500'>No licenses or certifications found.</div> : <div className='overflow-x-auto'>
                    <table className='min-w-full text-sm'>
                        <thead><tr className='border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500'><th className='px-3 py-2'>License Type</th><th className='px-3 py-2'>License Number</th><th className='px-3 py-2'>Issued By</th><th className='px-3 py-2'>Issued Date</th><th className='px-3 py-2'>Expired Date</th><th className='px-3 py-2'>Status</th><th className='px-3 py-2'>Primary</th><th className='px-3 py-2'>Document</th><th className='px-3 py-2'>Action</th></tr></thead>
                        <tbody>
                            {licenses.map((license) => <tr key={license.id} className='border-b border-gray-100'>
                                <td className='px-3 py-2'>{safeValue(license.license_type?.name ?? license.licenseType?.name)}</td>
                                <td className='px-3 py-2 font-medium'>{safeValue(license.license_number)}</td>
                                <td className='px-3 py-2'>{safeValue(license.issued_by)}</td>
                                <td className='px-3 py-2'>{formatDate(license.issued_date)}</td>
                                <td className='px-3 py-2'>{formatDate(license.expired_date)}</td>
                                <td className='px-3 py-2'><span className={`rounded-full border px-2 py-0.5 text-xs font-medium ${getLicenseStatusBadgeClass(license.computed_status ?? license.status)}`}>{toLabel(license.computed_status ?? license.status)}</span></td>
                                <td className='px-3 py-2'>{license.is_primary ? 'Yes' : 'No'}</td>
                                <td className='px-3 py-2'>{license.document_path || license.document_id ? <span className='text-blue-600'>Available</span> : '-'}</td>
                                <td className='px-3 py-2'><button disabled className='cursor-not-allowed text-gray-400'>Edit</button></td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>}
            </div>}

            {activeTab === 'documents' && <div className='rounded-xl border border-gray-200 bg-white p-6 shadow-sm text-sm text-gray-600'>
                Employee documents integration is ready. Connect with Document Center to show KTP, contract, certificates, SIPA, STRA, and other documents.
            </div>}

            {activeTab === 'login' && <div className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-3'>
                <h2 className='text-lg font-semibold text-gray-900'>Login Account</h2>
                <div className='flex flex-wrap items-center gap-2'>
                    <span className={`rounded-full border px-2 py-0.5 text-xs font-medium ${employee.user ? 'bg-green-100 text-green-700 border-green-200' : 'bg-gray-100 text-gray-700 border-gray-200'}`}>{employee.user ? 'Linked' : 'Not Linked'}</span>
                </div>
                <div className='grid gap-2 sm:grid-cols-2 text-sm'>
                    <div><span className='text-gray-500'>User Name:</span> <span className='font-medium text-gray-900'>{safeValue(employee.user?.name)}</span></div>
                    <div><span className='text-gray-500'>User Email:</span> <span className='font-medium text-gray-900'>{safeValue(employee.user?.email)}</span></div>
                    <div><span className='text-gray-500'>Roles:</span> <span className='font-medium text-gray-900'>{employee.user?.roles?.length ? employee.user.roles.map((r) => r.name).join(', ') : '-'}</span></div>
                    <div><span className='text-gray-500'>User Status:</span> <span className='font-medium text-gray-900'>{employee.user ? (employee.user.is_active ? 'Active' : 'Inactive') : '-'}</span></div>
                    <div><span className='text-gray-500'>Last Login:</span> <span className='font-medium text-gray-900'>{formatDate(employee.user?.last_login_at)}</span></div>
                </div>
                {!employee.user && <button type='button' disabled className='cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400'>Create / Link User Login</button>}
            </div>}


            {activeTab === 'signers' && <div className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm'>
                <div className='mb-3 flex items-start justify-between gap-3'>
                    <div>
                        <h2 className='text-lg font-semibold text-gray-900'>PO Signer Profiles</h2>
                        <p className='text-sm text-gray-500'>Employee ini dapat dipakai sebagai penanda tangan PO bila tercantum sebagai requester atau approver di master purchase_order_signers.</p>
                    </div>
                </div>
                {signerProfiles.length === 0 ? <div className='rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500'>Belum terhubung ke signer profile PO. Buat profile signer untuk jenis PO terkait dan pilih employee ini sebagai requester/approver.</div> : <div className='overflow-x-auto'>
                    <table className='min-w-full text-sm'>
                        <thead><tr className='border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500'><th className='px-3 py-2'>Jenis PO</th><th className='px-3 py-2'>Role</th><th className='px-3 py-2'>Nama Cetak</th><th className='px-3 py-2'>Jabatan Cetak</th><th className='px-3 py-2'>No Lisensi</th><th className='px-3 py-2'>Status</th></tr></thead>
                        <tbody>{signerProfiles.map((profile) => {
                            const role = profile.requester_employee_id === employee.id ? 'Requester / Pemohon' : 'Approver / Persetujuan';
                            const name = profile.requester_employee_id === employee.id ? profile.requester_name : profile.approver_name;
                            const title = profile.requester_employee_id === employee.id ? profile.requester_title : profile.approver_title;
                            const licenseNo = profile.requester_employee_id === employee.id ? profile.requester_license_no : profile.approver_license_no;
                            return <tr key={profile.id} className='border-b border-gray-100'><td className='px-3 py-2'>{poTypes[profile.po_type] || profile.po_type}</td><td className='px-3 py-2'>{role}</td><td className='px-3 py-2'>{safeValue(name, employee.full_name)}</td><td className='px-3 py-2'>{safeValue(title, employee.position?.name)}</td><td className='px-3 py-2'>{safeValue(licenseNo)}</td><td className='px-3 py-2'>{profile.is_active ? 'Active' : 'Inactive'}</td></tr>;
                        })}</tbody>
                    </table>
                </div>}
            </div>}

            {activeTab === 'activity' && <div className='rounded-xl border border-gray-200 bg-white p-4 shadow-sm'>
                <h2 className='text-lg font-semibold text-gray-900'>Activity / Audit Trail</h2>
                <div className='mt-3 grid gap-2 text-sm sm:grid-cols-2'>
                    <div><span className='text-gray-500'>Created At:</span> <span className='font-medium text-gray-900'>{formatDate(employee.created_at)}</span></div>
                    <div><span className='text-gray-500'>Updated At:</span> <span className='font-medium text-gray-900'>{formatDate(employee.updated_at)}</span></div>
                    <div><span className='text-gray-500'>Created By:</span> <span className='font-medium text-gray-900'>{safeValue(employee.created_by?.name ?? employee.created_by_name)}</span></div>
                    <div><span className='text-gray-500'>Updated By:</span> <span className='font-medium text-gray-900'>{safeValue(employee.updated_by?.name ?? employee.updated_by_name)}</span></div>
                </div>
            </div>}
        </div>
    </>;
}

Show.layout = (page) => <AppLayout children={page} />;
