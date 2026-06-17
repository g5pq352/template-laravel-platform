@extends('admin.partials.shell')

@php
    $formAction = $term ? '編輯' : '新增';
    $sections = $taxonomyConfig['form_sections'] ?? [];
    $statusOptions = $taxonomyConfig['status_options'] ?? [1 => '顯示', 0 => '不顯示'];

    $languageParams = ($languageEnabled ?? false) ? ($languageParams ?? array_filter(['language' => $languageContext['slug'] ?? null])) : [];

    $fieldValue = function (array $field) use ($values) {
        $name = $field['name'];
        return old($name, $values[$name] ?? null);
    };
@endphp

@section('title', $taxonomyModel->name . $formAction)
@section('page_title', $taxonomyModel->name . $formAction)

@section('breadcrumb')
    <li><span>{{ $taxonomyModel->name }}</span></li>
    <li><span>{{ $formAction }}</span></li>
@endsection

@section('content')
<form class="ecommerce-form" method="post" action="{{ $term ? route('admin.taxonomies.update', [$taxonomy, $term, ...$languageParams]) : route('admin.taxonomies.store', [$taxonomy, ...$languageParams]) }}">
    @csrf
    @if($term)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col">
            <div class="cms-page-actions">
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => old('parent_id', $values['parent_id'] ?? null), ...$languageParams])) }}">
                    <i class="bx bx-left-arrow-alt me-1"></i> 返回
                </a>
                <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" type="submit" id="submitBtn">
                    <i class="bx bx-save me-1"></i> 儲存 (alt+s)
                </button>
            </div>

            <section class="card card-modern card-big-info">
                <div class="card-body">
                    <div class="tabs-modern row" style="min-height: 512px;">
                        <div class="col-lg-2-5 col-xl-1-5">
                            <div class="nav flex-column" role="tablist" aria-orientation="vertical">
                                @foreach($sections as $section)
                                    <a class="nav-link @if($loop->first) active @endif" id="tab-{{ $loop->iteration }}" data-bs-toggle="pill" data-bs-target="#tab{{ $loop->iteration }}" role="tab" aria-controls="tab{{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="bx bx-cog me-2"></i> {{ $section['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content">
                                <input type="hidden" name="locale" value="{{ old('locale', $values['locale'] ?? $site->default_locale) }}">

                                @foreach($sections as $section)
                                    <div class="tab-pane fade @if($loop->first) show active @endif" id="tab{{ $loop->iteration }}" role="tabpanel" aria-labelledby="tab-{{ $loop->iteration }}">
                                        @foreach($section['fields'] as $field)
                                            @php
                                                $name = $field['name'];
                                                $type = $field['type'] ?? 'text';
                                                $value = $fieldValue($field);
                                                $required = !empty($field['required']);
                                                $note = $field['note'] ?? null;
                                                $hideParent = $name === 'parent_id' && !($taxonomyModel->is_hierarchical ?? true);
                                                $fieldTaxonomyOptions = $taxonomyOptionsByField[$name] ?? [];
                                                $fieldSelectedTermIds = old($name, $selectedTermIdsByField[$name] ?? []);
                                                $taxonomyInputName = $name . (!empty($field['multiple']) ? '[]' : '');
                                            @endphp

                                            @continue($hideParent)

                                            <div class="form-group row cms-form-row {{ in_array($type, ['textarea'], true) ? '' : 'align-items-center' }}">
                                                <label class="col-lg-5 col-xl-2 control-label text-lg-end {{ $type === 'textarea' ? 'pt-2 mt-1' : 'mb-0' }}">
                                                    {{ $field['label'] }}
                                                    @if($required)<span class="required">*</span>@endif
                                                </label>
                                                <div class="col-lg-7 col-xl-7">
                                                    @if($name === 'parent_id')
                                                        @php
                                                            $selectedParentId = (int) old('parent_id', $values['parent_id'] ?? 0);
                                                        @endphp
                                                        <select class="form-control form-control-modern" name="parent_id" data-plugin-selectTwo>
                                                            <option value="">全部</option>
                                                            @foreach($parentOptions as $option)
                                                                @php($parentLabelPrefix = str_repeat("\u{00a0}\u{00a0}\u{00a0}", (int) ($option['depth'] ?? 0)))
                                                                <option value="{{ $option['id'] }}" @selected($selectedParentId === (int) $option['id'])>{{ $parentLabelPrefix }}{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($type === 'select' && $name === 'is_active')
                                                        <select class="form-control form-control-md" name="is_active" required>
                                                            @foreach($statusOptions as $optionValue => $optionLabel)
                                                                <option value="{{ $optionValue }}" @selected((string) old('is_active', (int) ($values['is_active'] ?? true)) === (string) $optionValue)>{{ $optionLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif(in_array($type, ['taxonomy', 'linked_taxonomy'], true))
                                                        <select class="form-control form-control-md" name="{{ $taxonomyInputName }}" data-plugin-selectTwo {{ !empty($field['multiple']) ? 'multiple' : '' }}>
                                                            @if(empty($field['multiple']))
                                                                <option value="">-- 請選擇 --</option>
                                                            @endif
                                                            @foreach($fieldTaxonomyOptions as $option)
                                                                <option value="{{ $option['id'] }}" @selected(in_array((int) $option['id'], array_map('intval', (array) $fieldSelectedTermIds), true))>{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($type === 'textarea')
                                                        <textarea class="form-control form-control-modern" name="{{ $name }}" rows="{{ $field['rows'] ?? 5 }}" @required($required)>{{ $value }}</textarea>
                                                    @else
                                                        <input class="form-control form-control-modern" type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $name }}" value="{{ $value }}" @required($required)>
                                                    @endif

                                                    @if($note)
                                                        <div class="text-danger text-2 mt-2">{!! $note !!}</div>
                                                    @endif
                                                </div>
                                            </div>
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
            document.getElementById('submitBtn')?.click();
        }
    });
</script>
@endpush
