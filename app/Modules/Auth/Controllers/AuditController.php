<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditController
{
    public function index(): View
    {
        return view('auth.audit.index');
    }

    public function data(Request $request): JsonResponse
    {
        $companyId = (int) auth()->user()->company_id;

        $query = Activity::query()
            ->with('causer')
            ->where(function ($q) use ($companyId): void {
                $q->whereNull('causer_id')
                    ->orWhereIn('causer_id', function ($sub) use ($companyId): void {
                        $sub->select('id')->from('users')->where('company_id', $companyId);
                    });
            });

        return response()->json(
            ServerTable::of($query)
                ->searchable(['description', 'event', 'subject_type'])
                ->orderable([null, 'created_at', 'event', 'description', 'subject_type', null])
                ->filter('event', fn ($q, $value) => $q->where('event', $value))
                ->filter('from', fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
                ->filter('to', fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
                ->transform(fn (Activity $activity) => [
                    'created_at' => $activity->created_at?->format('d/m/Y H:i'),
                    'event' => '<span class="badge-info">'.e((string) $activity->event).'</span>',
                    'description' => e((string) $activity->description),
                    'subject' => e(class_basename((string) $activity->subject_type))
                        .($activity->subject_id ? ' #'.$activity->subject_id : ''),
                    'causer' => e($activity->causer?->name ?? 'Sistem'),
                    'aksi' => '<span class="text-muted text-xs">'
                        .e(Str::limit(json_encode($activity->properties), 60)).'</span>',
                ])
                ->make($request),
        );
    }
}
