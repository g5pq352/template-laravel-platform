@extends('admin.layout')

@section('title', '後台登入')
@section('html_class', 'fixed')

@section('body')
<section class="body-sign login-page">
    <div class="center-sign login-card">
        <a href="{{ route('admin.login') }}" class="logo float-start mb-4">
            <strong style="font-size: 22px;">Template Platform</strong>
        </a>

        <div class="panel card-sign">
            <div class="card-title-sign mt-3 text-end">
                <h2 class="title text-uppercase font-weight-bold m-0">
                    <i class="bx bx-user-circle me-1 text-6 position-relative top-5"></i> 後台登入
                </h2>
            </div>

            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="post" action="{{ route('admin.login.submit') }}">
                    @csrf
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <div class="input-group">
                            <input class="form-control form-control-lg" type="email" name="email" value="{{ old('email', 'admin@example.com') }}" required autofocus>
                            <span class="input-group-text">
                                <i class="bx bx-user text-4"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="clearfix">
                            <label class="float-start">密碼</label>
                        </div>
                        <div class="input-group">
                            <input class="form-control form-control-lg" type="password" name="password" required>
                            <span class="input-group-text">
                                <i class="bx bx-lock text-4"></i>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-8">
                            <div class="checkbox-custom checkbox-default">
                                <input id="remember" name="remember" type="checkbox">
                                <label for="remember">記住我</label>
                            </div>
                        </div>
                        <div class="col-sm-4 text-end">
                            <button type="submit" class="btn btn-primary mt-2">登入</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
