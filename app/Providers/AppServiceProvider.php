<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        if (
            class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)
            && $this->app->environment('local')
        ) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Включаем строгий режим для моделей
        Model::shouldBeStrict();

        $frontend_url = Config::string('app.frontend_url', '');

        ResetPassword::createUrlUsing(
            static function (mixed $notifiable, string $token) use ($frontend_url): string {
                assert($notifiable instanceof User);

                return $frontend_url."/reset-password/$token?email={$notifiable->getEmailForPasswordReset()}";
            },
        );

        VerifyEmail::createUrlUsing(static function (mixed $notifiable) use ($frontend_url): string {
            assert($notifiable instanceof User);
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::integer('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );

            return $frontend_url.'?verification_url='.$verificationUrl;
        });
    }
}
