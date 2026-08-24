<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Supplier\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export XLSX/CSV/PDF untuk master data dan laporan operasional (PLAN 56).
 */
class ExportService
{
    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    public function dataset(string $type, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now();

        return match ($type) {
            'products' => $this->products(),
            'customers' => $this->customers(),
            'suppliers' => $this->suppliers(),
            'sales' => $this->sales($from, $to),
            'purchases' => $this->purchases($from, $to),
            default => throw new \InvalidArgumentException('Jenis export tidak dikenali.'),
        };
    }

    public function download(string $type, string $format, ?Carbon $from = null, ?Carbon $to = null): StreamedResponse|Response
    {
        [$header, $rows] = $this->dataset($type, $from, $to);
        $filename = $type.'-'.now()->format('Ymd-His');

        return match ($format) {
            'csv' => $this->spreadsheet($header, $rows, $filename.'.csv', new CsvWriter),
            'xlsx' => $this->spreadsheet($header, $rows, $filename.'.xlsx', new XlsxWriter),
            'pdf' => $this->pdf($type, $header, $rows, $filename.'.pdf'),
            default => throw new \InvalidArgumentException('Format export tidak dikenali.'),
        };
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function products(): array
    {
        $header = ['SKU', 'Nama', 'Kategori', 'Brand', 'Satuan', 'Harga', 'Reorder'];
        $rows = Product::query()
            ->with(['category', 'brand', 'baseUnit'])
            ->orderBy('sku')
            ->get()
            ->map(fn (Product $p) => [
                $p->sku, $p->name, $p->category?->name, $p->brand?->name,
                $p->baseUnit?->code, $p->base_price, $p->reorder_point,
            ])
            ->all();

        return [$header, $rows];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function customers(): array
    {
        $header = ['Kode', 'Nama', 'Telepon', 'Kota', 'Limit'];
        $rows = Customer::query()->orderBy('code')->get()
            ->map(fn (Customer $c) => [$c->code, $c->name, $c->phone, $c->city, $c->credit_limit])
            ->all();

        return [$header, $rows];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function suppliers(): array
    {
        $header = ['Kode', 'Nama', 'Telepon', 'Kota', 'Lead time'];
        $rows = Supplier::query()->orderBy('code')->get()
            ->map(fn (Supplier $s) => [$s->code, $s->name, $s->phone, $s->city, $s->lead_time_days])
            ->all();

        return [$header, $rows];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function sales(Carbon $from, Carbon $to): array
    {
        $header = ['Nomor', 'Tanggal', 'Customer', 'Total', 'HPP'];
        $rows = SalesInvoice::query()
            ->with('customer')
            ->where('status', DocumentStatus::Posted)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $to->toDateString())
            ->orderBy('invoice_date')
            ->get()
            ->map(fn (SalesInvoice $i) => [
                $i->number, $i->invoice_date->toDateString(), $i->customer?->name, $i->total, $i->cost_of_goods,
            ])
            ->all();

        return [$header, $rows];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function purchases(Carbon $from, Carbon $to): array
    {
        $header = ['Nomor', 'Tanggal', 'Supplier', 'Total'];
        $rows = PurchaseInvoice::query()
            ->with('supplier')
            ->where('status', DocumentStatus::Posted)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $to->toDateString())
            ->orderBy('invoice_date')
            ->get()
            ->map(fn (PurchaseInvoice $i) => [
                $i->number, $i->invoice_date->toDateString(), $i->supplier?->name, $i->total,
            ])
            ->all();

        return [$header, $rows];
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function spreadsheet(array $header, array $rows, string $filename, CsvWriter|XlsxWriter $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $writer): void {
            $tmp = tempnam(sys_get_temp_dir(), 'oasse-export-');

            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValues($header));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(
                    fn ($value) => is_scalar($value) || $value === null ? $value : (string) $value,
                    $row,
                )));
            }

            $writer->close();
            readfile($tmp);
            @unlink($tmp);
        }, $filename);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function pdf(string $type, array $header, array $rows, string $filename): Response
    {
        return Pdf::loadView('core.export.pdf', [
            'title' => strtoupper($type),
            'header' => $header,
            'rows' => $rows,
        ])->download($filename);
    }
}
