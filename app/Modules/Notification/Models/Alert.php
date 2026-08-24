<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\AlertSeverity;
use App\Modules\Core\Enums\AlertType;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'is_resolved' => 'boolean',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function tone(): string
    {
        return match ($this->severity) {
            AlertSeverity::Critical => 'negative',
            AlertSeverity::Warning => 'caution',
            AlertSeverity::Info => 'informative',
        };
    }
}
