<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seab rakenduse keele eesti keele failidele (nt lang/et/validation.php).
 * Ilma selleta jääb APP_LOCALE=en korral Laraveli ingliskeelne vaiketekst.
 */
class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('et');

        return $next($request);
    }
}
