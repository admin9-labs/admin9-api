<?php

namespace App\Models;

use App\Models\Traits\HasModelDefaults;
use Mitoop\LaravelQueryBuilder\Traits\HasFilter;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class AuditLog extends SpatieActivity
{
    use HasFilter, HasModelDefaults;
}
