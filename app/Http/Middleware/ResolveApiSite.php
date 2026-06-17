<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\SiteDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = $this->resolveSite($request);

        abort_unless($site, 404, 'Site not found.');

        $request->attributes->set('site', $site);
        $request->attributes->set('site_id', $site->id);

        return $next($request);
    }

    private function resolveSite(Request $request): ?Site
    {
        $siteKey = $request->header('X-Site')
            ?: $request->query('site')
            ?: $request->getHost();

        $cacheKey = 'api_site:' . md5((string) $siteKey);

        $siteId = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($siteKey): ?int {
            $domain = SiteDomain::query()
                ->where('domain', $siteKey)
                ->first();

            if ($domain?->site_id) {
                return $domain->site_id;
            }

            return Site::query()
                ->where('slug', $siteKey)
                ->orWhere('id', is_numeric($siteKey) ? (int) $siteKey : 0)
                ->value('id');
        });

        return $siteId ? Site::query()->find($siteId) : null;
    }
}
