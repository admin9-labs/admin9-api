<?php

namespace App\Models;

use App\Models\Traits\HasModelDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Menu extends Model
{
    use HasFactory, HasModelDefaults, HasRecursiveRelationships, LogsActivity;

    protected static function booted(): void
    {
        static::saved(function (Menu $menu) {
            if ($menu->type === self::TYPE_BUTTON && ! empty($menu->permission)) {
                if (! preg_match('/^[a-zA-Z0-9_.]+$/', $menu->permission)) {
                    throw new \InvalidArgumentException("Invalid permission format: {$menu->permission}");
                }

                \Spatie\Permission\Models\Permission::findOrCreate($menu->permission, 'api');
            }
        });
    }

    public const TYPE_DIRECTORY = 1;

    public const TYPE_MENU = 2;

    public const TYPE_BUTTON = 3;

    protected $attributes = [
        'parent_id' => 0,
        'type' => self::TYPE_DIRECTORY,
    ];

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'path',
        'component',
        'permission',
        'locale',
        'icon',
        'sort',
        'is_hidden',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getParentKeyName(): string
    {
        return 'parent_id';
    }

    public function getPathName(): string
    {
        return 'ancestor_path';
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort');
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_menu');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('menu')->logFillable()->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'Menu created',
            'updated' => 'Menu updated',
            'deleted' => 'Menu deleted',
            default => $eventName,
        };
    }
}
