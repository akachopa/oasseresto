<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder as QueryContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;

/**
 * Adapter DataTables server-side (PLAN 49). Semua controller *_data memakai
 * kelas ini supaya perilaku paging, sorting, search, dan filter seragam.
 */
class ServerTable
{
    /** @var array<int, string> */
    private array $searchable = [];

    /** @var array<int, string> */
    private array $orderable = [];

    /** @var array<string, Closure> */
    private array $filters = [];

    private ?Closure $transformer = null;

    private function __construct(private readonly EloquentBuilder|QueryContract $query) {}

    public static function of(EloquentBuilder|QueryContract $query): self
    {
        return new self($query);
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * Urutan kolom harus sama dengan definisi data-columns di Blade, sudah
     * termasuk kolom No di indeks 0 dan Aksi di indeks terakhir.
     *
     * @param  array<int, string>  $columns
     */
    public function orderable(array $columns): self
    {
        $this->orderable = $columns;

        return $this;
    }

    public function filter(string $key, Closure $callback): self
    {
        $this->filters[$key] = $callback;

        return $this;
    }

    public function transform(Closure $callback): self
    {
        $this->transformer = $callback;

        return $this;
    }

    public function make(Request $request): array
    {
        $total = $this->countRows();

        $this->applyFilters($request);
        $this->applySearch($request);

        $filtered = $this->countRows();

        $this->applyOrder($request);

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);

        $rows = $this->query
            ->when($length > 0, fn ($q) => $q->skip($start)->take($length))
            ->get();

        $data = $this->transformer
            ? $rows->map(fn ($row) => ($this->transformer)($row))->values()->all()
            : $rows->all();

        return [
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ];
    }

    /**
     * Query builder mentah (mis. hasil fromSub) tidak punya toBase(), jadi
     * penghitungan total harus mengenali kedua jenis builder.
     */
    private function countRows(): int
    {
        $query = clone $this->query;

        return ($query instanceof EloquentBuilder ? $query->toBase() : $query)->getCountForPagination();
    }

    private function applyFilters(Request $request): void
    {
        $filters = (array) $request->input('filters', []);

        foreach ($this->filters as $key => $callback) {
            $value = $filters[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $callback($this->query, $value);
        }
    }

    private function applySearch(Request $request): void
    {
        $term = trim((string) $request->input('search.value', ''));

        if ($term === '' || $this->searchable === []) {
            return;
        }

        $this->query->where(function ($query) use ($term): void {
            foreach ($this->searchable as $column) {
                $query->orWhere($column, 'ilike', "%{$term}%");
            }
        });
    }

    private function applyOrder(Request $request): void
    {
        $orders = (array) $request->input('order', []);
        $applied = false;

        foreach ($orders as $order) {
            $index = (int) ($order['column'] ?? -1);
            $column = $this->orderable[$index] ?? null;

            if ($column === null) {
                continue;
            }

            $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $this->query->orderBy($column, $direction);
            $applied = true;
        }

        if (! $applied && $this->orderable !== []) {
            $this->query->orderByDesc($this->orderable[1] ?? $this->orderable[0]);
        }
    }
}
