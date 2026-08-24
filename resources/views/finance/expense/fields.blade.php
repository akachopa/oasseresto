@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <label class="label" for="expense_category_id">Kategori</label>
        <select id="expense_category_id" name="expense_category_id" class="input" required>
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                    @selected((int) old('expense_category_id', $expense?->expense_category_id) === (int) $category->id)>
                    {{ $category->name }}
                    @if ($category->requires_approval)
                        (approval di atas {{ Money::rupiah($category->approval_threshold) }})
                    @endif
                </option>
            @endforeach
        </select>
        @error('expense_category_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="expense_date">Tanggal</label>
        <input id="expense_date" name="expense_date" type="date" class="input" required
               value="{{ old('expense_date', $expense?->expense_date?->toDateString() ?? now()->toDateString()) }}">
        @error('expense_date') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="cash_account_id">Dibayar dari</label>
        <select id="cash_account_id" name="cash_account_id" class="input">
            <option value="">Tentukan saat pembayaran</option>
            @foreach ($accounts as $id => $name)
                <option value="{{ $id }}" @selected((int) old('cash_account_id', $expense?->cash_account_id) === (int) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label" for="amount">Nilai</label>
        <input id="amount" name="amount" type="number" step="0.01" min="0" class="input text-right" required
               value="{{ old('amount', $expense?->amount) }}">
        @error('amount') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="tax_amount">Pajak</label>
        <input id="tax_amount" name="tax_amount" type="number" step="0.01" min="0" class="input text-right"
               value="{{ old('tax_amount', $expense?->tax_amount ?? 0) }}">
    </div>

    <div>
        <label class="label" for="payee">Penerima</label>
        <input id="payee" name="payee" class="input" value="{{ old('payee', $expense?->payee) }}"
               placeholder="Nama penerima atau vendor">
    </div>

    <div>
        <label class="label" for="cost_center_id">Cost Center</label>
        <select id="cost_center_id" name="cost_center_id" class="input">
            <option value="">Tidak ditentukan</option>
            @foreach ($costCenters as $id => $name)
                <option value="{{ $id }}" @selected((int) old('cost_center_id', $expense?->cost_center_id) === (int) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label" for="supplier_id">Supplier</label>
        <select id="supplier_id" name="supplier_id" class="input">
            <option value="">Tidak ada</option>
            @foreach ($suppliers as $id => $name)
                <option value="{{ $id }}" @selected((int) old('supplier_id', $expense?->supplier_id) === (int) $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label" for="reference">Referensi</label>
        <input id="reference" name="reference" class="input" value="{{ old('reference', $expense?->reference) }}"
               placeholder="Nomor kwitansi atau nota">
    </div>

    <div class="sm:col-span-3">
        <label class="label" for="description">Keterangan</label>
        <input id="description" name="description" class="input"
               value="{{ old('description', $expense?->description) }}">
    </div>
</div>
