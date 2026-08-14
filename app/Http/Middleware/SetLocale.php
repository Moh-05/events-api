<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

// Sets the response language from the Accept-Language header the Flutter app
// sends (the app picks it from the user's choice or the phone's native language).
// We only support 'ar' and 'en'; anything else falls back to Arabic (the main
// audience). Applied to every API request.
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->header('Accept-Language') === 'en' ? 'en' : 'ar';

        App::setLocale($lang);

        // Remember the language on the account so background pushes (which have no
        // request header) go out in the user's own language. Only writes when it
        // actually changed, so it's not an extra write on every request.
        $account = $request->user();
        if ($account && isset($account->language) && $account->language !== $lang) {
            $account->update(['language' => $lang]);
        }

        return $next($request);
    }
}
