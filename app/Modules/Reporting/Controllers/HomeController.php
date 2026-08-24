<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Controllers;

use App\Modules\Reporting\Services\RoleHomeService;
use Illuminate\View\View;

class HomeController
{
    public function __construct(private readonly RoleHomeService $homes) {}

    public function warehouse(): View
    {
        return view('reporting.home.warehouse', $this->homes->warehouse());
    }

    public function purchasing(): View
    {
        return view('reporting.home.purchasing', $this->homes->purchasing());
    }

    public function salesman(): View
    {
        return view('reporting.home.salesman', $this->homes->salesman(auth()->user()));
    }

    public function finance(): View
    {
        return view('reporting.home.finance', $this->homes->finance());
    }
}
