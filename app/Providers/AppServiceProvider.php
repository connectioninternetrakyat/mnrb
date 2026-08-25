<?php

namespace App\Providers;

use App\Models\MikrotikServer;
use App\Services\Ppp\MikrotikPppService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolvable from the container (scheduler/commands/tests): builds
        // the RouterOS client against the default Mikrotik server.
        $this->app->bind(MikrotikPppService::class, function (): MikrotikPppService {
            $server = MikrotikServer::default();

            if ($server === null) {
                throw new \RuntimeException(__('Belum ada server Mikrotik default.'));
            }

            return new MikrotikPppService($server);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
}
