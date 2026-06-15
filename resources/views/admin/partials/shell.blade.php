@extends('admin.layout')

@php
    $pageTitle = trim($__env->yieldContent('page_title', $__env->yieldContent('title', '後台管理')));
    $taxonomy = request()->route('taxonomy');

    $isHomeDisplay = request()->routeIs('admin.home-display.*');
    $isHome = $isHomeDisplay || (request()->routeIs('admin.info.*') && request()->route('module') === 'popInfo');
    $isDashboard = request()->routeIs('admin.dashboard');
    $isNews = request()->routeIs('admin.news.*') || (request()->routeIs('admin.taxonomies.*') && in_array($taxonomy, ['newsCate', 'newsTag'], true));
    $isProducts = request()->routeIs('admin.products.*') || (request()->routeIs('admin.taxonomies.*') && in_array($taxonomy, ['productCate', 'productTag'], true));
    $isContact = request()->routeIs('admin.contact.*');
    $isSettings = request()->routeIs('admin.settings.*') || (request()->routeIs('admin.info.*') && request()->route('module') === 'keywordsInfo');
    $isMediaLibrary = request()->routeIs('admin.media-library.*');
    $isMenus = request()->routeIs('admin.menus.*');

    $menuLocation = request()->query('location', 'backend');
    $backendMenus = collect();

    if (isset($site) && class_exists(\App\Models\CmsMenu::class) && \Illuminate\Support\Facades\Schema::hasTable('cms_menus')) {
        $allBackendMenus = \App\Models\CmsMenu::query()
            ->where('site_id', $site->id)
            ->where('location', 'backend')
            ->where('is_active', true)
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $buildBackendMenuTree = function ($parentId = null) use (&$buildBackendMenuTree, $allBackendMenus) {
            return $allBackendMenus
                ->where('parent_id', $parentId)
                ->values()
                ->each(function ($menu) use (&$buildBackendMenuTree) {
                    $menu->setRelation('visibleChildren', $buildBackendMenuTree($menu->id));
                });
        };

        $backendMenus = $buildBackendMenuTree();
    }
@endphp

