<?php

use App\Http\Controllers\WikiController;
use App\Http\Controllers\WikiSearchController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\UserTeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return app(WikiController::class)->show(request(), 'team:index');
    }
    return redirect()->route('login');
})->name('dashboard');

// Public wiki assets (images, etc.) - no auth required
Route::prefix('wiki')->name('wiki.')->group(function () {
    Route::get('/fetch', ImageController::class)->name('fetch');
});

Route::middleware(['auth'])->group(function () {
    
    // Wiki routes
    Route::prefix('wiki')->name('wiki.')->group(function () {
        Route::get('/', [WikiController::class, 'index'])->name('index');
        Route::get('/search', [WikiController::class, 'search'])->name('search');
        Route::get('/attachments/{namespace?}', [WikiController::class, 'attachments'])->name('attachments');
        Route::get('/{page}/history', [WikiController::class, 'history'])->name('history');
        Route::get('/{page?}', [WikiController::class, 'show'])->name('show')->where('page', '.*');
    });
    
    // API routes for wiki search
    Route::prefix('api/wiki')->name('api.wiki.')->group(function () {
        Route::get('/search', [WikiSearchController::class, 'search'])->name('search');
        Route::get('/suggest', [WikiSearchController::class, 'suggest'])->name('suggest');
        Route::get('/subpages', [WikiController::class, 'getSubpages'])->name('subpages');
        Route::get('/debug-teams', [TeamController::class, 'debugTeams'])->name('debug-teams');
        Route::get('/debug-props', function () {
            $middleware = new \App\Http\Middleware\HandleInertiaRequests();
            $sharedData = $middleware->share(request());
            return response()->json([
                'teams' => $sharedData['teams'] ?? 'not_set',
                'teams_count' => is_array($sharedData['teams'] ?? null) ? count($sharedData['teams']) : 'not_array'
            ]);
        })->name('debug-props');
    });
    
    // API routes for favorites
    Route::prefix('api/favorites')->name('api.favorites.')->group(function () {
        Route::get('/', [FavoriteController::class, 'index'])->name('index');
        Route::post('/', [FavoriteController::class, 'store'])->name('store');
        Route::get('/{pageId}', [FavoriteController::class, 'show'])->name('show');
        Route::post('/toggle', [FavoriteController::class, 'toggle'])->name('toggle');
        Route::post('/reorder', [FavoriteController::class, 'updateOrder'])->name('reorder');
        Route::delete('/{id}', [FavoriteController::class, 'destroy'])->name('destroy');
    });
    
    // API routes for user teams
    Route::prefix('api/user-teams')->name('api.user-teams.')->group(function () {
        Route::get('/', [UserTeamController::class, 'index'])->name('index');
        Route::post('/', [UserTeamController::class, 'store'])->name('store');
        Route::delete('/{id}', [UserTeamController::class, 'destroy'])->name('destroy');
        Route::post('/reorder', [UserTeamController::class, 'reorder'])->name('reorder');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
