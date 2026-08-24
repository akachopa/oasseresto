<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Support\ServerTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Basis untuk master data sederhana (kategori, brand, satuan, grup customer).
 * Struktur method mengikuti pola index / data / hapus yang dipakai seluruh
 * modul, sehingga tabel dan tombol aksinya konsisten.
 */
abstract class SimpleCrudController
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function routeName(): string;

    abstract protected function viewPath(): string;

    abstract protected function title(): string;

    /** @return array<int, string> */
    abstract protected function searchable(): array;

    /** @return array<int, ?string> */
    abstract protected function orderable(): array;

    /** @return array<string, mixed> */
    abstract protected function rules(Request $request, ?Model $record): array;

    /** @return array<string, mixed> */
    abstract protected function row(Model $record): array;

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return ['is_active' => true];
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function booleans(Request $request): array
    {
        return ['is_active' => $request->boolean('is_active')];
    }

    protected function beforeDelete(Model $record): ?string
    {
        return null;
    }

    public function index(): View
    {
        return view($this->viewPath().'.index', ['title' => $this->title()]);
    }

    public function data(Request $request): JsonResponse
    {
        $model = $this->model();

        return response()->json(
            ServerTable::of($model::query())
                ->searchable($this->searchable())
                ->orderable($this->orderable())
                ->filter('is_active', fn ($q, $value) => $q->where('is_active', $value === '1'))
                ->transform(fn (Model $record) => $this->row($record) + [
                    'aksi' => view('components.row-actions', [
                        'edit' => route($this->routeName().'.edit', $record),
                        'delete' => route($this->routeName().'.hapus', $record),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        $model = $this->model();

        return view($this->viewPath().'.form', [
            'record' => new $model($this->defaults()),
            'title' => $this->title(),
            ...$this->formData(),
        ]);
    }

    public function edit(int $id): View
    {
        $model = $this->model();

        return view($this->viewPath().'.form', [
            'record' => $model::findOrFail($id),
            'title' => $this->title(),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $model = $this->model();
        $model::create($request->validate($this->rules($request, null)) + $this->booleans($request));

        return redirect()->route($this->routeName().'.index')
            ->with('status', $this->title().' berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $model = $this->model();
        $record = $model::findOrFail($id);
        $record->update($request->validate($this->rules($request, $record)) + $this->booleans($request));

        return redirect()->route($this->routeName().'.index')
            ->with('status', $this->title().' berhasil diperbarui.');
    }

    public function hapus(int $id): RedirectResponse
    {
        $model = $this->model();
        $record = $model::findOrFail($id);

        if ($reason = $this->beforeDelete($record)) {
            return back()->with('error', $reason);
        }

        $record->delete();

        return back()->with('status', $this->title().' berhasil dihapus.');
    }
}
