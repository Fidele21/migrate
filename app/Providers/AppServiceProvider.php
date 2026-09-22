<?php

namespace App\Providers;
use Illuminate\Support\Facades\View;
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
      /* The topbar shows how many signatures are owed. Kept cheap:
           the filter only runs over reports actually open for signature,
           which is a short list. */
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('signaturesDue', $user
                ? \App\Models\Document::where('type', 'report')
                    ->where('signature_stage', 'pending_signatures')
                    ->whereNull('sealed_at')
                    ->with('inspection.team', 'signatures')
                    ->get()
                    ->filter(fn ($d) => $d->awaitingSignatureFrom($user))
                    ->count()
                : 0);
        });
    }
}
