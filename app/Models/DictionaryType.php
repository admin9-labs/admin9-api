<?php

namespace App\Models;

use App\Models\Traits\HasModelDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mitoop\LaravelQueryBuilder\Traits\HasFilter;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DictionaryType extends Model
{
    use HasFactory, HasFilter, HasModelDefaults, LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'description',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DictionaryItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('dict_type')->logFillable()->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'Dictionary type created',
            'updated' => 'Dictionary type updated',
            'deleted' => 'Dictionary type deleted',
            default => $eventName,
        };
    }
}
