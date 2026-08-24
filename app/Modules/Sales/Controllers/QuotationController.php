<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class QuotationController
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('sales.quotation.index', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Quotation::query()
            ->join('customers', 'customers.id', '=', 'quotations.customer_id')
            ->select(['quotations.*', 'customers.name as customer_name']);

        $this->location->applyBranch($query, 'quotations.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['quotations.number', 'customers.name', 'customers.code'])
                ->orderable([
                    null, 'quotations.number', 'quotations.quotation_date',
                    'customers.name', 'quotations.valid_until',
                    'quotations.total', 'quotations.status', null,
                ])
                ->filter('customer', fn ($q, $value) => $q->where('quotations.customer_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('quotations.status', $value))
                ->transform(fn (Quotation $quotation) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('sales.quotations.detail', $quotation).'">'.e($quotation->number).'</a>',
                    'date' => $quotation->quotation_date->format('d/m/Y'),
                    'customer' => e((string) $quotation->customer_name),
                    'valid' => ($quotation->valid_until?->format('d/m/Y') ?? '-')
                        .($quotation->isExpired() ? ' <span class="badge-warning ml-1">kedaluwarsa</span>' : ''),
                    'total' => Money::rupiah($quotation->total),
                    'status' => view('components.status-badge', ['status' => $quotation->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('sales.quotations.detail', $quotation),
                        'edit' => $quotation->status->isEditable() ? route('sales.quotations.edit', $quotation) : null,
                        'delete' => $quotation->status->isEditable() ? route('sales.quotations.hapus', $quotation) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        return view('sales.quotation.create', [
            'customerId' => $request->integer('customer') ?: null,
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        abort_unless($quotation->status->isEditable(), 403);

        return view('sales.quotation.edit', ['quotation' => $quotation]);
    }

    public function detail(Quotation $quotation): View
    {
        $quotation->load([
            'items.product.baseUnit', 'items.unit', 'items.taxCode',
            'customer', 'warehouse', 'salesman', 'creator', 'orders',
        ]);

        return view('sales.quotation.detail', ['quotation' => $quotation]);
    }

    public function send(Quotation $quotation): RedirectResponse
    {
        try {
            $this->quotations->send($quotation);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penawaran ditandai terkirim ke customer.');
    }

    public function accept(Quotation $quotation): RedirectResponse
    {
        try {
            $this->quotations->accept($quotation);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penawaran ditandai diterima customer.');
    }

    public function hapus(Quotation $quotation): RedirectResponse
    {
        try {
            $this->quotations->cancel($quotation);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penawaran dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::Submitted->value => 'Terkirim',
            DocumentStatus::Approved->value => 'Diterima Customer',
            DocumentStatus::Completed->value => 'Jadi Order',
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }
}
