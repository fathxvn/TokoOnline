<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('auth.redirect')->with('msgError', 'Anda harus login sebagai customer');
        }

        abort_unless((string) $request->user()->role === '2' && (int) $request->user()->status === 1, 403);

        return $next($request);
    }
}
