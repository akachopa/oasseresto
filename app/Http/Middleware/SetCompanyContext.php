<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Services\ScopeManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyiapkan konteks company untuk request: global scope Eloquent lewat
 * ScopeManager dan team id spatie/permission supaya role tidak bocor
 * antar company.
 */
class SetCompanyContext
{
    public function __construct(private readonly ScopeManager $scope) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->company_id) {
            setPermissionsTeamId($user->company_id);
            $this->scope->setCompanyId((int) $user->company_id);

            $sessionBranch = $request->session()->get('oasse.branch_id');

            if ($sessionBranch && $this->scope->canAccessBranch((int) $sessionBranch)) {
                $this->scope->setBranchId((int) $sessionBranch);
            }
        }

        return $next($request);
    }
}
