<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Controllers;

use Illuminate\View\View;

class TaskController
{
    public function index(): View
    {
        return view('reporting.tasks.index');
    }
}
