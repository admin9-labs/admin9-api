<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AuditLogCleanScheduleTest extends TestCase
{
    public function test_activitylog_clean_is_scheduled_daily(): void
    {
        $schedule = app(Schedule::class);

        $matched = collect($schedule->events())->first(function ($event) {
            return str_contains($event->command, 'activitylog:clean');
        });

        $this->assertNotNull($matched, 'activitylog:clean command is not scheduled');
        $this->assertEquals('0 0 * * *', $matched->expression, 'activitylog:clean should run daily');
    }
}
