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
        // Pengecekan apakah user sudah login dan role-nya adalah customer (2)
        if (auth()->check() && auth()->user()->role == 2) {
            return $next($request);
        }

        // Jika tidak memenuhi syarat, arahkan kembali
        return redirect('/auth/redirect')->with('msgError', 'Anda harus login sebagai customer');
    }
}