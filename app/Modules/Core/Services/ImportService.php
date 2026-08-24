<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\ImportJob;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Models\Unit;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Import master data dari CSV/XLSX lewat antrian (PLAN 55). Baris error
 * tidak membatalkan seluruh berkas; hasilnya dicatat di import_jobs.
 */
class ImportService
{
    public function process(ImportJob $job): void
    {
        $job->status = 'processing';
        $job->started_at = now();
        $job->save();

        try {
            $path = Storage::path($job->path);
            $rows = $this->read($path, (string) $job->original_filename);

            $job->total_rows = count($rows);
            $job->save();

            $result = match ($job->type) {
                'products' => $this->importProducts($rows),
                'customers' => $this->importCustomers($rows),
                'suppliers' => $this->importSuppliers($rows),
                default => throw new \RuntimeException('Jenis import tidak dikenali.'),
            };

            $job->fill($result + [
                'status' => 'completed',
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $job->status = 'failed';
            $job->errors = array_merge($job->errors ?? [], [['row' => 0, 'message' => $e->getMessage()]]);
            $job->finished_at = now();
            $job->save();
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{created_count: int, updated_count: int, error_count: int, errors: array<int, array{row: int, message: string}>}
     */
    private function importProducts(array $rows): array
    {
        $created = $updated = 0;
        $errors = [];
        $pcs = Unit::query()->where('code', 'PCS')->first();

        foreach ($rows as $index => $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? $row['nama'] ?? ''));

            if ($sku === '' || $name === '') {
                $errors[] = ['row' => $index + 2, 'message' => 'SKU dan nama wajib diisi.'];

                continue;
            }

            try {
                $unit = Unit::query()->where('code', strtoupper((string) ($row['unit'] ?? 'PCS')))->first() ?? $pcs;

                if ($unit === null) {
                    $errors[] = ['row' => $index + 2, 'message' => 'Satuan tidak ditemukan.'];

                    continue;
                }

                $categoryId = $this->optionalCategory((string) ($row['category'] ?? $row['kategori'] ?? ''));
                $brandId = $this->optionalBrand((string) ($row['brand'] ?? ''));

                $product = Product::query()->where('sku', $sku)->first();
                $payload = [
                    'name' => $name,
                    'barcode' => ($row['barcode'] ?? '') !== '' ? (string) $row['barcode'] : null,
                    'product_category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'base_unit_id' => $unit->id,
                    'base_price' => (float) str_replace(['.', ','], ['', '.'], (string) ($row['base_price'] ?? $row['harga'] ?? 0)),
                    'reorder_point' => (float) ($row['reorder_point'] ?? 0),
                    'is_stocked' => true,
                    'is_active' => true,
                ];

                if ($product === null) {
                    $product = Product::create(['sku' => $sku] + $payload);
                    $product->units()->create([
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'is_base' => true,
                    ]);
                    $created++;
                } else {
                    $product->update($payload);
                    $updated++;
                }
            } catch (Throwable $e) {
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
            }
        }

        return [
            'created_count' => $created,
            'updated_count' => $updated,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{created_count: int, updated_count: int, error_count: int, errors: array<int, array{row: int, message: string}>}
     */
    private function importCustomers(array $rows): array
    {
        $created = $updated = 0;
        $errors = [];
        $defaultGroup = CustomerGroup::query()->orderBy('id')->value('id');

        foreach ($rows as $index => $row) {
            $code = trim((string) ($row['code'] ?? $row['kode'] ?? ''));
            $name = trim((string) ($row['name'] ?? $row['nama'] ?? ''));

            if ($code === '' || $name === '') {
                $errors[] = ['row' => $index + 2, 'message' => 'Kode dan nama wajib diisi.'];

                continue;
            }

            try {
                $payload = [
                    'name' => $name,
                    'phone' => ($row['phone'] ?? $row['telepon'] ?? '') ?: null,
                    'city' => ($row['city'] ?? $row['kota'] ?? '') ?: null,
                    'credit_limit' => (float) ($row['credit_limit'] ?? $row['limit'] ?? 0),
                    'payment_term' => PaymentTermType::tryFrom((string) ($row['payment_term'] ?? ''))?->value
                        ?? PaymentTermType::Net14->value,
                    'customer_group_id' => $defaultGroup,
                    'is_active' => true,
                ];

                $customer = Customer::query()->where('code', $code)->first();

                if ($customer === null) {
                    Customer::create(['code' => $code] + $payload);
                    $created++;
                } else {
                    $customer->update($payload);
                    $updated++;
                }
            } catch (Throwable $e) {
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
            }
        }

        return [
            'created_count' => $created,
            'updated_count' => $updated,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{created_count: int, updated_count: int, error_count: int, errors: array<int, array{row: int, message: string}>}
     */
    private function importSuppliers(array $rows): array
    {
        $created = $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $code = trim((string) ($row['code'] ?? $row['kode'] ?? ''));
            $name = trim((string) ($row['name'] ?? $row['nama'] ?? ''));

            if ($code === '' || $name === '') {
                $errors[] = ['row' => $index + 2, 'message' => 'Kode dan nama wajib diisi.'];

                continue;
            }

            try {
                $payload = [
                    'name' => $name,
                    'phone' => ($row['phone'] ?? $row['telepon'] ?? '') ?: null,
                    'city' => ($row['city'] ?? $row['kota'] ?? '') ?: null,
                    'lead_time_days' => (int) ($row['lead_time_days'] ?? 3),
                    'is_active' => true,
                ];

                $supplier = Supplier::query()->where('code', $code)->first();

                if ($supplier === null) {
                    Supplier::create(['code' => $code] + $payload);
                    $created++;
                } else {
                    $supplier->update($payload);
                    $updated++;
                }
            } catch (Throwable $e) {
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
            }
        }

        return [
            'created_count' => $created,
            'updated_count' => $updated,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function read(string $path, string $filename): array
    {
        $reader = $this->readerFor($filename);
        $reader->open($path);

        $header = [];
        $rows = [];
        $first = true;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(
                    fn ($value) => is_scalar($value) ? trim((string) $value) : '',
                    $row->toArray(),
                );

                if ($first) {
                    $header = array_map(fn (string $col) => strtolower(str_replace(' ', '_', $col)), $values);
                    $first = false;

                    continue;
                }

                if ($row->isEmpty()) {
                    continue;
                }

                $assoc = [];

                foreach ($header as $idx => $key) {
                    if ($key === '') {
                        continue;
                    }

                    $assoc[$key] = $values[$idx] ?? '';
                }

                $rows[] = $assoc;
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    private function readerFor(string $filename): ReaderInterface
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $ext === 'csv' ? new CsvReader : new XlsxReader;
    }

    private function optionalCategory(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $code = strtoupper(str()->slug($name, '-'));

        return ProductCategory::query()->firstOrCreate(
            ['code' => substr($code, 0, 30)],
            ['name' => $name, 'is_active' => true],
        )->id;
    }

    private function optionalBrand(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $code = strtoupper(str()->slug($name, '-'));

        return Brand::query()->firstOrCreate(
            ['code' => substr($code, 0, 30)],
            ['name' => $name, 'is_active' => true],
        )->id;
    }
}
