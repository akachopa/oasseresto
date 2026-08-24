<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController
{
    public function __construct(private readonly ExportService $export) {}

    public function download(Request $request, string $type, string $format): StreamedResponse|Response
    {
        abort_unless(in_array($type, ['products', 'customers', 'suppliers', 'sales', 'purchases'], true), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        abort_unless($this->allowed($type), 403);

        $from = $request->date('from') ? Carbon::parse($request->date('from')) : now()->startOfMonth();
        $to = $request->date('to') ? Carbon::parse($request->date('to')) : now();

        return $this->export->download($type, $format, $from, $to);
    }

    private function allowed(string $type): bool
    {
        $user = auth()->user();

        return match ($type) {
            'products' => $user->can('product.view') && $user->can('report.export'),
            'customers' => $user->can('customer.view') && $user->can('report.export'),
            'suppliers' => $user->can('supplier.view') && $user->can('report.export'),
            'sales' => $user->can('report.sales'),
            'purchases' => $user->can('report.purchase'),
        };
    }
}
