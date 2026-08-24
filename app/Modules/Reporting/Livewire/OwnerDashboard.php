<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Livewire;

use App\Modules\Reporting\Services\OwnerDashboardService;
use Livewire\Component;

class OwnerDashboard extends Component
{
    public ?string $focus = null;

    public function show(string $focus): void
    {
        $this->focus = $this->focus === $focus ? null : $focus;
    }

    public function render()
    {
        $board = app(OwnerDashboardService::class)->build();

        return view('reporting.livewire.owner-dashboard', [
            'today' => $board['today'],
            'yesterday' => $board['yesterday'],
            'month' => $board['month'],
            'alerts' => $board['alerts'],
            'trend' => $board['trend'],
            'showFinancial' => $board['show_financial'],
            'drill' => $this->focus
                ? app(OwnerDashboardService::class)->drill($this->focus)
                : null,
        ]);
    }
}
