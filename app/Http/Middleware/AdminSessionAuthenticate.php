<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_user_id');

        if (!$adminId || !AdminUser::query()->whereKey($adminId)->where('status', 'active')->exists()) {
            $request->session()->forget(['admin_user_id', 'current_site_id']);

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
