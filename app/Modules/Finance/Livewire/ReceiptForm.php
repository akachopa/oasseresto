<?php

declare(strict_types=1);

namespace App\Modules\Finance\Livewire;

use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Services\PaymentAllocationService;
use App\Modules\Finance\Services\ReceiptService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

class ReceiptForm extends Component
{
    public ?int $receiptId = null;

    public array $form = [
        'customer_id' => null,
        'cash_account_id' => null,
        'receipt_date' => '',
        'method' => 'transfer',
        'reference' => '',
        'amount' => 0,
        'note' => '',
    ];

    /** @var array<int, array{target_id: int, document_number: string, due_date: string, outstanding: float, amount: float, discount_amount: float}> */
    public array $lines = [];

    public function mount(?Receipt $receipt = null, ?int $customerId = null): void
    {
        if ($receipt?->exists) {
            $this->receiptId = $receipt->getKey();
            $this->form = [
                'customer_id' => $receipt->customer_id,
                'cash_account_id' => $receipt->cash_account_id,
                'receipt_date' => $receipt->receipt_date->toDateString(),
                'method' => $receipt->method,
                'reference' => $receipt->reference,
                'amount' => $receipt->amount,
                'note' => $receipt->note,
            ];

            $this->loadOpenDocuments($receipt->allocations->keyBy('target_id'));

            return;
        }

        $this->form['receipt_date'] = now()->toDateString();
        $this->form['cash_account_id'] = CashAccount::active()->orderByDesc('is_default')->value('id');
        $this->form['customer_id'] = $customerId;

        if ($customerId !== null) {
            $this->loadOpenDocuments();
        }
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'customer_id') {
            $this->loadOpenDocuments();
        }
    }

    /**
     * Isi otomatis alokasi dari tagihan tertua, termasuk usulan diskon
     * pelunasan dini bila memenuhi syarat.
     */
    public function autoAllocate(): void
    {
        if (! $this->form['customer_id'] || (float) $this->form['amount'] <= 0) {
            $this->addError('lines', 'Pilih customer dan isi nilai penerimaan lebih dulu.');

            return;
        }

        $customer = Customer::find($this->form['customer_id']);

        if ($customer === null) {
            return;
        }

        $suggested = collect(
            app(PaymentAllocationService::class)->suggestForCustomer($customer, (float) $this->form['amount'])
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
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'form.receipt_date' => ['required', 'date'],
            'form.method' => ['required', 'in:'.implode(',', array_keys(Receipt::methods()))],
            'form.reference' => ['nullable', 'string', 'max:100'],
            'form.amount' => ['required', 'numeric', 'gt:0'],
            'form.note' => ['nullable', 'string'],
            'lines' => ['array'],
            'lines.*.target_id' => ['required', 'integer', 'exists:receivables,id'],
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
            $this->addError('lines', 'Alokasikan penerimaan ke minimal satu tagihan sebelum diposting.');

            return;
        }

        try {
            $receipts = app(ReceiptService::class);

            $receipt = $receipts->save(
                $this->receiptId ? Receipt::findOrFail($this->receiptId) : null,
                array_merge($validated['form'], ['collected_by' => Auth::id()]),
                $allocations,
            );

            if ($andPost) {
                $receipts->post($receipt);
            }
        } catch (RuntimeException $exception) {
            $this->addError('lines', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Penerimaan diposting; kas bertambah dan piutang berkurang.'
            : 'Penerimaan disimpan sebagai draft.');

        $this->redirectRoute('finance.receipts.detail', $receipt, navigate: true);
    }

    public function render()
    {
        return view('finance.livewire.receipt-form', [
            'customers' => Customer::active()->orderBy('name')->get(),
            'accounts' => CashAccount::active()->orderBy('name')->get(),
            'methods' => Receipt::methods(),
            'allocatedTotal' => $this->allocatedTotal(),
            'unallocated' => round((float) $this->form['amount'] - $this->allocatedTotal(), 4),
        ]);
    }

    public function allocatedTotal(): float
    {
        return round(collect($this->lines)->sum(fn (array $line) => (float) ($line['amount'] ?? 0)), 4);
    }

    /**
     * Tampilkan seluruh tagihan terbuka customer beserta nilai alokasi yang
     * sudah tersimpan, sehingga kasir bisa menyesuaikan per tagihan.
     *
     * @param  Collection<int, PaymentAllocation>|null  $existing
     */
    private function loadOpenDocuments($existing = null): void
    {
        $customer = $this->form['customer_id'] ? Customer::find($this->form['customer_id']) : null;

        if ($customer === null) {
            $this->lines = [];

            return;
        }

        $receivables = app(PaymentAllocationService::class)->openReceivables($customer);

        /*
         * Tagihan yang sudah tertutup oleh alokasi dokumen ini tetap harus
         * tampil, kalau tidak barisnya hilang begitu penerimaan disimpan.
         */
        if ($existing !== null) {
            $missing = Receivable::whereIn('id', $existing->keys())
                ->whereNotIn('id', $receivables->modelKeys())
                ->get();

            $receivables = $receivables->concat($missing)->sortBy('due_date')->values();
        }

        $this->lines = $receivables
            ->map(function ($receivable) use ($existing) {
                $saved = $existing?->get($receivable->getKey());

                return [
                    'target_id' => (int) $receivable->getKey(),
                    'document_number' => (string) $receivable->document_number,
                    'due_date' => $receivable->due_date->format('d/m/Y'),
                    'days_overdue' => $receivable->daysOverdue(),
                    // Alokasi tersimpan sudah mengurangi sisa, jadi ditambahkan
                    // kembali agar baris ini bisa diedit ulang.
                    'outstanding' => round(
                        $receivable->outstanding_amount + ($saved?->settledAmount() ?? 0),
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
