<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use Illuminate\View\View;

class DashboardController
{
    public function index(): View
    {
        return view('core.dashboard');
    }

    /**
     * Halaman "Lainnya" untuk mobile: seluruh menu yang tidak muat di bottom nav.
     */
    public function more(): View
    {
        return view('core.more');
    }
}
