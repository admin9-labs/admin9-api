<?php

namespace App\Models;

use App\Models\Traits\HasModelDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mitoop\LaravelQueryBuilder\Traits\HasFilter;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DictionaryItem extends Model
{
    use HasFactory, HasFilter, HasModelDefaults, LogsActivity;

    protected $fillable = [
        'dictionary_type_id',
        'label',
        'value',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function dictionaryType(): BelongsTo
    {
        return $this->belongsTo(DictionaryType::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('dict_item')->logFillable()->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->dontSubmitEmptyLogs();
    }
}
