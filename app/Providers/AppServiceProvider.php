<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

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
        Schema::defaultStringLength(191);
        
        Gate::policy(Product::class, ProductPolicy::class);

        ResetPassword::createUrlUsing(function ($user, string $token) {
        return env('FRONTEND_URL', 'http://localhost:3000') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
    });

    VerifyEmail::createUrlUsing(function ($notifiable) {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $id, 'hash' => $hash]
        );

        // Extrait uniquement les query params signés pour les transmettre au frontend
        $query = parse_url($verifyUrl, PHP_URL_QUERY);

        return env('FRONTEND_URL', 'http://localhost:3000') . "/verify-email/{$id}/{$hash}?{$query}";
    });

    }
}
