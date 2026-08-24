<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Jobs\ProcessImportJob;
use App\Modules\Core\Models\ImportJob;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController
{
    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'products' => 'Produk',
            'customers' => 'Customer',
            'suppliers' => 'Supplier',
        ];
    }

    public function index(): View
    {
        return view('core.import.index', [
            'types' => self::types(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(
            ServerTable::of(ImportJob::query()->latest())
                ->searchable(['original_filename', 'type', 'status'])
                ->orderable([null, 'created_at', 'type', 'original_filename', 'status', null])
                ->transform(fn (ImportJob $job) => [
                    'created_at' => $job->created_at?->format('d/m/Y H:i'),
                    'type' => e($job->typeLabel()),
                    'file' => e($job->original_filename),
                    'result' => $job->created_count.' baru · '.$job->updated_count.' diubah · '.$job->error_count.' error',
                    'status' => '<span class="badge-'.($job->status === 'completed' ? 'success' : ($job->status === 'failed' ? 'danger' : 'info')).'">'
                        .e($job->statusLabel()).'</span>',
                    'aksi' => view('components.row-actions', [
                        'detail' => route('settings.import.detail', $job),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:products,customers,suppliers'],
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('imports/'.auth()->user()->company_id);

        $job = ImportJob::create([
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'status' => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
        ]);

        ProcessImportJob::dispatch($job->id);

        activity()->performedOn($job)->event('created')->log('Import '.$job->typeLabel().' diantrikan');

        return redirect()->route('settings.import.detail', $job)
            ->with('status', 'Berkas masuk antrian import.');
    }

    public function detail(ImportJob $importJob): View
    {
        return view('core.import.detail', ['job' => $importJob]);
    }

    public function template(string $type): StreamedResponse
    {
        $headers = match ($type) {
            'products' => ['sku', 'name', 'category', 'brand', 'unit', 'base_price', 'reorder_point', 'barcode'],
            'customers' => ['code', 'name', 'phone', 'city', 'credit_limit', 'payment_term'],
            'suppliers' => ['code', 'name', 'phone', 'city', 'lead_time_days'],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fclose($out);
        }, 'template-'.$type.'.csv');
    }
}
