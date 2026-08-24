<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Umur piutang dan hutang (PLAN 33 dan 35). Bucket dibaca dari konfigurasi
 * company supaya laporan mengikuti kebijakan kredit yang dipakai, bukan angka
 * yang dipatri di kode.
 */
class AgingService
{
    /**
     * @return array<int, array{label: string, from: int, to: ?int}>
     */
    public function buckets(): array
    {
        $limits = array_values(array_filter(
            array_map('intval', (array) config('oasse.credit.aging_buckets', [30, 60, 90])),
            fn (int $value) => $value > 0,
        ));

        sort($limits);

        $buckets = [['label' => 'Belum jatuh tempo', 'from' => -PHP_INT_MAX, 'to' => 0]];
        $from = 1;

        foreach ($limits as $limit) {
            $buckets[] = ['label' => $from.'-'.$limit.' hari', 'from' => $from, 'to' => $limit];
            $from = $limit + 1;
        }

        $buckets[] = ['label' => '> '.end($limits).' hari', 'from' => $from, 'to' => null];

        return $buckets;
    }

    /**
     * Ringkasan piutang per customer dengan kolom per bucket umur.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function receivables(?Carbon $asOf = null, ?int $customerId = null): Collection
    {
        $asOf ??= now();

        $rows = Receivable::query()
            ->with('customer')
            ->outstanding()
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->get();

        return $this->group(
            $rows,
            $asOf,
            fn (Receivable $row) => (int) $row->customer_id,
            fn (Receivable $row) => $row->customer?->name ?? '-',
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function payables(?Carbon $asOf = null, ?int $supplierId = null): Collection
    {
        $asOf ??= now();

        $rows = Payable::query()
            ->with('supplier')
            ->outstanding()
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->get();

        return $this->group(
            $rows,
            $asOf,
            fn (Payable $row) => (int) $row->supplier_id,
            fn (Payable $row) => $row->supplier?->name ?? '-',
        );
    }

    /**
     * Total per bucket untuk kartu ringkasan di atas tabel.
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return array<string, float>
     */
    public function totals(Collection $groups): array
    {
        $totals = [];

        foreach ($this->buckets() as $bucket) {
            $totals[$bucket['label']] = round(
                (float) $groups->sum(fn (array $group) => $group['buckets'][$bucket['label']] ?? 0),
                4,
            );
        }

        $totals['total'] = round((float) $groups->sum('total'), 4);

        return $totals;
    }

    public function bucketFor(int $daysOverdue): string
    {
        foreach ($this->buckets() as $bucket) {
            $withinLower = $daysOverdue >= $bucket['from'];
            $withinUpper = $bucket['to'] === null || $daysOverdue <= $bucket['to'];

            if ($withinLower && $withinUpper) {
                return $bucket['label'];
            }
        }

        return 'Belum jatuh tempo';
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function group(Collection $rows, Carbon $asOf, callable $keyBy, callable $nameOf): Collection
    {
        $labels = array_column($this->buckets(), 'label');

        return $rows
            ->groupBy($keyBy)
            ->map(function (Collection $group) use ($labels, $asOf, $nameOf): array {
                $buckets = array_fill_keys($labels, 0.0);
                $oldest = 0;

                foreach ($group as $row) {
                    $days = (int) ($row->due_date->lt($asOf) ? $row->due_date->diffInDays($asOf) : 0);
                    $label = $this->bucketFor($days);

                    $buckets[$label] = round($buckets[$label] + (float) $row->outstanding_amount, 4);
                    $oldest = max($oldest, $days);
                }

                return [
                    'name' => $nameOf($group->first()),
                    'documents' => $group->count(),
                    'oldest_days' => $oldest,
                    'buckets' => $buckets,
                    'total' => round((float) $group->sum('outstanding_amount'), 4),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
