@php
    $domainId = $domain['id'] ?? null;
    $domainName = $domain['domain'] ?? '';
    $isPrimary = (bool) ($domain['is_primary'] ?? false);
    $forceHttps = (bool) ($domain['force_https'] ?? false);
@endphp

<div class="site-domain-row border rounded p-3 mb-3">
    @if($domainId)
        <input type="hidden" name="domains[{{ $index }}][id]" value="{{ $domainId }}">
    @endif

    <div class="row align-items-center">
        <div class="col-lg-6 mb-3 mb-lg-0">
            <input type="text" name="domains[{{ $index }}][domain]" class="form-control" value="{{ $domainName }}" placeholder="example.com">
        </div>
        <div class="col-lg-2 mb-3 mb-lg-0">
            <label class="mb-0">
                <input type="radio" name="primary_domain_index" value="{{ $index }}" class="me-1" @checked($isPrimary)>
                主網域
            </label>
            <input type="hidden" name="domains[{{ $index }}][is_primary]" value="0">
        </div>
        <div class="col-lg-2 mb-3 mb-lg-0">
            <label class="mb-0">
                <input type="checkbox" name="domains[{{ $index }}][force_https]" value="1" class="me-1" @checked($forceHttps)>
                HTTPS
            </label>
        </div>
        <div class="col-lg-2 text-lg-end">
            <button type="button" class="btn btn-danger js-remove-domain">
                <i class="fas fa-trash-alt"></i> 刪除
            </button>
        </div>
    </div>
</div>
