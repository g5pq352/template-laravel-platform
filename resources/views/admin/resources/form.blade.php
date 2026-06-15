@extends('admin.partials.shell')

@php
    $readonlyForm = (bool) ($resourceConfig['readonly'] ?? false);
    $formAction = $readonlyForm ? '查看' : ($item ? '編輯' : '新增');
    $moduleLabel = match ($resource) {
        'news' => '最新消息',
        'products' => '產品',
        'contact' => '聯絡我們',
        default => $resourceConfig['label'],
    };
@endphp

@section('title', $resourceConfig['label'] . $formAction)
@section('page_title', $resourceConfig['label'] . $formAction)

@section('breadcrumb')
    <li><span>{{ $moduleLabel }}</span></li>
    <li><span>{{ $moduleLabel }}{{ $formAction }}</span></li>
@endsection

@section('content')
<form class="ecommerce-form" method="post" enctype="multipart/form-data" action="{{ $item ? route("admin.{$resource}.update", $item) : route("admin.{$resource}.store") }}">
    @csrf
    @if($item)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col">
            <div class="cms-page-actions">
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route("admin.{$resource}.index") }}">
                    <i class="fas fa-arrow-left"></i> 返回
                </a>
                @unless($readonlyForm)
                    <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4 btn-save" type="button" id="submitBtn">
                        <i class="fas fa-floppy-disk"></i> 儲存 (alt+s)
                    </button>
                @endunless
            </div>

            <input type="hidden" name="locale" value="{{ old('locale', $values['locale'] ?? $site?->default_locale) }}">

            <section class="card card-modern card-big-info">
                <div class="card-body">
                    <div class="tabs-modern row" style="min-height: 490px;">
                        <div class="col-lg-2-5 col-xl-1-5">
                            <div class="nav flex-column" id="tab" role="tablist" aria-orientation="vertical">
                                @foreach($resourceConfig['form_sections'] as $sectionKey => $section)
                                    <a class="nav-link @if($loop->first) active @endif" id="tab-{{ $loop->iteration }}" data-bs-toggle="pill" data-bs-target="#tab{{ $loop->iteration }}" role="tab" aria-controls="tab{{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="bx bx-cog me-2"></i> {{ $section['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content" id="tabContent">
                                @foreach($resourceConfig['form_sections'] as $sectionKey => $section)
                                    <div class="tab-pane fade @if($loop->first) show active @endif" id="tab{{ $loop->iteration }}" role="tabpanel" aria-labelledby="tab-{{ $loop->iteration }}">
                                        @foreach($section['fields'] as $field)
                                            @include('admin.resources.partials.field', [
                                                'field' => $field,
                                                'value' => old($field['name'], $values[$field['name']] ?? null),
                                                'statusOptions' => $resourceConfig['status_options'] ?? [],
                                                'mediaByRole' => $mediaByRole ?? [],
                                                'taxonomyOptions' => $taxonomyOptions,
                                                'selectedTermIds' => old('term_ids', $selectedTermIds),
                                            ])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const readonlyForm = @json($readonlyForm);

    document.addEventListener('keydown', function (event) {
        if (readonlyForm) return;

        if (event.altKey && event.key.toLowerCase() === 's') {
            event.preventDefault();
            document.getElementById('submitBtn')?.click();
        }
    });

    document.getElementById('submitBtn')?.addEventListener('click', function () {
        this.closest('form')?.submit();
    });
</script>
@endpush