@section('body')
<section class="body">
    <header class="header">
        <div class="logo-container">
            <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
                <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
            </div>
        </div>

        <div class="header-right">
            <div class="userbox">
                <div>
                    <span>登出時間：</span>
                    <span id="time-countdown">60:00</span>
                </div>
            </div>

            <span class="separator"></span>

            <div class="userbox">
                <div>
                    <a href="{{ url('/') }}" target="_blank" rel="noopener">
                        觀看首頁 <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </div>

            <span class="separator"></span>

            <div id="userbox" class="userbox">
                <a href="#" data-bs-toggle="dropdown">
                    <figure class="profile-picture">
                        <img src="{{ asset('admin-assets/template-style/img/!logged-user.jpg') }}" alt="SuperAdmin" class="rounded-circle" data-lock-picture="{{ asset('admin-assets/template-style/img/!logged-user.jpg') }}">
                    </figure>
                    <div class="profile-info" data-lock-name="SuperAdmin">
                        <span class="name"><strong>SuperAdmin</strong></span>
                        <span class="role mt-1">超級管理員</span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>

                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li>
                            <a role="menuitem" tabindex="-1" href="{{ route('admin.info.edit', 'keywordsInfo') }}"><i class="bx bx-cog"></i>全站設定</a>
                        </li>
                        <li>
                            <form method="post" action="{{ route('admin.logout') }}">
                                @csrf
                                <button class="dropdown-item text-start" type="submit">
                                    <i class="bx bx-power-off"></i>登出
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="inner-wrapper">
        <aside id="sidebar-left" class="sidebar-left">
            <div class="sidebar-header">
                <div class="sidebar-title">Navigation</div>
                <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
                    <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
                </div>
            </div>

            <div class="nano">
                <div class="nano-content">
                    <nav id="menu" class="nav-main" role="navigation">
                        <ul class="nav nav-main mb-5">
                            @if($backendMenus->isNotEmpty())
                                @foreach($backendMenus as $backendMenu)
                                    @include('admin.partials.menu-item', [
                                        'menu' => $backendMenu,
                                    ])
                                @endforeach
                            @else
                                <li @class(['nav-active' => $isDashboard])>
                                    <a class="nav-link" href="{{ route('admin.dashboard') }}">
                                        <i class="bx bx-home-alt" aria-hidden="true"></i>
                                        <span>Dashboard</span>
                                    </a>
                                </li>

                                <li @class(['nav-active' => $isMediaLibrary])>
                                    <a class="nav-link" href="{{ route('admin.media-library.index') }}">
                                        <i class="bx bx-images" aria-hidden="true"></i>
                                        <span>圖片庫</span>
                                    </a>
                                </li>

                                <li @class(['nav-parent', 'nav-expanded nav-active' => $isHome])>
                                    <a class="nav-link" href="#">
                                        <i class="bx bx-file" aria-hidden="true"></i>
                                        <span>首頁</span>
                                    </a>
                                    <ul class="nav nav-children">
                                        <li @class(['nav-active' => $isHomeDisplay])>
                                            <a class="nav-link" href="{{ route('admin.home-display.index') }}">首頁顯示</a>
                                        </li>
                                        <li @class(['nav-active' => request()->routeIs('admin.info.*') && request()->route('module') === 'popInfo'])>
                                            <a class="nav-link" href="{{ route('admin.info.edit', 'popInfo') }}">燈箱設定</a>
                                        </li>
                                    </ul>
                                </li>

                                <li @class(['nav-parent', 'nav-expanded nav-active' => $isNews])>
                                    <a class="nav-link" href="#">
                                        <i class="bx bx-file" aria-hidden="true"></i>
                                        <span>最新消息</span>
                                    </a>
                                    <ul class="nav nav-children">
                                        <li @class(['nav-active' => request()->routeIs('admin.news.*')])>
                                            <a class="nav-link" href="{{ route('admin.news.index') }}">最新消息列表</a>
                                        </li>
                                        <li @class(['nav-active' => request()->routeIs('admin.taxonomies.*') && $taxonomy === 'newsCate'])>
                                            <a class="nav-link" href="{{ route('admin.taxonomies.index', 'newsCate') }}">分類</a>
                                        </li>
                                        <li @class(['nav-active' => request()->routeIs('admin.taxonomies.*') && $taxonomy === 'newsTag'])>
                                            <a class="nav-link" href="{{ route('admin.taxonomies.index', 'newsTag') }}">標籤</a>
                                        </li>
                                    </ul>
                                </li>

                                <li @class(['nav-parent', 'nav-expanded nav-active' => $isProducts])>
                                    <a class="nav-link" href="#">
                                        <i class="bx bx-file" aria-hidden="true"></i>
                                        <span>產品</span>
                                    </a>
                                    <ul class="nav nav-children">
                                        <li @class(['nav-active' => request()->routeIs('admin.products.*')])>
                                            <a class="nav-link" href="{{ route('admin.products.index') }}">產品列表</a>
                                        </li>
                                        <li @class(['nav-active' => request()->routeIs('admin.taxonomies.*') && $taxonomy === 'productCate'])>
                                            <a class="nav-link" href="{{ route('admin.taxonomies.index', 'productCate') }}">分類</a>
                                        </li>
                                        <li @class(['nav-active' => request()->routeIs('admin.taxonomies.*') && $taxonomy === 'productTag'])>
                                            <a class="nav-link" href="{{ route('admin.taxonomies.index', 'productTag') }}">標籤</a>
                                        </li>
                                    </ul>
                                </li>

                                <li @class(['nav-parent', 'nav-expanded nav-active' => $isContact])>
                                    <a class="nav-link" href="#">
                                        <i class="bx bx-detail" aria-hidden="true"></i>
                                        <span>聯絡我們</span>
                                    </a>
                                    <ul class="nav nav-children">
                                        <li @class(['nav-active' => request()->routeIs('admin.contact.*')])>
                                            <a class="nav-link" href="{{ route('admin.contact.index') }}">聯絡我們列表</a>
                                        </li>
                                    </ul>
                                </li>

                                <li @class(['nav-active' => $isSettings])>
                                    <a class="nav-link" href="{{ route('admin.info.edit', 'keywordsInfo') }}">
                                        <i class="bx bx-cog" aria-hidden="true"></i>
                                        <span>全站設定</span>
                                    </a>
                                </li>

                                <li class="nav-parent">
                                    <a class="nav-link" href="#">
                                        <i class="bx bx-user-circle" aria-hidden="true"></i>
                                        <span>權限管理</span>
                                    </a>
                                    <ul class="nav nav-children">
                                        <li><a class="nav-link" href="#">管理員列表</a></li>
                                        <li><a class="nav-link" href="#">角色權限列表</a></li>
                                    </ul>
                                </li>

                                <li @class(['nav-parent', 'nav-expanded nav-active' => $isMenus])>
                                    <a class="nav-link" href="#">
                                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                                        <span>選單管理</span>
                                    </a>
                                    <ul class="nav nav-children">
                                        <li @class(['nav-active' => $isMenus && $menuLocation === 'backend'])>
                                            <a class="nav-link" href="{{ route('admin.menus.index', ['location' => 'backend']) }}">後端選單列表</a>
                                        </li>
                                        <li @class(['nav-active' => $isMenus && $menuLocation === 'frontend'])>
                                            <a class="nav-link" href="{{ route('admin.menus.index', ['location' => 'frontend']) }}">前端選單列表</a>
                                        </li>
                                        <li @class(['nav-active' => $isMenus && $menuLocation === 'footer'])>
                                            <a class="nav-link" href="{{ route('admin.menus.index', ['location' => 'footer']) }}">頁尾選單列表</a>
                                        </li>
                                    </ul>
                                </li>
                            @endif
                        </ul>
                    </nav>
                </div>
            </div>
        </aside>

        <section role="main" class="content-body">
            <header class="page-header">
                <h2>{{ $pageTitle }}</h2>

                <div class="right-wrapper text-end">
                    <ol class="breadcrumbs">
                        <li>
                            <a href="{{ route('admin.dashboard') }}">
                                <i class="bx bx-home-alt"></i>
                            </a>
                        </li>
                        @hasSection('breadcrumb')
                            @yield('breadcrumb')
                        @else
                            <li><span>{{ $pageTitle }}</span></li>
                        @endif
                    </ol>

                    <a class="sidebar-right-toggle" data-open="sidebar-right" style="pointer-events: none;"></a>
                </div>
            </header>

            @yield('content')
        </section>
    </div>
</section>
@endsection
