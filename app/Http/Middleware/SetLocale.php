<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->header('Accept-Language', config('app.locale'));

        // Normalize: take first 2 chars (en_US -> en)
        $locale = substr($locale, 0, 2);

        $supported = ['en', 'fr', 'es', 'de', 'ar', 'zh', 'pt', 'ru'];

        if (!in_array($locale, $supported)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
