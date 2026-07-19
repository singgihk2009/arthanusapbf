export const PO_PRINT_TEMPLATES = {
    regular: { title: 'PURCHASE ORDER', requesterLabel: 'Pemohon', approverLabel: 'Persetujuan' },
    precursor: { title: 'SURAT PESANAN PREKURSOR', requesterLabel: 'Hormat saya', approverLabel: 'Mengetahui' },
    oot: { title: 'PURCHASE ORDER OOT', requesterLabel: 'Pemohon', approverLabel: 'Persetujuan' },
    alkes: { title: 'PURCHASE ORDER ALKES', requesterLabel: 'Pemohon', approverLabel: 'Persetujuan' },
};

export const getPurchaseOrderPrintTemplate = (poType = 'regular') => PO_PRINT_TEMPLATES[poType] || PO_PRINT_TEMPLATES.regular;

export const getSignerDisplay = (profile, side) => {
    const employee = side === 'requester' ? profile?.requester_employee : profile?.approver_employee;
    return {
        name: employee?.full_name || profile?.[`${side}_name`] || '',
        title: employee?.position?.name || profile?.[`${side}_title`] || '',
        licenseNo: profile?.[`${side}_license_no`] || '',
    };
};
