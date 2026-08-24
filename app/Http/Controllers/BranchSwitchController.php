<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController
{
    public function __invoke(Request $request, ScopeManager $scope): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $branchId = (int) $validated['branch_id'];

        abort_unless($scope->canAccessBranch($branchId), 403);
        abort_unless(Branch::whereKey($branchId)->exists(), 404);

        $request->session()->put('oasse.branch_id', $branchId);

        return back()->with('status', 'Cabang aktif berhasil diganti.');
    }
}
