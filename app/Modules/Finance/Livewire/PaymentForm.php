<?php

declare(strict_types=1);

namespace App\Modules\Finance\Livewire;

use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Services\PaymentAllocationService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Collection;
use Livewire\Component;
use RuntimeException;

class PaymentForm extends Component
{
    public ?int $paymentId = null;

    public array $form = [
        'supplier_id' => null,
        'cash_account_id' => null,
        'payment_date' => '',
        'method' => 'transfer',
        'reference' => '',
        'amount' => 0,
        'note' => '',
    ];

    /** @var array<int, array{target_id: int, document_number: string, due_date: string, outstanding: float, amount: float, discount_amount: float}> */
    public array $lines = [];

    public function mount(?Payment $payment = null, ?int $supplierId = null): void
    {
        if ($payment?->exists) {
            $this->paymentId = $payment->getKey();
            $this->form = [
                'supplier_id' => $payment->supplier_id,
                'cash_account_id' => $payment->cash_account_id,
                'payment_date' => $payment->payment_date->toDateString(),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'note' => $payment->note,
            ];

            $this->loadOpenDocuments($payment->allocations->keyBy('target_id'));

            return;
        }

        $this->form['payment_date'] = now()->toDateString();
        $this->form['cash_account_id'] = CashAccount::active()->orderByDesc('is_default')->value('id');
        $this->form['supplier_id'] = $supplierId;

        if ($supplierId !== null) {
            $this->loadOpenDocuments();
        }
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'supplier_id') {
            $this->loadOpenDocuments();
        }
    }

    public function autoAllocate(): void
    {
        if (! $this->form['supplier_id'] || (float) $this->form['amount'] <= 0) {
            $this->addError('lines', 'Pilih supplier dan isi nilai pembayaran lebih dulu.');

            return;
        }

        $supplier = Supplier::find($this->form['supplier_id']);

        if ($supplier === null) {
            return;
        }

        $suggested = collect(
            app(PaymentAllocationService::class)->suggestForSupplier($supplier, (float) $this->form['amount'])
        )->keyBy('target_id');

        foreach ($this->lines as $index => $line) {
            $match = $suggested->get($line['target_id']);

            $this->lines[$index]['amount'] = $match ? (float) $match['amount'] : 0.0;
            $this->lines[$index]['discount_amount'] = $match ? (float) $match['discount_amount'] : 0.0;
        }
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'form.payment_date' => ['required', 'date'],
            'form.method' => ['required', 'in:'.implode(',', array_keys(Payment::methods()))],
            'form.reference' => ['nullable', 'string', 'max:100'],
            'form.amount' => ['required', 'numeric', 'gt:0'],
            'form.note' => ['nullable', 'string'],
            'lines' => ['array'],
            'lines.*.target_id' => ['required', 'integer', 'exists:payables,id'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $allocations = collect($validated['lines'] ?? [])
            ->filter(fn (array $line) => (float) ($line['amount'] ?? 0) > 0)
            ->map(fn (array $line) => [
                'target_id' => (int) $line['target_id'],
                'amount' => (float) $line['amount'],
                'discount_amount' => (float) ($line['discount_amount'] ?? 0),
            ])
            ->values()
            ->all();

        if ($andPost && $allocations === []) {
            $this->addError('lines', 'Alokasikan pembayaran ke minimal satu hutang sebelum diposting.');

            return;
        }

        try {
            $payments = app(PaymentService::class);

            $payment = $payments->save(
                $this->paymentId ? Payment::findOrFail($this->paymentId) : null,
                $validated['form'],
                $allocations,
            );

            if ($andPost) {
                $payments->post($payment);
            }
        } catch (RuntimeException $exception) {
            $this->addError('lines', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Pembayaran diposting; kas berkurang dan hutang menurun.'
            : 'Pembayaran disimpan sebagai draft.');

        $this->redirectRoute('finance.payments.detail', $payment, navigate: true);
    }

    public function render()
    {
        $account = $this->form['cash_account_id'] ? CashAccount::find($this->form['cash_account_id']) : null;

        return view('finance.livewire.payment-form', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'accounts' => CashAccount::active()->orderBy('name')->get(),
            'methods' => Payment::methods(),
            'allocatedTotal' => $this->allocatedTotal(),
            'unallocated' => round((float) $this->form['amount'] - $this->allocatedTotal(), 4),
            'availableBalance' => (float) ($account?->balance ?? 0),
        ]);
    }

    public function allocatedTotal(): float
    {
        return round(collect($this->lines)->sum(fn (array $line) => (float) ($line['amount'] ?? 0)), 4);
    }

    /**
     * @param  Collection<int, PaymentAllocation>|null  $existing
     */
    private function loadOpenDocuments($existing = null): void
    {
        $supplier = $this->form['supplier_id'] ? Supplier::find($this->form['supplier_id']) : null;

        if ($supplier === null) {
            $this->lines = [];

            return;
        }

        $payables = app(PaymentAllocationService::class)->openPayables($supplier);

        if ($existing !== null) {
            $missing = Payable::whereIn('id', $existing->keys())
                ->whereNotIn('id', $payables->modelKeys())
                ->get();

            $payables = $payables->concat($missing)->sortBy('due_date')->values();
        }

        $this->lines = $payables
            ->map(function (Payable $payable) use ($existing) {
                $saved = $existing?->get($payable->getKey());

                return [
                    'target_id' => (int) $payable->getKey(),
                    'document_number' => (string) $payable->document_number,
                    'due_date' => $payable->due_date->format('d/m/Y'),
                    'days_overdue' => $payable->daysOverdue(),
                    'outstanding' => round(
                        $payable->outstanding_amount + ($saved?->settledAmount() ?? 0),
                        4,
                    ),
                    'amount' => (float) ($saved?->amount ?? 0),
                    'discount_amount' => (float) ($saved?->discount_amount ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
