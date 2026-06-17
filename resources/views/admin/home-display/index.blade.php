@extends('admin.partials.shell')

@section('title', $config['label'])
@section('page_title', $config['label'])

@section('breadcrumb')
    <li><span>首頁</span></li>
    <li><span>首頁顯示設定</span></li>
@endsection

@section('content')
<div class="row">
    <div class="col">
        @if(($languageContext['languages'] ?? collect())->count() > 1)
            <div class="row align-items-center mb-3">
                <div class="col-12 col-lg-auto ms-auto mb-3 mb-lg-0">
                    <ul class="nav nav-pills nav-pills-primary justify-content-lg-end">
                        @foreach($languageContext['languages'] as $language)
                            <li class="nav-item">
                                <a @class(['nav-link py-1 px-3', 'active' => ($languageContext['slug'] ?? null) === $language->slug])
                                   href="{{ route('admin.home-display.index', array_merge(request()->except(['page', 'language']), ['language' => $language->slug])) }}">
                                    {{ $language->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper dataTables_wrapper mt-2">
                    <div class="datatable-header">
                        <form method="get" action="{{ route('admin.home-display.index') }}" class="row align-items-center mb-3">
                            <input type="hidden" name="language" value="{{ $languageContext['slug'] ?? '' }}">

                            <div class="col-4 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0">
                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                    <label class="ws-nowrap me-3 mb-0">Show:</label>
                                    <select class="form-control select-style-1 results-per-page" name="per_page" onchange="this.form.submit()">
                                        @foreach([12, 24, 36, 100] as $size)
                                            <option value="{{ $size }}" @selected((int) request('per_page', data_get($config, 'listPage.itemsPerPage', 12)) === $size)>{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-lg-auto ps-lg-1">
                                <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                    <div class="input-group">
                                        <input type="text" class="search-term form-control" name="search" id="search-term" placeholder="Search Category" value="{{ $keyword }}">
                                        <button class="btn btn-default search-button" type="submit"><i class="bx bx-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <table class="table table-ecommerce-simple table-striped mb-0 dataTable no-footer" id="datatable-ecommerce-list" style="min-width: 550px;">
                        <thead>
                            <tr>
                                @foreach(data_get($config, 'listPage.columns', []) as $column)
                                    <th class="sorting_disabled" @if(!empty($column['width'])) width="{{ $column['width'] }}" @endif>{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                @php
                                    $translation = $item->translation($languageContext['locale'] ?? $site->default_locale);
                                    $isInHome = !empty($item->home_display_id);
                                    $homeSort = (int) ($item->home_sort_order ?? 0);
                                @endphp
                                <tr data-content-id="{{ $item->id }}">
                                    <td>{{ optional($item->published_at ?? $item->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td>
                                        <a class="font-weight-semibold" href="{{ route("admin.{$targetResource}.edit", [$item->id, ...$languageParams]) }}">{{ $translation?->title ?: '-' }}</a>
                                        @if($translation?->summary)
                                            <div class="text-muted text-2 mt-1">{{ $translation->summary }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <select class="form-control-sm js-home-sort" style="width: 55px;" data-url="{{ route('admin.home-display.sort', [$item->id, ...$languageParams]) }}" @disabled(!$isInHome)>
                                            @for($i = 1; $i <= max(1, $displayCount); $i++)
                                                <option value="{{ $i }}" @selected($homeSort === $i)>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </td>
                                    <td>
                                        <button class="btn js-home-toggle" type="button" data-url="{{ route('admin.home-display.toggle', [$item->id, ...$languageParams]) }}" style="background-color: {{ $isInHome ? '#28a745' : '#dc3545' }}; color: #fff; padding: 5px 10px; border-radius: 4px;">
                                            {{ $isInHome ? '顯示' : '不顯示' }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count(data_get($config, 'listPage.columns', [])) }}" class="text-center text-muted cms-table-empty">No data available in table</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <hr class="solid mt-5 opacity-4">
                    <div class="datatable-footer">
                        <div class="row align-items-center justify-content-center mt-3">
                            <div class="col-lg-auto text-center cms-count">
                                顯示第 {{ $items->firstItem() ?? 0 }} 至 {{ $items->lastItem() ?? 0 }} 項結果，共 {{ $items->total() }} 項
                            </div>

                            @if($items->hasPages())
                                <div class="col-lg-auto mb-3 mb-lg-0">
                                    {{ $items->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload || {}),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        }

        document.querySelectorAll('.js-home-toggle').forEach(function (button) {
            button.addEventListener('click', async function () {
                button.disabled = true;
                try {
                    await postJson(button.dataset.url, {});
                    window.location.reload();
                } catch (error) {
                    button.disabled = false;
                    cmsAlert('首頁顯示切換失敗，請稍後再試。', 'error');
                }
            });
        });

        document.querySelectorAll('.js-home-sort').forEach(function (select) {
            select.dataset.previousValue = select.value;
            select.addEventListener('change', async function () {
                try {
                    await postJson(select.dataset.url, {sort_order: select.value});
                    window.location.reload();
                } catch (error) {
                    select.value = select.dataset.previousValue;
                    cmsAlert('排序更新失敗，請稍後再試。', 'error');
                }
            });
        });
    });
</script>
@endpush
