<?php

namespace Metribay\Core\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use Metribay\Core\Actions;
use Metribay\Core\Console\DemoCommand;
use Metribay\Core\Console\InstallCommand;
use Metribay\Core\Console\SyncCommand;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Contracts\Entitlements;
use Metribay\Core\Contracts\GoogleAuthorization;
use Metribay\Core\Contracts\PageSpeedCredentials;
use Metribay\Core\Contracts\ProviderRequests;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Http\Middleware\DemoMode;
use Metribay\Core\Http\Middleware\DiscardAbandonedSetup;
use Metribay\Core\Http\Middleware\SetLocale;
use Metribay\Core\Http\Middleware\UseWorkspace;
use Metribay\Core\Livewire\Dashboard;
use Metribay\Core\Livewire\ProjectCharts;
use Metribay\Core\Services\OwnConnectionCredentials;
use Metribay\Core\Services\OwnGoogleAuthorization;
use Metribay\Core\Services\OwnPageSpeedCredentials;
use Metribay\Core\Services\PersonalEntitlements;
use Metribay\Core\Services\PersonalWorkspaceAccess;
use Metribay\Core\Services\ProjectFavicon;
use Metribay\Core\Services\UnrestrictedProviderRequests;
use Metribay\Core\Services\WorkspaceContext;

class MetribayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/metribay.php', 'metribay');
        $this->app->scoped(WorkspaceContext::class, fn () => new WorkspaceContext);
        $this->app->bindIf(WorkspaceAccess::class, PersonalWorkspaceAccess::class);
        $this->app->bindIf(Entitlements::class, PersonalEntitlements::class);
        $this->app->bindIf(GoogleAuthorization::class, OwnGoogleAuthorization::class);
        $this->app->bindIf(ConnectionCredentials::class, OwnConnectionCredentials::class);
        $this->app->bindIf(ProviderRequests::class, UnrestrictedProviderRequests::class);
        $this->app->bindIf(PageSpeedCredentials::class, OwnPageSpeedCredentials::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'metribay');
        $this->loadJsonTranslationsFrom(__DIR__.'/../../lang');
        // Framework validation, auth and password messages are unnamespaced, so add them as a shared path.
        $this->callAfterResolving('translation.loader', fn ($loader) => $loader->addPath(__DIR__.'/../../lang'));
        // Through the HTTP kernel, so the group survives whether the kernel is built before or after boot.
        // The demo signs visitors in and refuses changes before authentication and route model binding run.
        $web = function (Kernel $kernel): void {
            if (! $kernel instanceof HttpKernel) {
                return;
            }
            // Fortify and Livewire must reject stale password sessions just like dashboard routes.
            foreach ([SetLocale::class, DiscardAbandonedSetup::class, DemoMode::class, AuthenticateSession::class] as $middleware) {
                $kernel->appendMiddlewareToGroup('web', $middleware);
            }
            $kernel->addToMiddlewarePriorityAfter(StartSession::class, DemoMode::class);
        };
        $this->app->resolved(Kernel::class) ? $web($this->app->make(Kernel::class)) : $this->app->afterResolving(Kernel::class, $web);
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->publishes([__DIR__.'/../../dist' => public_path('vendor/metribay')], 'metribay-assets');
        Fortify::createUsersUsing(Actions\CreateUser::class);
        Fortify::updateUserProfileInformationUsing(Actions\UpdateProfile::class);
        Fortify::updateUserPasswordsUsing(Actions\UpdatePassword::class);
        Fortify::resetUserPasswordsUsing(Actions\ResetPassword::class);
        Fortify::loginView(fn () => view('metribay::auth.login'));
        Fortify::registerView(fn () => view('metribay::auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('metribay::auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('metribay::auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('metribay::auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('metribay::auth.two-factor-challenge'));
        Fortify::verifyEmailView(fn () => view('metribay::auth.verify-email'));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id')));
        $this->app->bind(ProjectFavicon::class, fn () => new ProjectFavicon);
        Livewire::component('metribay-dashboard', Dashboard::class);
        Livewire::component('metribay-project-charts', ProjectCharts::class);
        View::composer('metribay::layouts.app', function ($view): void {
            $request = request();
            $navigationWorkspace = $request->attributes->get('metribay.workspace');
            if (! $navigationWorkspace && $request->user()) {
                $accessible = app(WorkspaceAccess::class)->queryFor($request->user());
                $remembered = $request->hasSession() ? $request->session()->get(UseWorkspace::NAVIGATION_SESSION_KEY) : null;
                $navigationWorkspace = $remembered ? (clone $accessible)->whereKey($remembered)->first() : null;
                $navigationWorkspace ??= $accessible->orderBy('id')->first();
            }
            // Navigation never establishes a data scope on account-level pages.
            $view->with('navigationWorkspace', $navigationWorkspace);
        });
        View::composer('metribay::*', function ($view): void {
            $workspace = request()->attributes->get('metribay.workspace');
            $user = request()->user();
            $access = app(WorkspaceAccess::class);
            $view->with(['workspace' => $workspace, 'canEdit' => $user && $workspace ? $access->allows($user, $workspace, 'edit') : false, 'canManageConnections' => $user && $workspace ? $access->allows($user, $workspace, 'manage_connections') : false, 'canManageAccess' => $user && $workspace ? $access->allows($user, $workspace, 'manage_access') : false, 'marketingUrl' => config('metribay.marketing_url'), 'edition' => config('metribay.edition')]);
        });
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, SyncCommand::class, DemoCommand::class]);
        }
    }
}
