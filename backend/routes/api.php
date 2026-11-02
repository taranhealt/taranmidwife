<?php
use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminPartnerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\MidwifeBookingController;
use App\Http\Controllers\Api\MidwifeProfileController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Minimal readiness endpoint: never exposes configuration, exception or database details.
Route::get('health', static function () {
    try {
        DB::connection()->select('select 1');
        return response()->json(['status' => 'ok']);
    } catch (\Throwable $exception) {
        report($exception);
        return response()->json(['status' => 'unavailable'], 503);
    }
})->middleware('throttle:60,1');
Route::prefix('v1')->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('services', [CatalogController::class, 'services']);
        Route::get('areas', [CatalogController::class, 'areas']);
    });
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('auth/forgot-password', [\App\Http\Controllers\Api\PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('auth/reset-password', [\App\Http\Controllers\Api\PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::delete('me', [\App\Http\Controllers\Api\AccountController::class, 'destroy'])->middleware('throttle:5,1');
        Route::middleware(\App\Http\Middleware\EnsureRole::class . ':patient,partner')->group(function () {
            Route::post('complaints', [\App\Http\Controllers\Api\ComplaintController::class, 'store'])->middleware('throttle:10,1');
            Route::get('complaints', [\App\Http\Controllers\Api\ComplaintController::class, 'index']);
            Route::get('complaints/{complaint}', [\App\Http\Controllers\Api\ComplaintController::class, 'show']);
        });

        Route::middleware(EnsureRole::class . ':patient,partner,ops_admin,sysadmin')->group(function () {
            Route::get('bookings', [BookingController::class, 'index']);
            Route::get('bookings/{booking}', [BookingController::class, 'show']);
        });
        Route::middleware(EnsureRole::class . ':patient')->group(function () {
            Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:booking-create');
            Route::get('coverage', [\App\Http\Controllers\Api\CoverageController::class, 'check']);
            Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        });
        Route::prefix('admin')->group(function () {
            Route::middleware(EnsureRole::class . ':ops_admin,reviewer,sysadmin')->group(function () {
                Route::get('metrics', [AdminDashboardController::class, 'metrics']);
            });
            Route::middleware(\App\Http\Middleware\EnsureRole::class . ':ops_admin,reviewer,sysadmin')->group(function () {
                Route::get('complaints', [\App\Http\Controllers\Api\AdminComplaintController::class, 'index']);
                Route::get('complaints/{complaint}', [\App\Http\Controllers\Api\AdminComplaintController::class, 'show']);
            });
            Route::middleware(\App\Http\Middleware\EnsureRole::class . ':ops_admin,reviewer')->group(function () {
                Route::post('complaints/{complaint}/start-review', [\App\Http\Controllers\Api\AdminComplaintController::class, 'startReview']);
                Route::post('complaints/{complaint}/resolve', [\App\Http\Controllers\Api\AdminComplaintController::class, 'resolve']);
                Route::post('complaints/{complaint}/close', [\App\Http\Controllers\Api\AdminComplaintController::class, 'close']);
                Route::post('complaints/{complaint}/reopen', [\App\Http\Controllers\Api\AdminComplaintController::class, 'reopen']);
            });
            Route::middleware(EnsureRole::class . ':reviewer,sysadmin')->group(function () {
                Route::get('partners', [AdminPartnerController::class, 'index']);
                Route::get('partners/{partner}', [AdminPartnerController::class, 'show']);
                Route::get('partners/{partner}/documents/{document}', [AdminPartnerController::class, 'document'])->middleware('signed')->name('api.v1.admin.partners.documents.signed');
                Route::post('partners/{partner}/start-review', [AdminPartnerController::class, 'startReview']);
                Route::post('partners/{partner}/checks', [AdminPartnerController::class, 'recordChecks']);
                Route::post('partners/{partner}/request-correction', [AdminPartnerController::class, 'requestCorrection']);
            });
            Route::middleware(EnsureRole::class . ':sysadmin')->group(function () {
                Route::post('partners/{partner}/approve', [AdminPartnerController::class, 'approve']);
                Route::post('partners/{partner}/reject', [AdminPartnerController::class, 'reject']);
                Route::post('partners/{partner}/activate', [AdminPartnerController::class, 'activate']);
                Route::post('partners/{partner}/suspend', [AdminPartnerController::class, 'suspend']);
            });
            Route::middleware(EnsureRole::class . ':ops_admin')->group(function () {
                Route::post('bookings/{booking}/screen', [AdminBookingController::class, 'screen']);
                Route::get('bookings/{booking}/candidates', [\App\Http\Controllers\Api\AdminMatchingController::class, 'candidates']);
                Route::post('bookings/{booking}/assign', [AdminBookingController::class, 'assign']);
                Route::post('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);
            });
        });
        Route::prefix('midwife')->middleware([EnsureRole::class . ':partner', 'throttle:partner-api'])->group(function () {
            Route::get('profile', [MidwifeProfileController::class, 'show']);
            Route::get('offerings', [\App\Http\Controllers\Api\PartnerOfferingsController::class, 'show']);
            Route::put('offerings', [\App\Http\Controllers\Api\PartnerOfferingsController::class, 'update']);
            Route::put('profile', [MidwifeProfileController::class, 'update']);
            Route::post('documents', [MidwifeProfileController::class, 'upload'])->middleware('throttle:partner-upload');
            Route::post('verification/submit', [MidwifeProfileController::class, 'submit']);
            Route::post('bookings/{booking}/accept', [MidwifeBookingController::class, 'accept']);
            Route::post('bookings/{booking}/decline', [MidwifeBookingController::class, 'decline']);
            Route::post('bookings/{booking}/start', [MidwifeBookingController::class, 'start']);
            Route::post('bookings/{booking}/complete', [MidwifeBookingController::class, 'complete']);
        });
    });
});
