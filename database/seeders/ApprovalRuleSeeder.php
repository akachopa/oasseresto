<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Aturan approval bawaan: nilai kecil cukup manajer cabang, nilai besar naik
 * ke general manager, nilai sangat besar wajib owner (PLAN 44).
 */
class ApprovalRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::all() as $company) {
            $rules = [
                [
                    'document_type' => 'purchase_request',
                    'name' => 'PR di atas 5 juta',
                    'sequence' => 1,
                    'min_amount' => 5_000_000,
                    'approver_role' => 'Branch Manager',
                ],
                [
                    'document_type' => 'purchase_order',
                    'name' => 'PO 10 juta - 50 juta',
                    'sequence' => 1,
                    'min_amount' => 10_000_000,
                    'max_amount' => 50_000_000,
                    'approver_role' => 'Branch Manager',
                ],
                [
                    'document_type' => 'purchase_order',
                    'name' => 'PO di atas 50 juta',
                    'sequence' => 2,
                    'min_amount' => 50_000_000,
                    'approver_role' => 'General Manager',
                ],
                [
                    'document_type' => 'purchase_order',
                    'name' => 'PO di atas 250 juta wajib owner',
                    'sequence' => 3,
                    'min_amount' => 250_000_000,
                    'approver_role' => 'Owner',
                ],
            ];

            foreach ($rules as $rule) {
                ApprovalRule::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'document_type' => $rule['document_type'],
                        'name' => $rule['name'],
                    ],
                    $rule,
                );
            }
        }
    }
}
