<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Listeners\AuthenticationActivitySubscriber;
use App\Models\User;
use App\Policies\RolePolicy;
use App\RoleName;
use App\View\Composers\PublicSettingsComposer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();

        Event::subscribe(AuthenticationActivitySubscriber::class);

        View::composer(['welcome', 'components.app-logo', 'layouts.public'], PublicSettingsComposer::class);

        Livewire::addPersistentMiddleware([
            EnsurePasswordHasBeenChanged::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure application authorization.
     */
    private function configureAuthorization(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(
            fn (User $user, string $ability): ?bool => $user->hasRole(RoleName::SuperAdmin)
                ? true
                : null,
        );
    }
}
