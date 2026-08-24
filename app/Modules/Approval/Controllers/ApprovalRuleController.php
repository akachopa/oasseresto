<?php

declare(strict_types=1);

namespace App\Modules\Approval\Controllers;

use App\Models\User;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Auth\Support\RoleTemplate;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApprovalRuleController
{
    /**
     * @return array<string, string>
     */
    public static function documentTypes(): array
    {
        return [
            'purchase_request' => 'Purchase Request',
            'purchase_order' => 'Purchase Order',
            'sales_order' => 'Sales Order',
            'expense' => 'Biaya',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function triggers(): array
    {
        return [
            'always' => 'Selalu (berdasarkan nilai)',
            'credit_limit' => 'Melebihi credit limit',
            'below_margin' => 'Di bawah margin minimum',
        ];
    }

    public function index(): View
    {
        return view('approval.rule.index');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(
            ServerTable::of(ApprovalRule::query()->leftJoin('branches', 'branches.id', '=', 'approval_rules.branch_id')
                ->select(['approval_rules.*', 'branches.name as branch_name']))
                ->searchable(['approval_rules.name', 'approval_rules.document_type', 'approval_rules.approver_role'])
                ->orderable([
                    null, 'approval_rules.document_type', 'approval_rules.name',
                    'approval_rules.sequence', 'approval_rules.min_amount', null, null,
                ])
                ->filter('document_type', fn ($q, $value) => $q->where('approval_rules.document_type', $value))
                ->filter('is_active', fn ($q, $value) => $q->where('approval_rules.is_active', $value === '1'))
                ->transform(fn (ApprovalRule $rule) => [
                    'document' => e(self::documentTypes()[$rule->document_type] ?? $rule->document_type),
                    'name' => e($rule->name),
                    'sequence' => $rule->sequence,
                    'amount' => Money::rupiah($rule->min_amount)
                        .($rule->max_amount !== null ? ' – '.Money::rupiah($rule->max_amount) : '+'),
                    'approver' => e($rule->approverLabel()),
                    'status' => $rule->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'edit' => route('approval.rules.edit', $rule),
                        'delete' => route('approval.rules.hapus', $rule),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('approval.rule.form', $this->formData([
            'rule' => new ApprovalRule([
                'sequence' => 1,
                'min_amount' => 0,
                'trigger' => 'always',
                'is_active' => true,
            ]),
        ]));
    }

    public function edit(ApprovalRule $rule): View
    {
        return view('approval.rule.form', $this->formData(['rule' => $rule]));
    }

    public function store(Request $request): RedirectResponse
    {
        ApprovalRule::create($this->validated($request));

        activity()->event('created')->log('Aturan approval ditambahkan');

        return redirect()->route('approval.rules.index')->with('status', 'Aturan approval berhasil ditambahkan.');
    }

    public function update(Request $request, ApprovalRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request));

        activity()->performedOn($rule)->event('updated')->log('Aturan approval diubah');

        return redirect()->route('approval.rules.index')->with('status', 'Aturan approval berhasil diperbarui.');
    }

    public function hapus(ApprovalRule $rule): RedirectResponse
    {
        $rule->delete();

        activity()->event('deleted')->log('Aturan approval dihapus');

        return back()->with('status', 'Aturan approval berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(self::documentTypes()))],
            'name' => ['required', 'string', 'max:120'],
            'sequence' => ['required', 'integer', 'min:1', 'max:20'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount'],
            'trigger' => ['required', Rule::in(array_keys(self::triggers()))],
            'approver_role' => ['nullable', 'string', 'max:60'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'note' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['max_amount'] = isset($data['max_amount']) && $data['max_amount'] !== null && $data['max_amount'] !== ''
            ? (float) $data['max_amount']
            : null;
        $data['approver_role'] = ($data['approver_role'] ?? null) ?: null;
        $data['approver_user_id'] = ($data['approver_user_id'] ?? null) ?: null;
        $data['branch_id'] = ($data['branch_id'] ?? null) ?: null;
        $data['note'] = ($data['note'] ?? null) ?: null;

        if ($data['approver_role'] === null && $data['approver_user_id'] === null) {
            throw ValidationException::withMessages([
                'approver_role' => 'Pilih peran atau user penyetuju.',
            ]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formData(array $extra): array
    {
        return $extra + [
            'documentTypes' => self::documentTypes(),
            'triggers' => self::triggers(),
            'roles' => collect(array_keys(RoleTemplate::all()))->mapWithKeys(fn (string $name) => [$name => $name]),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
