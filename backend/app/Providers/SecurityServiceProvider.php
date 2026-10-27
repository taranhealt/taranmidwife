<?php
namespace App\Providers;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
class SecurityServiceProvider extends ServiceProvider {
    public function boot(): void {
        if ($this->app->environment('production')) { URL::forceScheme('https'); }
        Route::pushMiddlewareToGroup('web', SecurityHeaders::class);
        Route::pushMiddlewareToGroup('api', SecurityHeaders::class);
    }
}
