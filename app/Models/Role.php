<?php

namespace App\Models;

use App\Models\Traits\HasModelDefaults;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasModelDefaults, LogsActivity;

    /**
     * Get the menus that belong to the role.
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menu');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('role')->logOnly(['name', 'guard_name', 'locale'])->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->dontSubmitEmptyLogs();
    }
}
