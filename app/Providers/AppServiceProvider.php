<?php

namespace App\Providers;

use App\Models\Todo;
use Illuminate\Support\Facades\Route;
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
        Route::bind('todo', function (string $value): Todo {
            $user = request()->user();

            if ($user === null) {
                abort(401);
            }

            return $user->todos()->whereKey($value)->firstOrFail();
        });
    }
}
