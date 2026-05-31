<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        $search = trim((string) $request->query('search', ''));

        $cashAccounts = CashAccount::query()
            ->with('chartOfAccount:id,account_code,account_name,account_type')
            ->where('company_id', $companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('bank_name', 'like', '%'.$search.'%')
                        ->orWhere('account_number', 'like', '%'.$search.'%')
                        ->orWhereHas('chartOfAccount', function ($query) use ($search): void {
                            $query->where('account_code', 'like', '%'.$search.'%')
                                ->orWhere('account_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Apps/MasterData/CashAccounts/Index', [
            'cashAccounts' => $cashAccounts,
            'chartAccounts' => $this->chartAccounts($companyId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        $data = $this->validatedData($request, $companyId);
        $data['company_id'] = $companyId;

        $cashAccount = CashAccount::query()->create($data);
        $this->syncDefault($cashAccount);

        return back()->with('success', 'Cash Account berhasil dibuat.');
    }

    public function update(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        abort_unless((int) $cashAccount->company_id === $companyId, 404);

        $data = $this->validatedData($request, $companyId, $cashAccount->id);
        $cashAccount->update($data);
        $this->syncDefault($cashAccount->fresh());

        return back()->with('success', 'Cash Account berhasil diperbarui.');
    }

    public function destroy(CashAccount $cashAccount): RedirectResponse
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        abort_unless((int) $cashAccount->company_id === $companyId, 404);

        $cashAccount->delete();

        return back()->with('success', 'Cash Account berhasil dihapus.');
    }

    private function validatedData(Request $request, int $companyId, ?int $cashAccountId = null): array
    {
        return $request->validate([
            'chart_of_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('cash_accounts', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($cashAccountId)],
            'name' => ['required', 'string', 'max:255'],
            'cash_type' => ['required', Rule::in(['CASH', 'BANK', 'CASH_EQUIVALENT'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function chartAccounts(int $companyId)
    {
        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name', 'account_type'])
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'label' => trim($account->account_code.' - '.$account->account_name),
                'account_type' => $account->account_type,
            ]);
    }

    private function syncDefault(?CashAccount $cashAccount): void
    {
        if (! $cashAccount || ! $cashAccount->is_default) {
            return;
        }

        CashAccount::query()
            ->where('company_id', $cashAccount->company_id)
            ->where('id', '!=', $cashAccount->id)
            ->update(['is_default' => false]);
    }
}
