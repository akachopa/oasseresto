<?php

namespace App\Providers;

use App\Modules\Accounting\Events\BusinessDocumentPosted;
use App\Modules\Accounting\Listeners\PostAccountingJournal;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(BusinessDocumentPosted::class, PostAccountingJournal::class);
    }
}
