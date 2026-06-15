@php
    $children = $menu->getRelation('visibleChildren') ?? collect();
    $settings = is_array($menu->settings) ? $menu->settings : [];
    $routeParams = $settings['route_params'] ?? [];
    $queryParams = $settings['query_params'] ?? [];
    $homeContentTypeId = $homeContentTypeId ?? null;
    $href = '#';

    if ($menu->module_key === 'home') {
        $href = $homeContentTypeId
            ? route('admin.contents.index', ['content_type_id' => $homeContentTypeId])
            : route('admin.contents.index');
    } elseif ($menu->module_key === 'settings') {
        $href = route('admin.info.edit', 'keywordsInfo');
    } elseif ($menu->route_name && \Illuminate\Support\Facades\Route::has($menu->route_name)) {
        $href = route($menu->route_name, $routeParams + $queryParams);
    } elseif ($menu->url) {
        $href = $menu->url;
    }

    $matchesMenu = function ($item) use (&$matchesMenu, $homeContentTypeId) {
        if ($item->module_key === 'home') {
            return request()->routeIs('admin.contents.*')
                && (!$homeContentTypeId || (string) request()->query('content_type_id') === (string) $homeContentTypeId);
        }

        if ($item->module_key === 'settings') {
            return request()->routeIs('admin.settings.*') || (request()->routeIs('admin.info.*') && request()->route('module') === 'keywordsInfo');
        }

        $itemSettings = is_array($item->settings) ? $item->settings : [];
        $itemRouteParams = $itemSettings['route_params'] ?? [];
        $itemQueryParams = $itemSettings['query_params'] ?? [];

        if ($item->route_name && \Illuminate\Support\Facades\Route::has($item->route_name)) {
            $routePrefix = str($item->route_name)->beforeLast('.')->toString();
            $canMatchRouteGroup = substr_count($item->route_name, '.') >= 2;
            $routeMatches = request()->routeIs($item->route_name) || ($canMatchRouteGroup && request()->routeIs($routePrefix . '.*'));

            if (!$routeMatches) {
                return false;
            }

            foreach ($itemRouteParams as $key => $value) {
                $actual = request()->route($key);
                if ($actual === null) {
                    $actual = request()->query($key);
                }

                if ((string) $actual !== (string) $value) {
                    return false;
                }
            }

            foreach ($itemQueryParams as $key => $value) {
                if ((string) request()->query($key) !== (string) $value) {
                    return false;
                }
            }

            if ($item->route_name === 'admin.menus.index' && !array_key_exists('location', $itemRouteParams) && !array_key_exists('location', $itemQueryParams)) {
                return request()->query('location', 'backend') === 'backend';
            }

            return true;
        }

        if ($item->url && $item->url !== '#') {
            $path = ltrim(parse_url($item->url, PHP_URL_PATH) ?: $item->url, '/');
            return request()->is($path) || request()->is($path . '/*') || request()->fullUrlIs(url($item->url) . '*');
        }

        return false;
    };

    $isActive = $matchesMenu($menu);

    foreach ($children as $child) {
        $isActive = $isActive || $matchesMenu($child);
    }
@endphp

<li @class(['nav-parent' => $children->isNotEmpty(), 'nav-expanded' => $children->isNotEmpty() && $isActive, 'nav-active' => $isActive])>
    <a class="nav-link" href="{{ $children->isNotEmpty() ? '#' : $href }}" @if($children->isEmpty() && $menu->target === '_blank') target="_blank" rel="noopener" @endif>
        @if($menu->icon)
            <i class="{{ $menu->icon }}" aria-hidden="true"></i>
        @endif
        <span>{{ $menu->title }}</span>
    </a>

    @if($children->isNotEmpty())
        <ul class="nav nav-children">
            @foreach($children as $child)
                @include('admin.partials.menu-item', [
                    'menu' => $child,
                    'homeContentTypeId' => $homeContentTypeId,
                ])
            @endforeach
        </ul>
    @endif
</li>
