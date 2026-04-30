<?php

namespace App\Providers;

use App\Events\Inventory\StockLedgerCreated;
use App\Listeners\Inventory\UpdateStockBalanceFromLedger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Paksa semua URL jadi HTTPS (biasanya perlu jika di belakang proxy/Cloudflare)
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // define super admin
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Event::listen(StockLedgerCreated::class, UpdateStockBalanceFromLedger::class);
    }
}
