<?php

namespace App\Http\Controllers\Apps\HumanResource;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLicense;
use App\Models\Position;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderSigner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        $query = Employee::with(['department', 'position', 'licenses'])->where('company_id', $companyId);

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%"));
        }
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('position_id')) $query->where('position_id', $request->position_id);
        if ($request->filled('status')) $query->where('is_active', $request->status === 'active');

        return Inertia::render('HumanResource/Employees/Index', [
            'employees' => $query->latest()->paginate(10)->withQueryString(),
            'departments' => Department::where('company_id', $companyId)->get(),
            'positions' => Position::where('company_id', $companyId)->get(),
            'filters' => $request->only(['search', 'department_id', 'position_id', 'status']),
            'stats' => [
                'total' => Employee::where('company_id', $companyId)->count(),
                'active' => Employee::where('company_id', $companyId)->where('is_active', 1)->count(),
                'expiring_soon' => EmployeeLicense::where('company_id', $companyId)->whereBetween('expired_date', [now(), now()->addDays(90)])->count(),
                'expired' => EmployeeLicense::where('company_id', $companyId)->whereDate('expired_date', '<', now())->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);

        return Inertia::render('HumanResource/Employees/Create', [
            'departments' => Department::where('company_id', $companyId)->orderBy('name')->get(),
            'positions' => Position::where('company_id', $companyId)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        $data = $this->validateEmployee($request, $companyId);
        $data['company_id'] = $companyId;
        $employee = Employee::create($data);

        if ($request->boolean('create_login')) {
            User::create([
                'name' => $employee->full_name,
                'email' => $request->login_email,
                'password' => Hash::make($request->login_password ?? 'password123'),
                'company_id' => $companyId,
                'employee_id' => $employee->id,
            ]);
        }

        return redirect()->route('apps.human-resource.employees.show', $employee)->with('success', 'Employee created');
    }

    public function show(Request $request, Employee $employee)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        abort_unless((int) $employee->company_id === $companyId, 404);

        $today = Carbon::today();
        $expiringLimit = Carbon::today()->addDays(90);
        $employee->load(['department', 'position', 'licenses.licenseType', 'user.roles']);
        $licenses = collect($employee->licenses)->map(function ($license) use ($today, $expiringLimit) {
            $status = strtolower((string) ($license->status ?? ''));
            if (in_array($status, ['suspended', 'revoked'], true)) {
                $computed = $status;
            } else {
                $expiredDate = $license->expired_date ? Carbon::parse($license->expired_date) : null;
                if ($expiredDate && $expiredDate->lt($today)) $computed = 'expired';
                elseif ($expiredDate && $expiredDate->lte($expiringLimit)) $computed = 'expiring_soon';
                else $computed = 'active';
            }
            $license->setAttribute('computed_status', $computed);

            return $license;
        });
        $primaryLicense = $licenses->first(fn ($license) => (bool) $license->is_primary) ?? $licenses->first();
        $signerProfiles = PurchaseOrderSigner::query()
            ->where(function ($q) use ($employee) {
                $q->where('requester_employee_id', $employee->id)
                    ->orWhere('approver_employee_id', $employee->id);
            })
            ->orderBy('po_type')
            ->get();

        return Inertia::render('HumanResource/Employees/Show', [
            'employee' => $employee,
            'summary' => [
                'total_licenses' => $licenses->count(),
                'active_licenses' => $licenses->where('computed_status', 'active')->count(),
                'expiring_soon_licenses' => $licenses->where('computed_status', 'expiring_soon')->count(),
                'expired_licenses' => $licenses->where('computed_status', 'expired')->count(),
                'has_login_account' => $employee->user !== null,
                'primary_license' => $primaryLicense,
                'signer_profile_count' => $signerProfiles->count(),
            ],
            'signerProfiles' => $signerProfiles,
            'poTypes' => PurchaseOrder::TYPE_LABELS,
        ]);
    }


    public function storeSignerProfile(Request $request, Employee $employee)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        abort_unless((int) $employee->company_id === $companyId, 404);

        $data = $request->validate([
            'po_type' => ['required', 'in:'.implode(',', PurchaseOrder::TYPES)],
            'role' => ['required', 'in:requester,approver'],
            'print_name' => ['nullable', 'string', 'max:255'],
            'print_title' => ['nullable', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = ['po_type' => $data['po_type'], 'is_active' => true];
        $defaults = [
            'requester_name' => $employee->full_name,
            'requester_title' => $employee->position?->name,
            'approver_name' => $employee->full_name,
            'approver_title' => $employee->position?->name,
        ];

        if ($data['role'] === 'requester') {
            $payload['requester_employee_id'] = $employee->id;
            $payload['requester_name'] = $data['print_name'] ?: $defaults['requester_name'];
            $payload['requester_title'] = $data['print_title'] ?: $defaults['requester_title'];
            $payload['requester_license_no'] = $data['license_no'] ?? null;
        } else {
            $payload['approver_employee_id'] = $employee->id;
            $payload['approver_name'] = $data['print_name'] ?: $defaults['approver_name'];
            $payload['approver_title'] = $data['print_title'] ?: $defaults['approver_title'];
            $payload['approver_license_no'] = $data['license_no'] ?? null;
        }

        PurchaseOrderSigner::create($payload);

        return back()->with('success', 'PO signer profile linked to employee.');
    }

    public function edit(Request $request, Employee $employee)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        abort_unless((int) $employee->company_id === $companyId, 404);

        return Inertia::render('HumanResource/Employees/Edit', [
            'employee' => $employee->load(['user', 'department', 'position']),
            'departments' => Department::where('company_id', $companyId)->orderBy('name')->get(),
            'positions' => Position::where('company_id', $companyId)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        abort_unless((int) $employee->company_id === $companyId, 404);
        $employee->update($this->validateEmployee($request, $companyId, $employee));

        return redirect()->route('apps.human-resource.employees.show', $employee)->with('success', 'Employee updated');
    }

    public function destroy(Request $request, Employee $employee)
    {
        abort_unless((int) $employee->company_id === (int) ($request->user()->company_id ?? 1), 404);
        $employee->update(['is_active' => false]);

        return back()->with('success', 'Employee deactivated');
    }

    private function validateEmployee(Request $request, int $companyId, ?Employee $employee = null): array
    {
        $employeeId = $employee?->id ?? 'NULL';

        return $request->validate([
            'employee_code' => "required|max:100|unique:employees,employee_code,{$employeeId},id,company_id,{$companyId}",
            'nik' => "nullable|max:100|unique:employees,nik,{$employeeId},id,company_id,{$companyId}",
            'full_name' => ['required', 'max:255'],
            'gender' => ['nullable', 'max:20'],
            'birth_place' => ['nullable', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'max:30'],
            'address' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'join_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'create_login' => ['nullable', 'boolean'],
            'login_email' => ['nullable', 'email', 'unique:users,email'],
            'login_password' => ['nullable', 'min:6'],
        ]);
    }
}
