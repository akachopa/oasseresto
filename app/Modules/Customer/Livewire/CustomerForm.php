<?php

declare(strict_types=1);

namespace App\Modules\Customer\Livewire;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAddress;
use App\Modules\Customer\Models\CustomerCreditProfile;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Models\PriceLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Customer punya daftar alamat kirim dinamis, jadi formnya Livewire.
 * Credit limit hanya bisa diubah oleh user dengan customer.credit.manage
 * sesuai pemisahan wewenang di PLAN 17.
 */
class CustomerForm extends Component
{
    public ?int $customerId = null;

    public array $form = [
        'code' => '',
        'name' => '',
        'type' => 'retail',
        'customer_group_id' => null,
        'price_level_id' => null,
        'branch_id' => null,
        'salesman_id' => null,
        'tax_code_id' => null,
        'pic_name' => '',
        'phone' => '',
        'whatsapp' => '',
        'email' => '',
        'address' => '',
        'city' => '',
        'sales_area' => '',
        'tax_number' => '',
        'payment_term' => 'cash',
        'credit_limit' => 0,
        'preferred_delivery' => null,
        'note' => '',
        'is_active' => true,
    ];

    /** @var array<int, array{id: ?int, label: string, pic_name: ?string, phone: ?string, address: string, city: ?string, is_default: bool}> */
    public array $addresses = [];

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customerId = $customer->getKey();
            $this->form = array_merge($this->form, $customer->only(array_keys($this->form)));
            $this->form['payment_term'] = $customer->payment_term->value;

            $this->addresses = $customer->addresses
                ->map(fn (CustomerAddress $address) => [
                    'id' => $address->getKey(),
                    'label' => $address->label,
                    'pic_name' => $address->pic_name,
                    'phone' => $address->phone,
                    'address' => $address->address,
                    'city' => $address->city,
                    'is_default' => $address->is_default,
                ])->all();

            return;
        }

        $this->form['code'] = $this->nextCode();
        $this->form['branch_id'] = app(ScopeManager::class)->branchId();
    }

    /**
     * Mengubah grup langsung menurunkan default level harga, termin, dan
     * credit limit supaya operator tidak perlu mengingat kebijakan grup.
     */
    public function updatedFormCustomerGroupId(mixed $value): void
    {
        $group = $value ? CustomerGroup::find($value) : null;

        if ($group === null) {
            return;
        }

        $this->form['price_level_id'] = $group->price_level_id;
        $this->form['payment_term'] = $group->default_payment_term->value;

        if ($this->canManageCredit()) {
            $this->form['credit_limit'] = $group->default_credit_limit;
        }
    }

    public function addAddressRow(): void
    {
        $this->addresses[] = [
            'id' => null,
            'label' => '',
            'pic_name' => null,
            'phone' => null,
            'address' => '',
            'city' => $this->form['city'] ?: null,
            'is_default' => $this->addresses === [],
        ];
    }

    public function removeAddressRow(int $index): void
    {
        unset($this->addresses[$index]);
        $this->addresses = array_values($this->addresses);
    }

    public function markDefaultAddress(int $index): void
    {
        foreach (array_keys($this->addresses) as $key) {
            $this->addresses[$key]['is_default'] = $key === $index;
        }
    }

    public function canManageCredit(): bool
    {
        return (bool) auth()->user()?->can('customer.credit.manage');
    }

    public function save(): void
    {
        $companyId = (int) auth()->user()->company_id;

        $validated = $this->validate([
            'form.code' => [
                'required', 'string', 'max:30',
                Rule::unique('customers', 'code')->where('company_id', $companyId)->ignore($this->customerId),
            ],
            'form.name' => ['required', 'string', 'max:255'],
            'form.type' => ['required', 'in:retail,wholesale,project,institution'],
            'form.customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'form.price_level_id' => ['nullable', 'integer', 'exists:price_levels,id'],
            'form.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'form.salesman_id' => ['nullable', 'integer', 'exists:users,id'],
            'form.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'form.pic_name' => ['nullable', 'string', 'max:100'],
            'form.phone' => ['nullable', 'string', 'max:40'],
            'form.whatsapp' => ['nullable', 'string', 'max:40'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.address' => ['nullable', 'string'],
            'form.city' => ['nullable', 'string', 'max:100'],
            'form.sales_area' => ['nullable', 'string', 'max:100'],
            'form.tax_number' => ['nullable', 'string', 'max:40'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_column(PaymentTermType::cases(), 'value'))],
            'form.credit_limit' => ['required', 'numeric', 'min:0'],
            'form.preferred_delivery' => ['nullable', 'in:pickup,delivery,expedition'],
            'form.note' => ['nullable', 'string'],
            'addresses.*.label' => ['required', 'string', 'max:60'],
            'addresses.*.address' => ['required', 'string'],
            'addresses.*.pic_name' => ['nullable', 'string', 'max:100'],
            'addresses.*.phone' => ['nullable', 'string', 'max:40'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
        ], [
            'addresses.*.label.required' => 'Nama alamat wajib diisi.',
            'addresses.*.address.required' => 'Detail alamat wajib diisi.',
        ]);

        DB::transaction(function () use ($validated): void {
            $customer = $this->customerId
                ? Customer::findOrFail($this->customerId)
                : new Customer;

            $data = $validated['form'];

            // Credit limit adalah keputusan finance, bukan sales.
            if (! $this->canManageCredit()) {
                unset($data['credit_limit']);
            }

            $customer->fill($data);
            $customer->is_active = (bool) $this->form['is_active'];
            $customer->save();

            $this->syncAddresses($customer);

            CustomerCreditProfile::firstOrCreate(
                ['customer_id' => $customer->getKey()],
                ['company_id' => $customer->company_id],
            );
        });

        session()->flash('status', 'Customer berhasil disimpan.');
        $this->redirectRoute('customers.index', navigate: true);
    }

    public function render()
    {
        return view('customer.livewire.customer-form', [
            'groups' => CustomerGroup::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'priceLevels' => PriceLevel::where('is_active', true)->orderBy('sequence')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'salesmen' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_sales', true)->orderBy('code')->pluck('name', 'id'),
            'paymentTerms' => PaymentTermType::options(),
        ]);
    }

    private function syncAddresses(Customer $customer): void
    {
        $keep = [];

        foreach ($this->addresses as $row) {
            $address = $row['id']
                ? CustomerAddress::where('customer_id', $customer->getKey())->findOrFail($row['id'])
                : new CustomerAddress(['customer_id' => $customer->getKey()]);

            $address->fill([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->getKey(),
                'label' => $row['label'],
                'pic_name' => $row['pic_name'] ?: null,
                'phone' => $row['phone'] ?: null,
                'address' => $row['address'],
                'city' => $row['city'] ?: null,
                'is_default' => (bool) ($row['is_default'] ?? false),
            ])->save();

            $keep[] = $address->getKey();
        }

        CustomerAddress::where('customer_id', $customer->getKey())->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function nextCode(): string
    {
        $last = Customer::withTrashed()->where('code', 'like', 'CUS%')->orderByDesc('code')->value('code');
        $number = $last ? (int) substr($last, 3) : 0;

        return 'CUS'.str_pad((string) ($number + 1), 5, '0', STR_PAD_LEFT);
    }
}
