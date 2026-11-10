<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->get('admin')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
