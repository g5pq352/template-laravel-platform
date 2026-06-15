@extends('admin.partials.shell')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-md-4 col-lg-12 col-xl-4">
        <section class="card card-featured-left card-featured-primary mb-3">
            <div class="card-body">
                <div class="widget-summary">
                    <div class="widget-summary-col widget-summary-col-icon">
                        <div class="summary-icon bg-primary">
                            <i class="bx bx-file"></i>
                        </div>
                    </div>
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title">內容數量</h4>
                            <div class="info">
                                <strong class="amount">{{ number_format($contentCount) }}</strong>
                            </div>
                        </div>
                        <div class="summary-footer">
                            <a class="text-muted text-uppercase" href="{{ route('admin.contents.index') }}">View All</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-md-4 col-lg-12 col-xl-4">
        <section class="card card-featured-left card-featured-success mb-3">
            <div class="card-body">
                <div class="widget-summary">
                    <div class="widget-summary-col widget-summary-col-icon">
                        <div class="summary-icon bg-success">
                            <i class="bx bx-category"></i>
                        </div>
                    </div>
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title">內容類型</h4>
                            <div class="info">
                                <strong class="amount">{{ number_format($contentTypeCount) }}</strong>
                            </div>
                        </div>
                        <div class="summary-footer">
                            <span class="text-muted">Config driven CMS</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-md-4 col-lg-12 col-xl-4">
        <section class="card card-featured-left card-featured-info mb-3">
            <div class="card-body">
                <div class="widget-summary">
                    <div class="widget-summary-col widget-summary-col-icon">
                        <div class="summary-icon bg-info">
                            <i class="bx bx-store"></i>
                        </div>
                    </div>
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title">商品數量</h4>
                            <div class="info">
                                <strong class="amount">{{ number_format($productCount) }}</strong>
                            </div>
                        </div>
                        <div class="summary-footer">
                            <span class="text-muted">Commerce ready</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row">
    <div class="col">
        <section class="card card-modern">
            <header class="card-header">
                <h2 class="card-title">系統狀態</h2>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <strong class="d-block mb-1">目前網站</strong>
                        <span class="text-muted">{{ $site?->name ?? '未選擇網站' }}</span>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <strong class="d-block mb-1">網站代碼</strong>
                        <span class="text-muted">{{ $site?->slug ?? '-' }}</span>
                    </div>
                    <div class="col-md-4">
                        <strong class="d-block mb-1">後台架構</strong>
                        <span class="text-muted">Laravel + 多網站資料層</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
