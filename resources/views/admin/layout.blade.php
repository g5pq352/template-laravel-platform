<!doctype html>
<html lang="zh-Hant-TW" class="@yield('html_class', 'sidebar-left-big-icons')">
<head>
    @php
        $routeName = request()->route()?->getName() ?? '';
        $loadsFormAssets = \Illuminate\Support\Str::endsWith($routeName, ['.create', '.edit'])
            || request()->routeIs('admin.info.*', 'admin.contents.create', 'admin.contents.edit');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '後台管理') - Template Platform</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/bootstrap/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/animate/animate.compat.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/font-awesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/boxicons/css/boxicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/datatables/media/css/dataTables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/pnotify/pnotify.custom.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/elusive-icons/css/elusive-icons.css') }}">
    @if($loadsFormAssets)
        <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/magnific-popup/magnific-popup.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/select2/css/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/dropzone/basic.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/template-style/vendor/dropzone/dropzone.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/cms-jquery/cropper/cropper.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/cms-crop/crop.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/css/skins/default.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/template-style/css/layouts/modern.css') }}">
    <script src="{{ asset('admin-assets/template-style/vendor/modernizr/modernizr.js') }}"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="{{ asset('admin-assets/cms-js/sweetalert2@11.js') }}"></script>

    <style>
        html { scrollbar-gutter: stable; }
        .chosen-container { position: relative; top: -3px; }

        .cms-page-actions { padding: 0 0 16px; }

        .table .actions {
            display: inline-flex;
            gap: 6px;
            justify-content: flex-end;
            white-space: nowrap;
        }

        .cms-table-empty {
            background: #eee;
            height: 124px;
        }

        .cms-count { color: #4f5b66; font-size: 13px; }

        .ecommerce-form .cms-form-row {
            margin-bottom: 0;
            padding-bottom: 10px;
        }

        .ecommerce-form .cms-form-row + .cms-form-row {
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .linked-select-levels {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .linked-select-levels.linked-select-levels-inline {
            flex-direction: row;
            align-items: center;
            gap: 0;
        }

        .linked-select-levels.linked-select-levels-inline .linked-select-control {
            width: auto;
            min-width: 58px;
            margin-bottom: 0 !important;
        }

        .linked-select-levels .select2-container {
            max-width: 100%;
        }

        .ecommerce-form .cms-form-row:last-child {
            padding-bottom: 0;
        }

        .badge-status {
            border-radius: 3px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0;
            padding: 8px 12px;
        }

        .login-page {
            align-items: center;
            background: #ecedf0;
            display: flex;
            min-height: 100vh;
            padding: 24px;
        }

        .login-card {
            margin: 0 auto;
            max-width: 420px;
            width: 100%;
        }

        @media (max-width: 991px) {
            .cms-count { text-align: left; }
        }
    </style>
    @stack('styles')
</head>
<body>
@yield('body')

<script src="{{ asset('admin-assets/template-style/vendor/jquery/jquery.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/vendor/jquery-browser-mobile/jquery.browser.mobile.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/vendor/popper/umd/popper.min.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/vendor/common/common.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/vendor/nanoscroller/nanoscroller.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/vendor/jquery-placeholder/jquery.placeholder.js') }}"></script>
@if($loadsFormAssets)
    <script src="{{ asset('admin-assets/template-style/vendor/magnific-popup/jquery.magnific-popup.js') }}"></script>
    <script src="{{ asset('admin-assets/template-style/vendor/select2/js/select2.js') }}"></script>
    <script src="{{ asset('admin-assets/template-style/vendor/dropzone/dropzone.js') }}"></script>
    <script src="{{ asset('admin-assets/ckeditor/ckeditor.js') }}"></script>
    <script src="{{ asset('admin-assets/cms-jquery/cropper/cropper.min.js') }}"></script>
@endif
<script src="{{ asset('admin-assets/template-style/js/theme.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/js/custom.js') }}"></script>
<script src="{{ asset('admin-assets/template-style/js/theme.init.js') }}"></script>
@if($loadsFormAssets)
    <script>
        window.CMS_IMAGE_DEMO_URL = "{{ asset('admin-assets/cms-crop/demo.jpg') }}";
    </script>
    <script src="{{ asset('admin-assets/cms-js/laravel-image-upload-manager.js') }}"></script>
@endif
<script>
    window.cmsAlert = function (message, icon = 'info', options = {}) {
        return Swal.fire({
            icon,
            title: options.title || '提示',
            text: message,
            confirmButtonText: options.confirmButtonText || '確定',
            confirmButtonColor: options.confirmButtonColor || '#0088cc',
        });
    };

    window.cmsConfirm = async function (message, options = {}) {
        const result = await Swal.fire({
            icon: options.icon || 'warning',
            title: options.title || '確認操作',
            text: message,
            showCancelButton: true,
            confirmButtonText: options.confirmButtonText || '確定',
            cancelButtonText: options.cancelButtonText || '取消',
            confirmButtonColor: options.confirmButtonColor || '#d33',
            cancelButtonColor: options.cancelButtonColor || '#6c757d',
            reverseButtons: true,
        });

        return result.isConfirmed;
    };

    window.initCmsLinkedTaxonomy = function (fieldId, selectedId, options = {}) {
        const input = document.getElementById(fieldId);
        const treeScript = document.getElementById(fieldId + '_tree');
        const wrapper = input?.closest('.linked-select-wrapper');
        const levels = wrapper?.querySelector('.linked-select-levels');

        if (!input || !treeScript || !levels) {
            return;
        }

        const requireLeaf = options.requireLeaf !== false;
        const submitOnChange = options.submitOnChange === true;
        const submitMode = options.submitMode || wrapper.dataset.submitMode || 'always';
        const inline = options.layout === 'inline' || wrapper.dataset.layout === 'inline';
        const useSelect2 = options.select2 !== false && wrapper.dataset.select2 !== 'false';
        levels.classList.toggle('linked-select-levels-inline', inline);

        let terms = [];
        try {
            terms = JSON.parse(treeScript.textContent || '[]');
        } catch (error) {
            terms = [];
        }

        const byParent = new Map();
        const byId = new Map();
        terms.forEach(function (term) {
            const id = Number(term.id);
            const parentId = term.parent_id === null || term.parent_id === undefined || term.parent_id === '' ? null : Number(term.parent_id);
            const normalized = {id, parent_id: parentId, label: term.label};
            byId.set(id, normalized);
            const key = parentId === null ? 'root' : String(parentId);
            if (!byParent.has(key)) {
                byParent.set(key, []);
            }
            byParent.get(key).push(normalized);
        });

        function selectedPath(id) {
            const path = [];
            let current = byId.get(Number(id));

            while (current) {
                path.unshift(current.id);
                current = current.parent_id === null ? null : byId.get(current.parent_id);
            }

            return path;
        }

        function childrenOf(parentId) {
            return byParent.get(parentId === null ? 'root' : String(parentId)) || [];
        }

        function enhanceSelect(select) {
            if (!useSelect2 || !window.jQuery || !jQuery.fn.select2) {
                return;
            }

            jQuery(select).select2({
                theme: 'bootstrap',
                width: inline ? '180px' : '100%',
            });
        }

        function submitOwnerForm(hasChildSelect) {
            if (!submitOnChange) {
                return;
            }

            if (submitMode === 'leaf' && hasChildSelect) {
                return;
            }

            input.closest('form')?.submit();
        }

        function renderLevel(parentId, selectedValue) {
            const children = childrenOf(parentId);
            if (children.length === 0) {
                return null;
            }

            const select = document.createElement('select');
            select.className = inline
                ? 'form-control select-style-1 me-2 category-filter linked-select-level linked-select-control'
                : 'form-control form-control-md linked-select-level linked-select-control mb-2';
            select.dataset.parentId = parentId === null ? '' : String(parentId);

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = wrapper.dataset.placeholder || '-- 請選擇 --';
            select.appendChild(placeholder);

            children.forEach(function (term) {
                const option = document.createElement('option');
                option.value = String(term.id);
                option.textContent = term.label;
                option.selected = Number(selectedValue) === term.id;
                select.appendChild(option);
            });

            select.addEventListener('change', function () {
                let next = select.nextElementSibling;
                while (next) {
                    const remove = next;
                    next = next.nextElementSibling;
                    if (window.jQuery && jQuery.fn.select2 && jQuery(remove).data('select2')) {
                        jQuery(remove).select2('destroy');
                    }
                    remove.remove();
                }

                if (select.value) {
                    const childSelect = renderLevel(Number(select.value), null);
                    if (childSelect) {
                        input.value = requireLeaf ? '' : select.value;
                        levels.appendChild(childSelect);
                        enhanceSelect(childSelect);
                        submitOwnerForm(true);
                    } else {
                        input.value = select.value;
                        submitOwnerForm(false);
                    }
                } else {
                    input.value = select.dataset.parentId || '';
                    submitOwnerForm(false);
                }
            });

            return select;
        }

        levels.innerHTML = '';

        const path = selectedId ? selectedPath(selectedId) : [];
        let parentId = null;
        let lastSelected = null;

        if (path.length === 0) {
            const firstLevel = renderLevel(null, null);
            if (firstLevel) {
                levels.appendChild(firstLevel);
                enhanceSelect(firstLevel);
            }
            input.value = '';
            return;
        }

        path.forEach(function (id) {
            const select = renderLevel(parentId, id);
            if (select) {
                levels.appendChild(select);
                enhanceSelect(select);
            }

            parentId = id;
            lastSelected = id;
        });

        const nextLevel = renderLevel(parentId, null);
        if (nextLevel) {
            levels.appendChild(nextLevel);
            enhanceSelect(nextLevel);
        }

        input.value = nextLevel && requireLeaf ? '' : (lastSelected || '');
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-confirm]').forEach(function (item) {
            item.addEventListener('submit', async function (event) {
                if (item.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();

                if (await window.cmsConfirm(item.getAttribute('data-confirm'))) {
                    item.dataset.confirmed = '1';
                    item.submit();
                }
            });
        });

        if (window.CKEDITOR && window.jQuery) {
            jQuery('textarea.tiny').each(function (index, el) {
                if (!el.id) {
                    el.id = 'ckeditor_' + index;
                }

                if (!CKEDITOR.instances[el.id]) {
                    CKEDITOR.replace(el.id, {
                        height: '350px',
                        ignoreReadOnlyWarning: true,
                        pasteFromWordRemoveFontStyles: true,
                        pasteFromWordRemoveStyles: true,
                        forcePasteAsPlainText: false,
                        pasteFilter: 'p; h1; h2; h3; h4; h5; h6; ul; ol; li; strong; em; u; a[!href]; img[!src,alt,width,height]; br',
                        removeFormatAttributes: 'class,style,lang,width,height,align,hspace,valign',
                        removeFormatTags: 'font,span'
                    });
                }
            });
        }
    });
</script>
@stack('scripts')
</body>
</html>
