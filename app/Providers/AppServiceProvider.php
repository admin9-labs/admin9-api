<?php

namespace App\Providers;

use App\Enums\Role;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Mitoop\Http\Exceptions\Handler;
use Mitoop\Http\JsonResponderDefault;
use Mitoop\LaravelQueryLogger\QueryDebugger;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public $singletons = [
        ExceptionHandler::class => Handler::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('request_id', function () {
            return (string) Str::uuid7();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        Event::listen(ArtisanStarting::class, function () {
            if (app()->runningInConsole()) {
                Context::addIf('request_id', app('request_id'));
            }
        });

        app(JsonResponderDefault::class)->apply([
            'extra' => [
                'request_id' => app('request_id'),
            ],
        ]);

        QueryDebugger::enableWhen(function () {
            return is_local();
        });

        Gate::before(function ($user, $ability) {
            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });

        Activity::saving(function (Activity $activity) {
            $extra = array_filter([
                'ip' => Context::get('ip'),
                'user_agent' => Context::get('user_agent'),
            ], fn ($v) => $v !== null);

            if (! empty($extra)) {
                $props = $activity->properties ?? collect();
                $activity->properties = $props->merge($extra);
            }
        });
    }
}
