<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $this->companyId($request);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'active');

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('account_code', 'like', '%'.$search.'%')
                        ->orWhere('account_name', 'like', '%'.$search.'%')
                        ->orWhere('account_type', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('account_code')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => ChartOfAccount::query()->where('company_id', $companyId)->count(),
            'active' => ChartOfAccount::query()->where('company_id', $companyId)->where('is_active', true)->count(),
            'inactive' => ChartOfAccount::query()->where('company_id', $companyId)->where('is_active', false)->count(),
        ];

        return Inertia::render('Apps/MasterData/ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => $stats,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt'],
        ]);

        $companyId = $this->companyId($request);
        $rows = $this->parseImportRows($request->file('file'));
        $requiredHeaders = ['code', 'name', 'is_active'];

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File import COA kosong atau tidak bisa dibaca.',
            ]);
        }

        if (! $this->hasRequiredHeaders($rows->first(), $requiredHeaders)) {
            throw ValidationException::withMessages([
                'file' => 'Format header file import COA tidak valid. Kolom wajib: '.implode(', ', $requiredHeaders).'.',
            ]);
        }

        $errors = [];
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        foreach ($rows as $index => $row) {
            if ($this->isRowEmpty($row)) {
                $skipped++;
                continue;
            }

            try {
                $data = validator($row, [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'is_active' => ['nullable'],
                ])->validate();

                $account = ChartOfAccount::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'account_code' => $this->normalizeCode($data['code']),
                    ],
                    [
                        'account_name' => trim((string) $data['name']),
                        'account_type' => $this->inferAccountType($data['code']),
                        'is_active' => $this->toBoolean($data['is_active'] ?? null, true),
                    ]
                );

                if ($account->wasRecentlyCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if (! empty($errors)) {
            DB::rollBack();

            $firstErrors = collect($errors)
                ->take(5)
                ->map(fn (array $error) => 'Baris '.$error['row'].': '.$error['message'])
                ->join(' | ');

            throw ValidationException::withMessages([
                'file' => 'Import COA gagal. '.$firstErrors,
            ]);
        }

        DB::commit();

        if (($inserted + $updated) === 0) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data COA yang diproses dari file import.',
            ]);
        }

        return back()->with('success', "Import COA berhasil. {$inserted} COA baru, {$updated} COA diperbarui, {$skipped} baris kosong dilewati.");
    }

    private function companyId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 1);
    }

    private function normalizeCode(string $code): string
    {
        return trim($code);
    }

    private function inferAccountType(string $code): string
    {
        $firstDigit = substr(trim($code), 0, 1);

        return match ($firstDigit) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4' => 'revenue',
            '5', '6' => 'expense',
            '7' => 'other_income',
            '8', '9' => 'other_expense',
            default => 'asset',
        };
    }

    private function parseImportRows(UploadedFile $file): Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'xlsx' => $this->parseXlsxRows($file->getRealPath()),
            default => $this->parseCsvRows($file->getRealPath()),
        };
    }

    private function parseCsvRows(string $path): Collection
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return collect();
        }

        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if (! $header) {
                $header = $this->normalizeHeaders($data);
                continue;
            }

            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($data) {
                return [$key => trim((string) ($data[$index] ?? ''))];
            })->all();
        }

        fclose($handle);

        return collect($rows);
    }

    private function parseXlsxRows(string $path): Collection
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return collect();
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared !== false && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                        continue;
                    }

                    $parts = [];
                    foreach ($si->r ?? [] as $run) {
                        if (isset($run->t)) {
                            $parts[] = (string) $run->t;
                        }
                    }
                    $sharedStrings[] = implode('', $parts);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            return collect();
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || ! isset($xml->sheetData->row)) {
            return collect();
        }

        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $cellReference = (string) ($cell['r'] ?? '');
                preg_match('/^[A-Z]+/', $cellReference, $matches);
                $columnIndex = $this->columnLettersToIndex($matches[0] ?? '');
                $type = (string) ($cell['t'] ?? '');
                $value = '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) ($cell->v ?? 0)] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                if ($columnIndex >= 0) {
                    $line[$columnIndex] = trim($value);
                }
            }
            ksort($line);
            $table[] = $line;
        }

        if (count($table) < 2) {
            return collect();
        }

        $header = $this->normalizeHeaders($table[0]);
        $rows = [];

        foreach (array_slice($table, 1) as $line) {
            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($line) {
                return [$key => trim((string) ($line[$index] ?? ''))];
            })->all();
        }

        return collect($rows);
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = strtolower(trim((string) $header));
            $header = str_replace([' ', '-', '.'], '_', $header);

            return preg_replace('/_+/', '_', $header) ?: '';
        }, $headers);
    }

    private function columnLettersToIndex(string $letters): int
    {
        if ($letters === '') {
            return -1;
        }

        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function isRowEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function hasRequiredHeaders(array $row, array $requiredHeaders): bool
    {
        $headers = array_keys($row);

        foreach ($requiredHeaders as $header) {
            if (! in_array($header, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    private function toBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'aktif', 'active'], true);
    }
}
