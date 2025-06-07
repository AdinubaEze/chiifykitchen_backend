<?php

// app/Http/Middleware/RoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== $role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - only admin is allowed to access this resources'
            ], 403);
        }

        return $next($request);
    }
}