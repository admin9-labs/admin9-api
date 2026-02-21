<?php

namespace App\Providers;

use App\Enums\Role;
use App\Listeners\AuditLogSubscriber;
use App\Support\Scramble\FilterQueryParametersExtractor;
use App\Support\Scramble\SceneFormRequestParametersExtractor;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\DeprecationExtension;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestEssentialsExtension;
use Dedoc\Scramble\Support\OperationExtensions\ResponseExtension;
use Dedoc\Scramble\Support\OperationExtensions\ResponseHeadersExtension;
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
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;
use Mitoop\LaravelQueryLogger\QueryDebugger;

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

        Event::subscribe(AuditLogSubscriber::class);

        if (class_exists(Scramble::class)) {
            // Remove ErrorResponsesExtension to prevent Scramble from inferring
            // 401/404/422 responses — this project always returns HTTP 200.
            Scramble::configure()->operationTransformers->use([
                RequestEssentialsExtension::class,
                RequestBodyExtension::class,
                ResponseExtension::class,
                ResponseHeadersExtension::class,
                DeprecationExtension::class,
            ]);

            Scramble::configure()->parametersExtractors->prepend([
                SceneFormRequestParametersExtractor::class,
                FilterQueryParametersExtractor::class,
            ]);

            FormRequestParametersExtractor::ignoreInstanceOf(
                EfficientSceneFormRequest::class,
            );
        }
    }
}
