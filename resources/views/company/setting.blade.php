<x-layouts.app title="Pengaturan Company">
    <x-page-header title="Pengaturan Company" subtitle="Identitas perusahaan dan kebijakan operasional." />

    <form method="POST" action="{{ route('settings.company.update') }}" class="max-w-3xl space-y-4">
        @csrf
        @method('PUT')

        <div class="card card-pad space-y-4">
            <p class="section-title">Identitas</p>

            <div class="form-grid">
                <div>
                    <label class="label" for="name">Nama Company</label>
                    <input id="name" name="name" class="input" value="{{ old('name', $company->name) }}" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="legal_name">Nama Legal</label>
                    <input id="legal_name" name="legal_name" class="input" value="{{ old('legal_name', $company->legal_name) }}">
                </div>

                <div>
                    <label class="label" for="tax_number">NPWP</label>
                    <input id="tax_number" name="tax_number" class="input" value="{{ old('tax_number', $company->tax_number) }}">
                </div>

                <div>
                    <label class="label" for="phone">Telepon</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $company->phone) }}">
                </div>

                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $company->email) }}">
                </div>

                <div>
                    <label class="label" for="city">Kota</label>
                    <input id="city" name="city" class="input" value="{{ old('city', $company->city) }}">
                </div>

                <div>
                    <label class="label" for="province">Provinsi</label>
                    <input id="province" name="province" class="input" value="{{ old('province', $company->province) }}">
                </div>

                <div>
                    <label class="label" for="fiscal_year_start">Awal Tahun Buku</label>
                    <input id="fiscal_year_start" name="fiscal_year_start" type="date" class="input"
                           value="{{ old('fiscal_year_start', $company->fiscal_year_start?->toDateString()) }}">
                </div>
            </div>

            <div>
                <label class="label" for="address">Alamat</label>
                <textarea id="address" name="address" rows="2" class="input">{{ old('address', $company->address) }}</textarea>
            </div>
        </div>

        <div class="card card-pad space-y-4">
            <p class="section-title">Kebijakan Operasional</p>

            <div class="form-grid">
                <div>
                    <label class="label" for="costing_method">Metode Penilaian Persediaan</label>
                    <select id="costing_method" name="costing_method" class="input">
                        @foreach (\App\Modules\Core\Enums\CostingMethod::cases() as $method)
                            <option value="{{ $method->value }}"
                                @selected(old('costing_method', $company->costing_method?->value) === $method->value)>
                                {{ $method->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="credit_mode">Perlakuan Credit Limit Terlampaui</label>
                    <select id="credit_mode" name="credit_mode" class="input">
                        @foreach (['block' => 'Blokir order', 'warn' => 'Beri peringatan', 'approval' => 'Butuh approval'] as $value => $label)
                            <option value="{{ $value }}"
                                @selected(old('credit_mode', $company->setting('credit_mode', 'approval')) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="min_margin_percent">Margin Minimum (%)</label>
                    <input id="min_margin_percent" name="min_margin_percent" type="number" step="0.01" class="input"
                           value="{{ old('min_margin_percent', $company->setting('min_margin_percent', 5)) }}">
                </div>

                <div>
                    <label class="label" for="near_expiry_days">Ambang Mendekati Expiry (hari)</label>
                    <input id="near_expiry_days" name="near_expiry_days" type="number" class="input"
                           value="{{ old('near_expiry_days', $company->setting('near_expiry_days', 60)) }}">
                </div>
            </div>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="allow_negative_stock" value="1" class="mt-0.5 rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('allow_negative_stock', $company->allow_negative_stock))>
                <span>
                    Izinkan stok negatif
                    <span class="text-muted block text-xs">
                        Default tidak diizinkan. Jika diaktifkan, setiap kejadian stok negatif tetap dicatat di audit
                        dan wajib direkonsiliasi.
                    </span>
                </span>
            </label>
        </div>

        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
    </form>
</x-layouts.app>
