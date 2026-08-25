<?php

namespace App\Providers;

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
        // Mailtrap is configured in .env but the credentials are pasted in by
        // hand. Until they are, fall back to the log driver so a queued
        // notification fails visibly in the log rather than throwing SMTP
        // auth errors all over the queue.
        if (config('mail.default') === 'smtp' && blank(config('mail.mailers.smtp.username'))) {
            config(['mail.default' => 'log']);
        }
    }
}
