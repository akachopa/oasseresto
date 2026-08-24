<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Company\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nomor dokumen unik per company/type/periode (PLAN 51 dan 88).
 *
 * Sequence disimpan di tabel document_sequences dan diambil dengan row lock
 * supaya dua transaksi bersamaan tidak menghasilkan nomor yang sama.
 */
class DocumentNumberService
{
    public function __construct(private readonly ScopeManager $scope) {}

    public function next(string $documentType, ?int $branchId = null, ?Carbon $date = null, ?int $companyId = null): string
    {
        $companyId ??= $this->scope->companyId();

        if ($companyId === null) {
            throw new RuntimeException('Company belum ditentukan untuk penomoran dokumen.');
        }

        $date ??= Carbon::now();
        $branchId ??= $this->scope->branchId();

        $prefix = config("oasse.numbering.prefix.{$documentType}");

        if (! $prefix) {
            $prefix = strtoupper(substr(preg_replace('/[^a-z]/', '', $documentType) ?: 'DOC', 0, 3));
        }

        $period = $date->format('Ym');
        $sequence = $this->reserveSequence($companyId, $documentType, $branchId, $period);

        return str_replace(
            ['{prefix}', '{branch}', '{year}', '{month}', '{seq}'],
            [
                $prefix,
                $this->branchCode($branchId),
                $date->format('Y'),
                $date->format('m'),
                str_pad((string) $sequence, (int) config('oasse.numbering.sequence_length', 5), '0', STR_PAD_LEFT),
            ],
            (string) config('oasse.numbering.pattern'),
        );
    }

    private function reserveSequence(int $companyId, string $documentType, ?int $branchId, string $period): int
    {
        return DB::transaction(function () use ($companyId, $documentType, $branchId, $period): int {
            $row = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where('branch_id', $branchId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('document_sequences')->insert([
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'branch_id' => $branchId,
                    'period' => $period,
                    'current_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->current_value + 1;

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update(['current_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    private function branchCode(?int $branchId): string
    {
        if ($branchId === null) {
            return 'HO';
        }

        static $cache = [];

        return $cache[$branchId] ??= (string) (
            Branch::withoutGlobalScopes()->whereKey($branchId)->value('code') ?: 'HO'
        );
    }
}
