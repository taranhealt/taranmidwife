<?php
namespace App\Providers;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
class SecurityServiceProvider extends ServiceProvider {
    public function boot(): void {
        if ($this->app->environment('production')) { URL::forceScheme('https'); }
        Route::pushMiddlewareToGroup('web', SecurityHeaders::class);
        Route::pushMiddlewareToGroup('api', SecurityHeaders::class);
        // Pembatas bernama: tiap pembatas punya hitungan sendiri per pengguna, tidak
        // tercampur dengan throttle lain (throttle tanpa nama berbagi satu hitungan).
        RateLimiter::for('partner-api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->getAuthIdentifier() ?: $r->ip()));
        RateLimiter::for('partner-upload', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->getAuthIdentifier() ?: $r->ip()));
    }
}
