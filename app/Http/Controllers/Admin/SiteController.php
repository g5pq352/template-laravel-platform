<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\Tenant;
use App\Support\AdminContext;
use App\Support\SiteBootstrapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(AdminContext $context, Request $request): View
    {
        $this->authorizeMainSite($context);

        $keyword = trim((string) $request->query('keyword', ''));
        $status = (string) $request->query('status', '');

        $sites = Site::query()
            ->with(['tenant', 'domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')])
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('name', 'like', "%{$keyword}%")
                        ->orWhere('slug', 'like', "%{$keyword}%")
                        ->orWhereHas('domains', fn ($domain) => $domain->where('domain', 'like', "%{$keyword}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 12))
            ->withQueryString();

        return view('admin.sites.index', [
            'admin' => $context->user(),
            'site' => $context->site(),
            'sites' => $context->sites(),
            'managedSites' => $sites,
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    public function create(AdminContext $context): View
    {
        $this->authorizeMainSite($context);

        return view('admin.sites.form', [
            'admin' => $context->user(),
            'site' => $context->site(),
            'sites' => $context->sites(),
            'managedSite' => null,
            'tenants' => $this->tenants(),
            'values' => [
                'tenant_id' => Tenant::query()->orderBy('name')->value('id'),
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'domains' => [],
                'api_allowed_origins' => '',
                'cms_set_path' => '',
                'repository_path' => '',
                'db_connection' => '',
                'db_host' => '',
                'db_port' => '3306',
                'db_database' => '',
                'db_username' => '',
                'db_password' => '',
                'enabled_modules' => ['news', 'products', 'contact'],
                'custom_modules' => [],
                'production_domain' => '',
                'frontend_url' => '',
                'admin_url' => '',
                'git_repository_url' => '',
                'initialized_at' => '',
                'domain_bound_at' => '',
                'deployment_notes' => '',
            ],
        ]);
    }

    public function store(AdminContext $context, Request $request, SiteBootstrapper $bootstrapper): RedirectResponse
    {
        $this->authorizeMainSite($context);

        $data = $this->validatedData($request);

        $site = DB::transaction(function () use ($data, $bootstrapper): Site {
            $site = Site::query()->create($this->sitePayload($data));
            $this->syncDomains($site, $data['domains'] ?? []);
            $bootstrapper->bootstrap($site);

            return $site;
        });

        $request->session()->put('current_site_id', $site->id);

        return redirect()
            ->route('admin.sites.edit', $site)
            ->with('status', '站台已新增');
    }

    public function edit(AdminContext $context, Site $site): View
    {
        $this->authorizeMainSite($context);

        $site->load(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')]);
        $database = Arr::wrap($site->settings['database'] ?? []);
        $deployment = Arr::wrap($site->settings['deployment'] ?? []);

        return view('admin.sites.form', [
            'admin' => $context->user(),
            'site' => $context->site(),
            'sites' => $context->sites(),
            'managedSite' => $site,
            'tenants' => $this->tenants(),
            'values' => [
                ...$site->attributesToArray(),
                'domains' => $site->domains->map(fn (SiteDomain $domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'is_primary' => $domain->is_primary,
                    'force_https' => $domain->force_https,
                ])->all(),
                'api_allowed_origins' => implode("\n", Arr::wrap($site->settings['api_allowed_origins'] ?? [])),
                'cms_set_path' => $site->settings['cms_set_path'] ?? '',
                'repository_path' => $site->settings['repository_path'] ?? '',
                'db_connection' => $database['connection'] ?? '',
                'db_host' => $database['host'] ?? '',
                'db_port' => $database['port'] ?? '3306',
                'db_database' => $database['database'] ?? '',
                'db_username' => $database['username'] ?? '',
                'db_password' => '',
                'enabled_modules' => Arr::wrap($site->settings['enabled_modules'] ?? []),
                'custom_modules' => Arr::wrap($site->settings['custom_modules'] ?? []),
                'production_domain' => $deployment['production_domain'] ?? '',
                'frontend_url' => $deployment['frontend_url'] ?? '',
                'admin_url' => $deployment['admin_url'] ?? '',
                'git_repository_url' => $deployment['git_repository_url'] ?? '',
                'initialized_at' => $deployment['initialized_at'] ?? '',
                'domain_bound_at' => $deployment['domain_bound_at'] ?? '',
                'deployment_notes' => $deployment['notes'] ?? '',
            ],
        ]);
    }

    public function update(AdminContext $context, Request $request, Site $site): RedirectResponse
    {
        $this->authorizeMainSite($context);

        $data = $this->validatedData($request, $site);

        DB::transaction(function () use ($site, $data): void {
            $site->update($this->sitePayload($data, $site));
            $this->syncDomains($site, $data['domains'] ?? []);
        });

        if ($site->status !== 'active' && (int) $request->session()->get('current_site_id') === (int) $site->id) {
            $request->session()->forget('current_site_id');
        }

        return redirect()
            ->route('admin.sites.edit', $site)
            ->with('status', '站台已更新');
    }

    public function destroy(AdminContext $context, Request $request, Site $site): RedirectResponse|JsonResponse
    {
        $this->authorizeMainSite($context);

        abort_if(Site::query()->count() <= 1, 422, '至少需要保留一個站台');

        if ($this->siteHasManagedData($site)) {
            $message = '此站台仍有內容、語系、選單或媒體資料，請先改為停用，避免誤刪整站資料。';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['site' => $message]);
        }

        DB::transaction(function () use ($site): void {
            $site->domains()->delete();
            $site->delete();
        });

        if ((int) $request->session()->get('current_site_id') === (int) $site->id) {
            $request->session()->forget('current_site_id');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect_url' => route('admin.sites.index'),
            ]);
        }

        return redirect()->route('admin.sites.index')->with('status', '站台已刪除');
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $site = Site::query()->whereKey($data['site_id'])->where('status', 'active')->firstOrFail();
        $request->session()->put('current_site_id', $site->id);

        return redirect()->to($this->safeSwitchRedirect($request, (string) ($data['redirect_to'] ?? '')));
    }

    private function tenants()
    {
        return Tenant::query()->where('status', 'active')->orderBy('name')->get();
    }

    private function authorizeMainSite(AdminContext $context): void
    {
        abort_unless($context->site()?->isMainSite(), 403, '多站管理只能在主站使用。');
    }

    private function validatedData(Request $request, ?Site $site = null): array
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
                Rule::unique('sites', 'slug')->ignore($site?->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'default_locale' => ['required', 'string', 'max:30'],
            'timezone' => ['required', 'string', 'max:80'],
            'currency_code' => ['required', 'string', 'size:3'],
            'api_allowed_origins' => ['nullable', 'string'],
            'cms_set_path' => ['nullable', 'string', 'max:500'],
            'repository_path' => ['nullable', 'string', 'max:500'],
            'db_connection' => ['nullable', 'string', 'max:80'],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'db_database' => ['nullable', 'string', 'max:150'],
            'db_username' => ['nullable', 'string', 'max:150'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', Rule::in(['news', 'products', 'contact'])],
            'custom_modules' => ['nullable', 'array'],
            'custom_modules.*.name' => ['nullable', 'required_with:custom_modules.*.slug', 'string', 'max:150'],
            'custom_modules.*.slug' => ['nullable', 'required_with:custom_modules.*.name', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
            'custom_modules.*.type' => ['nullable', 'required_with:custom_modules.*.name,custom_modules.*.slug', Rule::in(['single', 'multi', 'contactus', 'info', 'list_only'])],
            'production_domain' => ['nullable', 'string', 'max:255'],
            'frontend_url' => ['nullable', 'string', 'max:255'],
            'admin_url' => ['nullable', 'string', 'max:255'],
            'git_repository_url' => ['nullable', 'string', 'max:500'],
            'initialized_at' => ['nullable', 'string', 'max:100'],
            'domain_bound_at' => ['nullable', 'string', 'max:100'],
            'deployment_notes' => ['nullable', 'string', 'max:2000'],
            'primary_domain_index' => ['nullable'],
            'domains' => ['nullable', 'array'],
            'domains.*.id' => ['nullable', 'integer', 'exists:site_domains,id'],
            'domains.*.domain' => ['nullable', 'string', 'max:255'],
            'domains.*.is_primary' => ['nullable', 'boolean'],
            'domains.*.force_https' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => '站台代號只能使用小寫英文、數字與連字號，且開頭與結尾不能是連字號。',
        ]);

        $domains = collect($data['domains'] ?? [])
            ->map(function (array $domain): array {
                $domain['domain'] = $this->normalizeDomain((string) ($domain['domain'] ?? ''));
                $domain['is_primary'] = (bool) ($domain['is_primary'] ?? false);
                $domain['force_https'] = (bool) ($domain['force_https'] ?? false);

                return $domain;
            })
            ->filter(fn (array $domain) => $domain['domain'] !== '')
            ->values();

        $primaryIndex = (string) ($data['primary_domain_index'] ?? '');
        $seen = [];
        foreach ($domains as $index => $domain) {
            $domain['is_primary'] = (string) $index === $primaryIndex;
            $domains[$index] = $domain;

            if (in_array($domain['domain'], $seen, true)) {
                abort(422, '網域不能重複：' . $domain['domain']);
            }

            $exists = SiteDomain::query()
                ->where('domain', $domain['domain'])
                ->when($site, fn ($query) => $query->where('site_id', '!=', $site->id))
                ->exists();

            if ($exists) {
                abort(422, '網域已被其他站台使用：' . $domain['domain']);
            }

            $seen[] = $domain['domain'];
        }

        if ($domains->isNotEmpty() && !$domains->contains('is_primary', true)) {
            $first = $domains->first();
            $first['is_primary'] = true;
            $domains = $domains->replace([0 => $first]);
        }

        $primaryFound = false;
        $data['domains'] = $domains
            ->map(function (array $domain) use (&$primaryFound): array {
                if ($domain['is_primary'] && !$primaryFound) {
                    $primaryFound = true;
                } else {
                    $domain['is_primary'] = false;
                }

                return $domain;
            })
            ->all();

        return $data;
    }

    private function sitePayload(array $data, ?Site $site = null): array
    {
        $origins = collect(preg_split('/\R/', (string) ($data['api_allowed_origins'] ?? '')) ?: [])
            ->map(fn (string $origin) => trim($origin))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $settings = Arr::wrap($site?->settings ?? []);
        $settings['api_allowed_origins'] = $origins;
        $settings['cms_set_path'] = $this->nullableSetting($data['cms_set_path'] ?? null);
        $settings['repository_path'] = $this->nullableSetting($data['repository_path'] ?? null);

        $database = Arr::wrap($settings['database'] ?? []);
        $database['connection'] = $this->nullableSetting($data['db_connection'] ?? null);
        $database['host'] = $this->nullableSetting($data['db_host'] ?? null);
        $database['port'] = $this->nullableSetting($data['db_port'] ?? null);
        $database['database'] = $this->nullableSetting($data['db_database'] ?? null);
        $database['username'] = $this->nullableSetting($data['db_username'] ?? null);

        if ($this->nullableSetting($data['db_password'] ?? null) !== null) {
            $database['password'] = (string) $data['db_password'];
        }

        $database = array_filter($database, fn ($value) => $value !== null && $value !== '');
        if ($database === []) {
            unset($settings['database']);
        } else {
            $settings['database'] = $database;
        }

        $settings['enabled_modules'] = collect($data['enabled_modules'] ?? [])
            ->map(fn (string $module) => trim($module))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $settings['custom_modules'] = collect($data['custom_modules'] ?? [])
            ->map(function (array $module): array {
                return [
                    'name' => $this->nullableSetting($module['name'] ?? null),
                    'slug' => $this->nullableSetting($module['slug'] ?? null),
                    'type' => $this->nullableSetting($module['type'] ?? null) ?: 'single',
                ];
            })
            ->filter(fn (array $module) => $module['name'] || $module['slug'])
            ->values()
            ->all();

        $deployment = [
            'production_domain' => $this->nullableSetting($data['production_domain'] ?? null),
            'frontend_url' => $this->nullableSetting($data['frontend_url'] ?? null),
            'admin_url' => $this->nullableSetting($data['admin_url'] ?? null),
            'git_repository_url' => $this->nullableSetting($data['git_repository_url'] ?? null),
            'initialized_at' => $this->nullableSetting($data['initialized_at'] ?? null),
            'domain_bound_at' => $this->nullableSetting($data['domain_bound_at'] ?? null),
            'notes' => $this->nullableSetting($data['deployment_notes'] ?? null),
        ];
        $deployment = array_filter($deployment, fn ($value) => $value !== null && $value !== '');
        if ($deployment === []) {
            unset($settings['deployment']);
        } else {
            $settings['deployment'] = $deployment;
        }

        $settings = array_filter($settings, fn ($value) => !($value === null || $value === [] || $value === ''));

        return [
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'default_locale' => $data['default_locale'],
            'timezone' => $data['timezone'],
            'currency_code' => strtoupper($data['currency_code']),
            'settings' => $settings,
        ];
    }

    private function nullableSetting(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function syncDomains(Site $site, array $domains): void
    {
        $keepIds = [];

        foreach ($domains as $domain) {
            $model = !empty($domain['id'])
                ? $site->domains()->whereKey($domain['id'])->first()
                : null;

            $model ??= $site->domains()->make();
            $model->fill([
                'domain' => $domain['domain'],
                'is_primary' => (bool) $domain['is_primary'],
                'force_https' => (bool) $domain['force_https'],
            ]);
            $model->save();

            $keepIds[] = $model->id;
        }

        $site->domains()
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = trim($domain, "/ \t\n\r\0\x0B");

        return strtolower($domain);
    }

    private function safeSwitchRedirect(Request $request, string $redirectTo): string
    {
        $fallback = route('admin.dashboard');
        $redirectTo = trim($redirectTo);

        if ($redirectTo === '') {
            return $fallback;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $targetHost = parse_url($redirectTo, PHP_URL_HOST);
        $targetPath = parse_url($redirectTo, PHP_URL_PATH) ?: '';

        if ($targetHost && $targetHost !== $appHost) {
            return $fallback;
        }

        if (!str_starts_with('/' . ltrim($targetPath, '/'), '/admin')) {
            return $fallback;
        }

        if (str_contains($targetPath, '/admin/sites')) {
            return $fallback;
        }

        return $redirectTo;
    }

    private function siteHasManagedData(Site $site): bool
    {
        foreach ([
            'content_types',
            'contents',
            'products',
            'taxonomy_terms',
            'languages',
            'language_packs',
            'cms_menus',
            'media_files',
            'media_folders',
            'home_displays',
            'contact_messages',
        ] as $table) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            if (!\Illuminate\Support\Facades\Schema::hasColumn($table, 'site_id')) {
                continue;
            }

            if (DB::table($table)->where('site_id', $site->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
