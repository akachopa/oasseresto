<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    protected $guarded = ['id'];

    /**
     * Kolom uang dan kuantitas selalu dibaca sebagai float agar konsisten
     * di seluruh modul (PostgreSQL mengembalikan numeric sebagai string).
     */
    public function asMoney(?string $value): float
    {
        return (float) ($value ?? 0);
    }
}
