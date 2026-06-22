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

        if (!$this->originIsAllowed($request, $site)) {
            return response()->json(['message' => 'API origin not allowed.'], 403);
        }

        $request->attributes->set('site', $site);
        $request->attributes->set('site_id', $site->id);

        if ($request->isMethod('OPTIONS')) {
            return $this->withCorsHeaders(response()->noContent(), $request, $site);
        }

        return $this->withCorsHeaders($next($request), $request, $site);
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

    private function originIsAllowed(Request $request, Site $site): bool
    {
        if ($this->siteApiKeyIsAllowed($request, $site)) {
            return true;
        }

        $allowedOrigins = $this->allowedOrigins($site);

        if ($allowedOrigins === []) {
            return true;
        }

        $requestOrigin = $this->requestOrigin($request);

        return $requestOrigin !== null && in_array($requestOrigin, $allowedOrigins, true);
    }

    private function siteApiKeyIsAllowed(Request $request, Site $site): bool
    {
        $expected = trim((string) ($site->settings['api_access_token'] ?? ''));
        if ($expected === '') {
            return false;
        }

        $provided = trim((string) $request->headers->get('X-Site-Api-Key', ''));
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function allowedOrigins(Site $site): array
    {
        return collect($site->settings['api_allowed_origins'] ?? [])
            ->map(fn (string $origin) => $this->normalizeOrigin($origin))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function requestOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if ($origin) {
            return $this->normalizeOrigin($origin);
        }

        $referer = $request->headers->get('Referer');
        if (!$referer) {
            return null;
        }

        $parts = parse_url($referer);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $this->normalizeOrigin($origin);
    }

    private function normalizeOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return null;
        }

        $parts = parse_url($origin);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $normalized = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        return $normalized;
    }

    private function withCorsHeaders(Response $response, Request $request, Site $site): Response
    {
        $allowedOrigins = $this->allowedOrigins($site);
        if ($allowedOrigins === []) {
            return $response;
        }

        $requestOrigin = $this->requestOrigin($request);
        if ($requestOrigin && in_array($requestOrigin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $requestOrigin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Site, X-Site-Api-Key, X-Requested-With, Authorization');
        }

        return $response;
    }
}
