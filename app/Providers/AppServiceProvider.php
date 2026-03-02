<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        class_alias(\Tighten\Ziggy\Ziggy::class, 'Ziggy\Ziggy');
    }
}