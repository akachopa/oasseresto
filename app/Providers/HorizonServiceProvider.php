<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request) {
            $user = $request->user();

            return $user instanceof User && $user->can('setting.user.manage');
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user): bool {
            return $user instanceof User && $user->can('setting.user.manage');
        });
    }
}
