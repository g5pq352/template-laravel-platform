@extends('admin.partials.shell')

@section('title', $moduleConfig['label'])
@section('page_title', $moduleConfig['label'])

@section('breadcrumb')
    <li><span>{{ $moduleConfig['label'] }}</span></li>
@endsection

@section('content')
<form class="ecommerce-form" method="post" enctype="multipart/form-data" action="{{ route('admin.info.update', $module) }}">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col">
            <div class="cms-page-actions">
                <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4 btn-save" type="submit">
                    <i class="fas fa-floppy-disk"></i> 儲存 (alt+s)
                </button>
            </div>

            <section class="card card-modern card-big-info">
                <div class="card-body">
                    <div class="tabs-modern row" style="min-height: 490px;">
                        <div class="col-lg-2-5 col-xl-1-5">
                            <div class="nav flex-column" id="info-tabs" role="tablist" aria-orientation="vertical">
                                @foreach($moduleConfig['sections'] as $sectionKey => $section)
                                    <a class="nav-link @if($loop->first) active @endif" id="tab-{{ $sectionKey }}" data-bs-toggle="pill" data-bs-target="#pane-{{ $sectionKey }}" role="tab" aria-controls="pane-{{ $sectionKey }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="bx bx-cog me-2"></i> {{ $section['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content" id="info-tabs-panel">
                                @foreach($moduleConfig['sections'] as $sectionKey => $section)
                                    <div class="tab-pane fade @if($loop->first) show active @endif" id="pane-{{ $sectionKey }}" role="tabpanel" aria-labelledby="tab-{{ $sectionKey }}">
                                        @foreach($section['fields'] as $field)
                                            @include('admin.resources.partials.field', [
                                                'field' => $field,
                                                'value' => old($field['name'], $values[$field['name']] ?? null),
                                                'mediaByRole' => $mediaByRole,
                                                'statusOptions' => $field['options'] ?? $statusOptions,
                                                'taxonomyOptions' => $taxonomyOptions,
                                                'selectedTermIds' => $selectedTermIds,
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
    document.addEventListener('keydown', function (event) {
        if (event.altKey && event.key.toLowerCase() === 's') {
            event.preventDefault();
            document.querySelector('.ecommerce-form')?.submit();
        }
    });

    window.deleteImageItem = window.deleteImageItem || async function (mediaId) {
        if (!await window.cmsConfirm('確定要刪除這張圖片？')) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_file[]';
        input.value = mediaId;
        document.querySelector('.ecommerce-form')?.appendChild(input);
        document.getElementById('img_item_' + mediaId)?.remove();
    };
</script>
@endpush
